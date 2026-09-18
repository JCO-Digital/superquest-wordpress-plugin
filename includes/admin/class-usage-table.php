<?php
/**
 * Table of posts holding the SuperQuest block.
 *
 * @package SuperQuest
 */

namespace SuperQuest\Admin;

use SuperQuest\Usage\Scanner;
use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( WP_List_Table::class ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists every post holding a SuperQuest block.
 */
class Usage_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'superquest-usage',
				'plural'   => 'superquest-usages',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'title'  => __( 'Where', 'superquest' ),
			'id'     => __( 'ID', 'superquest' ),
			'type'   => __( 'Type', 'superquest' ),
			'quests' => __( 'Quests', 'superquest' ),
		);
	}

	/**
	 * Cell content.
	 *
	 * @param array<string, mixed> $item        Usage entry.
	 * @param string               $column_name Column.
	 *
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':
				return $this->render_title( $item );
			case 'id':
				return esc_html( (string) $item['id'] );
			case 'type':
				return esc_html( $this->type_label( $item['type'] ) );
			case 'quests':
				return $this->render_quests( $item['quests'] );
			default:
				return '';
		}
	}

	/**
	 * The post name with edit and view links, its status when not published,
	 * and the pages a synced pattern reaches.
	 *
	 * @param array<string, mixed> $item Usage entry.
	 *
	 * @return string
	 */
	private function render_title( array $item ): string {
		$title = '' !== $item['title'] ? $item['title'] : __( '(no title)', 'superquest' );
		$edit  = get_edit_post_link( $item['id'] );

		$out = $edit
			? '<strong><a href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a></strong>'
			: '<strong>' . esc_html( $title ) . '</strong>';

		if ( 'publish' !== $item['status'] ) {
			$out .= ' <span class="superquest-badge superquest-badge--status">' . esc_html( $item['status'] ) . '</span>';
		}

		$permalink = 'publish' === $item['status'] ? get_permalink( $item['id'] ) : '';
		if ( $permalink ) {
			$out .= '<div class="row-actions"><span class="view"><a href="' . esc_url( $permalink ) . '">'
				. esc_html__( 'View', 'superquest' ) . '</a></span></div>';
		}

		if ( ! empty( $item['hosts'] ) ) {
			$links = array();
			foreach ( $item['hosts'] as $host ) {
				$host_edit  = get_edit_post_link( $host->ID );
				$host_title = '' !== $host->post_title ? $host->post_title : __( '(no title)', 'superquest' );

				$links[] = $host_edit
					? '<a href="' . esc_url( $host_edit ) . '">' . esc_html( $host_title ) . '</a>'
					: esc_html( $host_title );
			}

			$out .= '<div class="superquest-usage-hosts">'
				. esc_html__( 'Used on:', 'superquest' ) . ' ' . implode( ', ', $links )
				. '</div>';
		}

		return $out;
	}

	/**
	 * One line per block on the post.
	 *
	 * @param array<int, array<string, string>> $quests Blocks found.
	 *
	 * @return string
	 */
	private function render_quests( array $quests ): string {
		$lines = array();

		foreach ( $quests as $quest ) {
			if ( '' === $quest['quest_id'] ) {
				$lines[] = '<em>' . esc_html__( 'No quest selected', 'superquest' ) . '</em>';
				continue;
			}

			// The title is only known for quests still in the fetched list.
			$lines[] = '' !== $quest['title']
				? esc_html( $quest['title'] )
				: '<code>' . esc_html( $quest['quest_id'] ) . '</code>';
		}

		return implode( '<br>', $lines );
	}

	/**
	 * A readable name for a post type.
	 *
	 * @param string $type Post type slug.
	 *
	 * @return string
	 */
	private function type_label( string $type ): string {
		if ( 'wp_block' === $type ) {
			return __( 'Synced pattern', 'superquest' );
		}

		$object = get_post_type_object( $type );

		return $object && isset( $object->labels->singular_name )
			? (string) $object->labels->singular_name
			: $type;
	}

	/**
	 * Message when the block is used nowhere.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No SuperQuest blocks found.', 'superquest' );
	}

	/**
	 * Loads the current page of results.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$result      = Scanner::scan( $this->get_pagenum() );
		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'total_pages' => $result['pages'],
				'per_page'    => Scanner::PER_PAGE,
			)
		);
	}
}
