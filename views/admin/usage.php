<?php
/**
 * Usage screen.
 *
 * @package SuperQuest
 */

use SuperQuest\Admin\Menu;
use SuperQuest\Admin\Usage_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$superquest_table = new Usage_Table();
$superquest_table->prepare_items();
?>
<div class="wrap superquest-wrap">
	<div class="superquest-page-header">
		<span class="superquest-logo" role="img" aria-label="SuperQuest"></span>
		<h1><?php esc_html_e( 'Usage', 'superquest' ); ?></h1>
	</div>

	<div class="superquest-card">
		<p class="description">
			<?php esc_html_e( 'Every page, post, template and synced pattern whose content holds a SuperQuest block. The list is read from the database each time this screen opens.', 'superquest' ); ?>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to the Popup screen. */
				esc_html__( 'Popup quests are not listed: they are inserted site-wide and configured on the %s screen.', 'superquest' ),
				'<a href="' . esc_url( Menu::url( Menu::POPUP_SLUG ) ) . '">' . esc_html__( 'Popup', 'superquest' ) . '</a>'
			);
			?>
		</p>

		<?php $superquest_table->display(); ?>
	</div>
</div>
