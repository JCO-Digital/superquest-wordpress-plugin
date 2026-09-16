<?php
/**
 * Removes everything the plugin stored when it is deleted.
 *
 * @package SuperQuest
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the plugin's options and transients on the current site.
 *
 * Names are spelled out rather than read from the classes so this file stands
 * alone, as uninstall.php runs without the plugin loaded.
 *
 * @return void
 */
function superquest_uninstall_site(): void {
	$options = array(
		'superquest_organization_id',
		'superquest_quests',
		'superquest_always_load',
		'superquest_consent_bypass',
		'superquest_popup_quests',
		'superquest_popup_excluded_ids',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	delete_transient( 'superquest_manifest' );
	delete_transient( 'superquest_refresh_lock' );

	// Refresh results are keyed per user and expire within a minute; sweep
	// any that are still around.
	foreach ( get_users( array( 'fields' => 'ID' ) ) as $user_id ) {
		delete_transient( 'superquest_refresh_result_' . $user_id );
	}
}

if ( is_multisite() ) {
	$superquest_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $superquest_site_ids as $superquest_site_id ) {
		switch_to_blog( (int) $superquest_site_id );
		superquest_uninstall_site();
		restore_current_blog();
	}
} else {
	superquest_uninstall_site();
}
