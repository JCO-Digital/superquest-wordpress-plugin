<?php
/**
 * Popup quest list field on the Popup screen.
 *
 * The form carries no JavaScript: a blank entry is always appended, so filling
 * it in and saving adds a quest, and leaving it alone changes nothing because
 * the sanitiser drops entries without a quest. Rows are removed by ticking
 * their remove box.
 *
 * @package SuperQuest
 */

use SuperQuest\Options;
use SuperQuest\Quests;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$superquest_available = Quests::items();
$superquest_known_ids = array_column( $superquest_available, 'id' );
$superquest_entries   = Options::popup_quests();
$superquest_entries[] = array(
	'quest_id' => '',
	'enabled'  => true,
);
$superquest_last      = count( $superquest_entries ) - 1;

if ( array() === $superquest_available ) :
	?>
	<p class="description">
		<?php esc_html_e( 'No quests are available yet. Set an Organisation ID on the General screen first.', 'superquest' ); ?>
	</p>
	<?php
endif;

foreach ( $superquest_entries as $superquest_index => $superquest_entry ) :
	$superquest_is_new = $superquest_index === $superquest_last;
	$superquest_name   = Options::POPUP_QUESTS . '[' . $superquest_index . ']';
	$superquest_id     = 'superquest-popup-quest-' . $superquest_index;
	$superquest_stale  = ! $superquest_is_new && ! in_array( $superquest_entry['quest_id'], $superquest_known_ids, true );
	?>
	<fieldset class="superquest-quest-entry<?php echo $superquest_is_new ? ' superquest-quest-entry--new' : ''; ?>">
		<legend>
			<?php
			if ( $superquest_is_new ) {
				esc_html_e( 'Add a quest', 'superquest' );
			} else {
				/* translators: %d: position of the quest in the list. */
				echo esc_html( sprintf( __( 'Quest %d', 'superquest' ), $superquest_index + 1 ) );
			}
			?>
		</legend>

		<p>
			<label for="<?php echo esc_attr( $superquest_id . '-quest' ); ?>"><?php esc_html_e( 'Quest', 'superquest' ); ?></label><br>
			<select id="<?php echo esc_attr( $superquest_id . '-quest' ); ?>" name="<?php echo esc_attr( $superquest_name . '[quest_id]' ); ?>">
				<option value=""><?php esc_html_e( '— Select a quest —', 'superquest' ); ?></option>
				<?php foreach ( $superquest_available as $superquest_quest ) : ?>
					<option value="<?php echo esc_attr( $superquest_quest['id'] ); ?>" <?php selected( $superquest_entry['quest_id'], $superquest_quest['id'] ); ?>>
						<?php echo esc_html( '' !== $superquest_quest['title'] ? $superquest_quest['title'] : $superquest_quest['id'] ); ?>
					</option>
				<?php endforeach; ?>
				<?php if ( $superquest_stale ) : ?>
					<option value="<?php echo esc_attr( $superquest_entry['quest_id'] ); ?>" selected>
						<?php echo esc_html( $superquest_entry['quest_id'] ); ?>
					</option>
				<?php endif; ?>
			</select>
		</p>
		<?php if ( $superquest_stale ) : ?>
			<p class="description superquest-warning">
				<?php esc_html_e( 'This quest is no longer in the fetched quest list. Pick another one or remove the entry.', 'superquest' ); ?>
			</p>
		<?php endif; ?>

		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $superquest_name . '[enabled]' ); ?>" value="1" <?php checked( $superquest_entry['enabled'] ); ?>>
				<?php esc_html_e( 'Insert this quest above the footer', 'superquest' ); ?>
			</label>
		</p>

		<?php if ( ! $superquest_is_new ) : ?>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $superquest_name . '[remove]' ); ?>" value="1">
					<?php esc_html_e( 'Remove this quest when saving', 'superquest' ); ?>
				</label>
			</p>
		<?php endif; ?>
	</fieldset>
	<?php
endforeach;
