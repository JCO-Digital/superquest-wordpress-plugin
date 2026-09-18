<?php
/**
 * Finds every place the SuperQuest block is used.
 *
 * @package SuperQuest
 */

namespace SuperQuest\Usage;

use SuperQuest\Block;
use SuperQuest\Quests;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans post content for the block, one page at a time.
 */
final class Scanner {

	/**
	 * Posts per page of results.
	 */
	public const PER_PAGE = 50;

	/**
	 * Every post on the requested page whose content holds the block.
	 *
	 * The block serialises into post_content as an HTML comment naming it, so
	 * a LIKE on that finds candidates; parsing the blocks then confirms each
	 * one. Trashed posts are kept and shown with their status, so a quest in
	 * the trash is not mistaken for one that is gone.
	 *
	 * @param int $page Page number, 1-based.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int}
	 */
	public static function scan( int $page = 1 ): array {
		$query  = self::query_containing(
			'<!-- wp:' . Block::NAME,
			array(
				'paged'          => max( 1, $page ),
				'posts_per_page' => self::PER_PAGE,
				'orderby'        => array(
					'type'  => 'ASC',
					'title' => 'ASC',
				),
			)
		);
		$titles = Quests::titles();
		$items  = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$quests = self::collect( parse_blocks( $post->post_content ) );

			// The LIKE also matches content that merely mentions the block
			// name, such as a code sample. Parsing settles it.
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
		$query = self::query_containing(
			'"ref":' . $ref,
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
	 * Runs a query for posts whose content contains a string.
	 *
	 * @param string               $needle Literal text to look for.
	 * @param array<string, mixed> $args   Extra WP_Query arguments.
	 *
	 * @return WP_Query
	 */
	private static function query_containing( string $needle, array $args ): WP_Query {
		global $wpdb;

		// `any` skips post types excluded from search, which is exactly where
		// patterns and template parts live, so the list is explicit.
		$post_types = array_unique(
			array_merge(
				array_values( get_post_types( array( 'public' => true ) ) ),
				array( 'wp_block', 'wp_template', 'wp_template_part' )
			)
		);

		$like  = '%' . $wpdb->esc_like( $needle ) . '%';
		$where = static function ( string $sql ) use ( $wpdb, $like ): string {
			return $sql . $wpdb->prepare( " AND {$wpdb->posts}.post_content LIKE %s", $like );
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
	 * Pulls every quest block out of a parsed block tree, however deeply it is
	 * nested.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 *
	 * @return array<int, array{quest_id: string}>
	 */
	private static function collect( array $blocks ): array {
		$found = array();

		foreach ( $blocks as $block ) {
			if ( Block::NAME === ( $block['blockName'] ?? '' ) ) {
				$quest_id = $block['attrs']['questId'] ?? '';
				$found[]  = array( 'quest_id' => is_scalar( $quest_id ) ? (string) $quest_id : '' );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = array_merge( $found, self::collect( $block['innerBlocks'] ) );
			}
		}

		return $found;
	}
}
