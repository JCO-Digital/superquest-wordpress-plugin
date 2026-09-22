<?php
/**
 * Finds every place a SuperQuest quest is used.
 *
 * @package SuperQuest
 */

namespace SuperQuest\Usage;

use SuperQuest\Block;
use SuperQuest\Elementor\Integration;
use SuperQuest\Quests;
use SuperQuest\Shortcode;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans for quests, one page of posts at a time.
 *
 * A quest reaches a page three ways, and they are not stored alike: the block
 * and the shortcode sit in post_content, while Elementor keeps its tree as
 * JSON in post meta. One query covers all three, so the pager stays honest.
 */
final class Scanner {

	/**
	 * Posts per page of results.
	 */
	public const PER_PAGE = 50;

	/**
	 * A quest placed with the block.
	 */
	public const SOURCE_BLOCK = 'block';

	/**
	 * A quest placed with the shortcode.
	 */
	public const SOURCE_SHORTCODE = 'shortcode';

	/**
	 * A quest placed with the Elementor widget.
	 */
	public const SOURCE_ELEMENTOR = 'elementor';

	/**
	 * Every post on the requested page that holds a quest.
	 *
	 * Each embed leaves a literal trace a LIKE can find: the block serialises
	 * into post_content as an HTML comment naming it, the shortcode as its
	 * own tag, and an Elementor widget as its name inside the tree's JSON.
	 * Those only find candidates; parsing then confirms each one. Trashed
	 * posts are kept and shown with their status, so a quest in the trash is
	 * not mistaken for one that is gone.
	 *
	 * @param int $page Page number, 1-based.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int}
	 */
	public static function scan( int $page = 1 ): array {
		$query = self::query_matching(
			self::embed_clauses(),
			array(
				'paged'          => max( 1, $page ),
				'posts_per_page' => self::PER_PAGE,
				'orderby'        => array(
					'type'  => 'ASC',
					'title' => 'ASC',
				),
			)
		);

		$titles    = Quests::titles();
		$elementor = self::elementor_data( wp_list_pluck( $query->posts, 'ID' ) );
		$items     = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$quests = self::collect_all( $post, $elementor[ $post->ID ] ?? '' );

			// The LIKE also matches content that merely mentions a name, such
			// as a code sample. Parsing settles it.
			if ( array() === $quests ) {
				continue;
			}

			foreach ( $quests as $index => $quest ) {
				$quests[ $index ]['title'] = $titles[ $quest['quest_id'] ] ?? '';
			}

			$items[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'type'   => $post->post_type,
				'status' => $post->post_status,
				'quests' => $quests,
				'hosts'  => 'wp_block' === $post->post_type ? self::pattern_hosts( $post->ID ) : array(),
			);
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * The posts that embed a given synced pattern.
	 *
	 * A pattern is its own wp_block post referenced by ID, so a page using one
	 * holds no quest block of its own and never turns up in the scan above.
	 *
	 * @param int $ref The wp_block post ID.
	 *
	 * @return WP_Post[]
	 */
	public static function pattern_hosts( int $ref ): array {
		$query = self::query_matching(
			array( self::content_like( '"ref":' . $ref ) ),
			array(
				'posts_per_page' => 20,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		// The LIKE also matches longer IDs starting with this one, e.g.
		// "ref":12 inside "ref":1234, so confirm each hit on a digit boundary.
		$pattern = '/"ref"\s*:\s*' . $ref . '(?!\d)/';

		return array_values(
			array_filter(
				$query->posts,
				static function ( $post ) use ( $pattern ): bool {
					return $post instanceof WP_Post && 1 === preg_match( $pattern, $post->post_content );
				}
			)
		);
	}

	/**
	 * The clauses that find a post holding a quest, in any of the three ways.
	 *
	 * @return string[] Prepared SQL fragments to be ORed together.
	 */
	private static function embed_clauses(): array {
		return array(
			self::content_like( '<!-- wp:' . Block::NAME ),
			self::content_like( '[' . Shortcode::TAG ),
			self::elementor_exists(),
		);
	}

	/**
	 * A clause matching post_content against a literal string.
	 *
	 * @param string $needle Literal text to look for.
	 *
	 * @return string Prepared SQL fragment.
	 */
	private static function content_like( string $needle ): string {
		global $wpdb;

		return $wpdb->prepare(
			"{$wpdb->posts}.post_content LIKE %s",
			'%' . $wpdb->esc_like( $needle ) . '%'
		);
	}

	/**
	 * The LIKE patterns that mark a quest inside an Elementor tree.
	 *
	 * Two of them: the widget, and a shortcode typed into one of Elementor's
	 * own widgets. Elementor keeps a rendered copy of the page in
	 * post_content, but not reliably, so the tree has to answer for both.
	 *
	 * @return string[]
	 */
	private static function elementor_likes(): array {
		global $wpdb;

		return array(
			'%' . $wpdb->esc_like( Integration::needle() ) . '%',
			'%' . $wpdb->esc_like( '[' . Shortcode::TAG ) . '%',
		);
	}

	/**
	 * A clause matching the Elementor tree stored in post meta.
	 *
	 * EXISTS rather than IN, so the subquery runs against the rows that
	 * survive the post type and status filters instead of being materialised
	 * whole. The clause is not gated on Elementor being active: a site that
	 * deactivates it still has the data, and hiding those rows would make this
	 * screen claim a quest is nowhere when it is only dormant.
	 *
	 * @return string Prepared SQL fragment.
	 */
	private static function elementor_exists(): string {
		global $wpdb;

		$likes = self::elementor_likes();

		return $wpdb->prepare(
			"EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} sq_meta
				WHERE sq_meta.post_id = {$wpdb->posts}.ID
				AND sq_meta.meta_key = %s
				AND ( sq_meta.meta_value LIKE %s OR sq_meta.meta_value LIKE %s )
			)",
			Integration::META_KEY,
			$likes[0],
			$likes[1]
		);
	}

	/**
	 * Runs a query for posts matching any of the given clauses.
	 *
	 * One query rather than one per source, so `found_posts` and
	 * `max_num_pages` stay authoritative and the pager does not lie.
	 *
	 * @param string[]             $clauses Prepared SQL fragments, ORed together.
	 * @param array<string, mixed> $args    Extra WP_Query arguments.
	 *
	 * @return WP_Query
	 */
	private static function query_matching( array $clauses, array $args ): WP_Query {
		// `any` skips post types excluded from search, which is exactly where
		// patterns and template parts live, so the list is explicit.
		$post_types = array_unique(
			array_merge(
				array_values( get_post_types( array( 'public' => true ) ) ),
				array( 'wp_block', 'wp_template', 'wp_template_part', 'elementor_library' )
			)
		);

		$where = static function ( string $sql ) use ( $clauses ): string {
			return $sql . ' AND ( ' . implode( ' OR ', $clauses ) . ' )';
		};

		add_filter( 'posts_where', $where );
		$query = new WP_Query(
			array_merge(
				array(
					'post_type'              => $post_types,
					'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
					'ignore_sticky_posts'    => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				),
				$args
			)
		);
		remove_filter( 'posts_where', $where );

		return $query;
	}

	/**
	 * The raw Elementor trees of the posts on this page that hold a quest.
	 *
	 * Meta caching stays off for the scan, because loading every field of
	 * fifty posts would pull in a great deal of unrelated JSON. This fetches
	 * the one field that matters, for the posts where it matters, in one go.
	 *
	 * @param int[] $post_ids Post IDs on this page.
	 *
	 * @return array<int, string> Raw JSON, keyed by post ID.
	 */
	private static function elementor_data( array $post_ids ): array {
		global $wpdb;

		$post_ids = array_filter( array_map( 'intval', $post_ids ) );

		if ( array() === $post_ids ) {
			return array();
		}

		// One %d per ID, so the list goes through prepare() like everything else.
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$likes        = self::elementor_likes();

		$sql = "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND ( meta_value LIKE %s OR meta_value LIKE %s ) AND post_id IN ( {$placeholders} )";

		$values = array_merge(
			array( Integration::META_KEY, $likes[0], $likes[1] ),
			$post_ids
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- an on-demand admin scan of one meta key, deliberately uncached; every value is a placeholder.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		$data = array();

		foreach ( (array) $rows as $row ) {
			$data[ (int) $row->post_id ] = (string) $row->meta_value;
		}

		return $data;
	}

	/**
	 * Every quest on a post, wherever it was embedded.
	 *
	 * Elementor mirrors a rendered copy of the page into post_content, so a
	 * post it owns is read from its tree alone; counting post_content as well
	 * would list the same quest twice.
	 *
	 * @param WP_Post $post           The post.
	 * @param string  $elementor_json Its Elementor tree, or an empty string.
	 *
	 * @return array<int, array{quest_id: string, source: string}>
	 */
	private static function collect_all( WP_Post $post, string $elementor_json ): array {
		if ( '' !== $elementor_json ) {
			$decoded = json_decode( $elementor_json, true );

			return is_array( $decoded ) ? self::collect_elementor( $decoded ) : array();
		}

		return array_merge(
			self::collect_blocks( parse_blocks( $post->post_content ) ),
			self::collect_shortcodes( $post->post_content )
		);
	}

	/**
	 * Pulls every quest block out of a parsed block tree, however deeply it is
	 * nested.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 *
	 * @return array<int, array{quest_id: string, source: string}>
	 */
	private static function collect_blocks( array $blocks ): array {
		$found = array();

		foreach ( $blocks as $block ) {
			if ( Block::NAME === ( $block['blockName'] ?? '' ) ) {
				$quest_id = $block['attrs']['questId'] ?? '';
				$found[]  = array(
					'quest_id' => is_scalar( $quest_id ) ? (string) $quest_id : '',
					'source'   => self::SOURCE_BLOCK,
				);
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = array_merge( $found, self::collect_blocks( $block['innerBlocks'] ) );
			}
		}

		return $found;
	}

	/**
	 * Pulls every quest shortcode out of a string.
	 *
	 * Core's own regex, so an escaped `[[superquest]]` and every attribute
	 * quoting style are read the way `do_shortcode()` reads them.
	 *
	 * @param string $content Content to search.
	 *
	 * @return array<int, array{quest_id: string, source: string}>
	 */
	private static function collect_shortcodes( string $content ): array {
		$found = array();

		if ( ! str_contains( $content, '[' . Shortcode::TAG ) ) {
			return $found;
		}

		$matches = array();
		$pattern = '/' . get_shortcode_regex( array( Shortcode::TAG ) ) . '/';

		if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			return $found;
		}

		foreach ( $matches as $match ) {
			// A shortcode wrapped in a second pair of brackets is an escaped
			// example, not an embed.
			if ( '[' === $match[1] && ']' === $match[6] ) {
				continue;
			}

			$atts     = shortcode_parse_atts( $match[3] );
			$quest_id = is_array( $atts ) && isset( $atts['id'] ) && is_scalar( $atts['id'] )
				? (string) $atts['id']
				: '';

			$found[] = array(
				'quest_id' => trim( $quest_id ),
				'source'   => self::SOURCE_SHORTCODE,
			);
		}

		return $found;
	}

	/**
	 * Pulls every quest out of a decoded Elementor tree.
	 *
	 * Elements nest under `elements`, so the walk mirrors the block one. Any
	 * string setting is also swept for shortcodes, which is how a quest typed
	 * into a Text Editor or Shortcode widget turns up.
	 *
	 * @param array<int, mixed> $elements Decoded Elementor elements.
	 *
	 * @return array<int, array{quest_id: string, source: string}>
	 */
	private static function collect_elementor( array $elements ): array {
		$found = array();

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$settings = isset( $element['settings'] ) && is_array( $element['settings'] )
				? $element['settings']
				: array();

			if ( Integration::WIDGET_NAME === ( $element['widgetType'] ?? '' ) ) {
				$found[] = array(
					'quest_id' => self::elementor_quest_id( $settings ),
					'source'   => self::SOURCE_ELEMENTOR,
				);
			}

			foreach ( $settings as $value ) {
				if ( is_string( $value ) ) {
					$found = array_merge( $found, self::collect_shortcodes( $value ) );
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = array_merge( $found, self::collect_elementor( $element['elements'] ) );
			}
		}

		return $found;
	}

	/**
	 * The quest an Elementor widget shows: its manual ID when set, else the
	 * one picked from the list. Same precedence as the widget itself.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 *
	 * @return string
	 */
	private static function elementor_quest_id( array $settings ): string {
		$manual = $settings[ Integration::SETTING_QUEST_ID_MANUAL ] ?? '';
		$manual = is_scalar( $manual ) ? trim( (string) $manual ) : '';

		if ( '' !== $manual ) {
			return $manual;
		}

		$picked = $settings[ Integration::SETTING_QUEST_ID ] ?? '';

		return is_scalar( $picked ) ? trim( (string) $picked ) : '';
	}
}
