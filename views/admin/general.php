<?php
/**
 * General screen.
 *
 * @package SuperQuest
 */

use SuperQuest\Admin\Menu;
use SuperQuest\Admin\Quests_Table;
use SuperQuest\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$superquest_table = new Quests_Table();
$superquest_table->prepare_items();
?>
<div class="wrap superquest-wrap">
	<div class="superquest-page-header">
		<span class="superquest-logo" role="img" aria-label="SuperQuest"></span>
		<h1><?php esc_html_e( 'Settings', 'superquest' ); ?></h1>
	</div>

	<?php settings_errors(); ?>

	<div class="superquest-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
			<?php
			settings_fields( Options::GROUP_GENERAL );
			do_settings_sections( Menu::SLUG );
			submit_button();
			?>
		</form>
	</div>

	<div class="superquest-card">
		<div class="superquest-card-header">
			<h2><?php esc_html_e( 'Organisation quests', 'superquest' ); ?></h2>
			<?php if ( '' !== Options::organization_id() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( Menu::REFRESH_ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( Menu::REFRESH_ACTION ); ?>">
					<?php submit_button( __( 'Refresh quests', 'superquest' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php $superquest_table->display(); ?>
	</div>
</div>
