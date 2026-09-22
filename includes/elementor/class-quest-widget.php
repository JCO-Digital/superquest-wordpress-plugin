<?php
/**
 * The SuperQuest Elementor widget.
 *
 * @package SuperQuest
 */

namespace SuperQuest\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use SuperQuest\Admin\Menu;
use SuperQuest\Embed;
use SuperQuest\Loader;
use SuperQuest\Options;
use SuperQuest\Quests;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only ever reached from `elementor/widgets/register`, but a site that loads
// this file another way should not fatal on the missing parent.
if ( ! class_exists( Widget_Base::class ) ) {
	return;
}

/**
 * Places a quest from Elementor's panel.
 *
 * Inside this namespace a bare `Elementor\Foo` would resolve to
 * `SuperQuest\Elementor\Elementor\Foo`, so every Elementor class is imported
 * above rather than named inline.
 */
final class Quest_Widget extends Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return Integration::WIDGET_NAME;
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'SuperQuest', 'superquest' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-checkbox';
	}

	/**
	 * Panel categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Words that find the widget in the panel search.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'superquest', 'quest', 'quiz', 'poll', 'survey', 'gamification', 'learning' );
	}

	/**
	 * The panel controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'superquest_quest',
			array(
				'label' => esc_html__( 'Quest', 'superquest' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$notice = self::setup_notice();

		if ( '' !== $notice ) {
			$this->add_control(
				'superquest_notice',
				array(
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => $notice,
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				)
			);
		}

		$this->add_control(
			Integration::SETTING_QUEST_ID,
			array(
				'label'       => esc_html__( 'Quest', 'superquest' ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => self::quest_options(),
				'default'     => '',
				'multiple'    => false,
				'label_block' => true,
			)
		);

		// Elementor registers controls once per widget type, not per instance,
		// so a saved quest that has since left the list cannot be added to the
		// options the way the block's picker does it. Without somewhere to put
		// it the picker would show blank and saving would quietly drop the ID.
		$this->add_control(
			Integration::SETTING_QUEST_ID_MANUAL,
			array(
				'label'       => esc_html__( 'Quest ID', 'superquest' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => esc_html__( 'Use this when the quest is not in the list yet. It takes precedence over the picker.', 'superquest' ),
				'separator'   => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Prints the widget.
	 *
	 * @return void
	 */
	protected function render() {
		$quest_id = self::quest_id( $this->get_settings_for_display() );

		if ( Integration::is_editing() ) {
			self::render_placeholder( $quest_id );
			return;
		}

		if ( array() === Embed::attributes( $quest_id ) ) {
			return;
		}

		// Elementor renders during `the_content`, long after the head enqueue
		// has decided, so this is the footer fallback the block uses too.
		Loader::enqueue();

		Embed::render( $quest_id );
	}

	/**
	 * The quest to show: the manual ID when given, else the picker's.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 *
	 * @return string
	 */
	private static function quest_id( array $settings ): string {
		$manual = $settings[ Integration::SETTING_QUEST_ID_MANUAL ] ?? '';
		$manual = is_scalar( $manual ) ? trim( (string) $manual ) : '';

		if ( '' !== $manual ) {
			return $manual;
		}

		$picked = $settings[ Integration::SETTING_QUEST_ID ] ?? '';

		return is_scalar( $picked ) ? trim( (string) $picked ) : '';
	}

	/**
	 * The quest list as the picker's options, keyed by quest ID.
	 *
	 * Read straight from the stored list, so the panel needs no REST request.
	 *
	 * @return array<string, string>
	 */
	private static function quest_options(): array {
		$options = array();

		foreach ( Quests::items() as $quest ) {
			$options[ $quest['id'] ] = '' !== $quest['title'] ? $quest['title'] : $quest['id'];
		}

		return $options;
	}

	/**
	 * The warning shown above the picker when it cannot be useful yet.
	 *
	 * @return string Escaped HTML, or an empty string when all is well.
	 */
	private static function setup_notice(): string {
		$can_manage = current_user_can( 'manage_options' );
		$settings   = $can_manage ? Menu::url() : '';

		if ( '' === Options::organization_id() ) {
			$message = $can_manage
				? __( 'No SuperQuest organisation is set for this site.', 'superquest' )
				: __( 'Ask an administrator to set the Organisation ID in the SuperQuest settings.', 'superquest' );
		} elseif ( array() === Quests::items() ) {
			$message = '' !== Quests::message()
				? Quests::message()
				: __( 'No quests were found for the organisation.', 'superquest' );
		} else {
			return '';
		}

		$notice = '<p>' . esc_html( $message ) . '</p>';

		if ( '' !== $settings ) {
			$notice .= '<p><a href="' . esc_url( $settings ) . '" target="_blank" rel="noopener">'
				. esc_html__( 'Open SuperQuest settings', 'superquest' )
				. '</a></p>';
		}

		return $notice;
	}

	/**
	 * What the editor sees in place of the quest.
	 *
	 * The quest is rendered by the SuperQuest app and cannot be previewed, and
	 * letting it load inside the editor iframe would run the real thing every
	 * time a control changes. This stands in for it, the way the block's own
	 * placeholder does.
	 *
	 * @param string $quest_id Selected quest ID.
	 *
	 * @return void
	 */
	private static function render_placeholder( string $quest_id ): void {
		$titles = Quests::titles();
		$title  = $titles[ $quest_id ] ?? '';

		if ( '' === $quest_id ) {
			$heading = '<span class="superquest-placeholder__title superquest-placeholder__title--empty">'
				. esc_html__( 'No quest selected', 'superquest' ) . '</span>';
			$note    = esc_html__( 'Pick a quest in the panel.', 'superquest' );
		} elseif ( '' !== $title ) {
			$heading = '<span class="superquest-placeholder__title">' . esc_html( $title ) . '</span>';
			$note    = esc_html__( 'SuperQuest loads this quest when a visitor opens the page.', 'superquest' );
		} else {
			$heading = '<code class="superquest-placeholder__title">' . esc_html( $quest_id ) . '</code>';
			$note    = esc_html__( 'This quest is not in the fetched quest list. It still renders, but its title is unknown here.', 'superquest' );
		}

		$allowed = array(
			'span' => array( 'class' => array() ),
			'code' => array( 'class' => array() ),
		);

		printf(
			'<div class="superquest-placeholder"><span class="superquest-placeholder__brand">%1$s</span>%2$s<span class="superquest-placeholder__note">%3$s</span></div>',
			esc_html__( 'SuperQuest', 'superquest' ),
			wp_kses( $heading, $allowed ),
			esc_html( $note )
		);
	}
}
