<?php
/**
 * SuperQuest box on Appearance → Menus.
 *
 * The markup follows core's post type boxes, so core's nav-menu.js adds the
 * checked quests to the menu as custom links.
 *
 * @package SuperQuest
 */

use SuperQuest\Admin\Menu;
use SuperQuest\Admin\Nav_Menu;
use SuperQuest\Options;
use SuperQuest\Quests;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Quests the API reports as inline would not open as a popup, so they are
// not offered. Quests of unknown mode stay in.
$superquest_entries = array_filter(
	Options::popup_quests(),
	static function ( array $entry ): bool {
		return ! Quests::is_inline( $entry['quest_id'] );
	}
);
$superquest_titles  = Quests::titles();

if ( array() === $superquest_entries ) :
	?>
	<p>
		<?php esc_html_e( 'No popup quests are set up yet.', 'superquest' ); ?>
		<a href="<?php echo esc_url( Menu::url( Menu::POPUP_SLUG ) ); ?>"><?php esc_html_e( 'Add one on the Popup screen.', 'superquest' ); ?></a>
	</p>
	<?php
	return;
endif;
?>
<div id="posttype-superquest" class="posttypediv">
	<div id="tabs-panel-superquest" class="tabs-panel tabs-panel-active">
		<ul id="superquest-checklist" class="categorychecklist form-no-clear">
			<?php
			$superquest_index = 0;
			foreach ( $superquest_entries as $superquest_entry ) :
				--$superquest_index;
				$superquest_id    = $superquest_entry['quest_id'];
				$superquest_title = isset( $superquest_titles[ $superquest_id ] ) && '' !== $superquest_titles[ $superquest_id ]
					? $superquest_titles[ $superquest_id ]
					: $superquest_id;
				$superquest_name  = 'menu-item[' . $superquest_index . ']';
				?>
				<li>
					<label class="menu-item-title">
						<input type="checkbox" class="menu-item-checkbox" name="<?php echo esc_attr( $superquest_name . '[menu-item-object-id]' ); ?>" value="<?php echo esc_attr( (string) $superquest_index ); ?>">
						<?php echo esc_html( $superquest_title ); ?>
						<?php if ( ! $superquest_entry['enabled'] ) : ?>
							<span class="description"><?php esc_html_e( '(not inserted on the site)', 'superquest' ); ?></span>
						<?php endif; ?>
					</label>
					<input type="hidden" class="menu-item-type" name="<?php echo esc_attr( $superquest_name . '[menu-item-type]' ); ?>" value="custom">
					<input type="hidden" class="menu-item-title" name="<?php echo esc_attr( $superquest_name . '[menu-item-title]' ); ?>" value="<?php echo esc_attr( $superquest_title ); ?>">
					<input type="hidden" class="menu-item-url" name="<?php echo esc_attr( $superquest_name . '[menu-item-url]' ); ?>" value="<?php echo esc_attr( Nav_Menu::quest_url( $superquest_id ) ); ?>">
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<p class="description">
		<?php esc_html_e( 'Menu items for a quest are only shown on pages where that popup quest is inserted.', 'superquest' ); ?>
	</p>

	<p class="button-controls wp-clearfix" data-items-type="posttype-superquest">
		<span class="list-controls hide-if-no-js">
			<input type="checkbox" id="superquest-select-all" class="select-all">
			<label for="superquest-select-all"><?php esc_html_e( 'Select All', 'superquest' ); ?></label>
		</span>
		<span class="add-to-menu">
			<input type="submit" class="button submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'superquest' ); ?>" name="add-post-type-menu-item" id="submit-posttype-superquest">
			<span class="spinner"></span>
		</span>
	</p>
</div>
