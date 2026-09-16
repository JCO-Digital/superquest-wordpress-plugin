<?php
/**
 * Popup screen.
 *
 * @package SuperQuest
 */

use SuperQuest\Admin\Menu;
use SuperQuest\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap superquest-wrap">
	<div class="superquest-page-header">
		<span class="superquest-logo" role="img" aria-label="SuperQuest"></span>
		<h1><?php esc_html_e( 'Popup', 'superquest' ); ?></h1>
	</div>

	<?php settings_errors(); ?>

	<div class="superquest-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
			<?php
			settings_fields( Options::GROUP_POPUP );
			do_settings_sections( Menu::POPUP_SLUG );
			submit_button();
			?>
		</form>
	</div>
</div>
