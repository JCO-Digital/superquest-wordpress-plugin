<?php
/**
 * Excluded pages field on the Popup screen.
 *
 * @package SuperQuest
 */

use SuperQuest\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$superquest_excluded = Options::popup_excluded_ids();
?>
<input type="text"
	class="regular-text code"
	id="<?php echo esc_attr( Options::POPUP_EXCLUDED_IDS ); ?>"
	name="<?php echo esc_attr( Options::POPUP_EXCLUDED_IDS ); ?>"
	value="<?php echo esc_attr( implode( ', ', $superquest_excluded ) ); ?>"
	placeholder="12, 34, 56">
<p class="description">
	<?php esc_html_e( 'Page or post IDs no popup quest is inserted on, separated by commas.', 'superquest' ); ?>
</p>
<?php if ( array() !== $superquest_excluded ) : ?>
	<ul class="superquest-excluded-list">
		<?php foreach ( $superquest_excluded as $superquest_excluded_id ) : ?>
			<?php $superquest_excluded_title = get_the_title( $superquest_excluded_id ); ?>
			<li>
				<code><?php echo esc_html( (string) $superquest_excluded_id ); ?></code>
				<?php if ( '' !== $superquest_excluded_title ) : ?>
					<?php echo esc_html( $superquest_excluded_title ); ?>
				<?php else : ?>
					<em><?php esc_html_e( 'not found', 'superquest' ); ?></em>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
