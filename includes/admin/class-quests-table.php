<?php
/**
 * Table of the organisation's quests.
 *
 * @package SuperQuest
 */

namespace SuperQuest\Admin;

use SuperQuest\Quests;
use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( WP_List_Table::class ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists the cached quests with their IDs.
 */
class Quests_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'superquest-quest',
				'plural'   => 'superquest-quests',
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
			'title' => __( 'Title', 'superquest' ),
			'id'    => __( 'Quest ID', 'superquest' ),
		);
	}

	/**
	 * Cell content.
	 *
	 * @param array<string, string> $item        Quest.
	 * @param string                $column_name Column.
	 *
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':
				return '' !== $item['title']
					? '<strong>' . esc_html( $item['title'] ) . '</strong>'
					: '<em>' . esc_html__( '(no title)', 'superquest' ) . '</em>';
			case 'id':
				return '<code>' . esc_html( $item['id'] ) . '</code>';
			default:
				return '';
		}
	}

	/**
	 * Shows the API message and when the list was last fetched.
	 *
	 * @param string $which Top or bottom.
	 *
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$updated = Quests::updated();
		$message = Quests::message();

		echo '<div class="alignleft actions superquest-quests-meta">';
		if ( $updated > 0 ) {
			printf(
				/* translators: %s: human-readable time difference, e.g. "5 minutes". */
				esc_html__( 'Last fetched %s ago.', 'superquest' ),
				esc_html( human_time_diff( $updated ) )
			);
		}
		if ( '' !== $message ) {
			echo ' <span class="superquest-api-message">' . esc_html( $message ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * Message when there are no quests.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No quests fetched yet. Save an Organisation ID or refresh the list.', 'superquest' );
	}

	/**
	 * Loads the items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = Quests::items();
	}
}
