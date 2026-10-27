<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://posimyth.com/
 * @since      2.0.0
 *
 * @package sticky-header-effects-for-elementor
 * @category Core
 * @author POSIMYTH
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all per-site options and transients created by the plugin.
 *
 * Run once on a single site, or once per blog on multisite (see below).
 *
 * @since 2.2.1
 */
function she_header_cleanup_site() {
	global $wpdb;

	// Options created by the plugin.
	$she_options = array(
		'she_rebranding_dismissed',
		'she_bfsale_notice_dismissed',
		'she_smsale_notice_dismissed',
		'she_menu_notificetions',
		'she_onboarding_setup',
		'she_design_from_scratch',
		'she_header_template',
		'wkit_onbording_end',
		'she_nexter_extension_notice',
		'she_header_install_time',
		'she_join_community_notice',
		'she_feature_status',
		'she_review_notice',
	);

	foreach ( $she_options as $she_option ) {
		delete_option( $she_option );
	}

	// Transients created by the plugin.
	delete_transient( 'she_header_template' );

	// Versioned rollback caches (she_rollback_version_*) — the version suffix
	// isn't known at uninstall time, so clean them up directly.
	$like_transient = $wpdb->esc_like( '_transient_she_rollback_version_' ) . '%';
	$like_timeout   = $wpdb->esc_like( '_transient_timeout_she_rollback_version_' ) . '%';

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$like_transient,
			$like_timeout
		)
	);
}

// On multisite, options/transients live per blog — clean every site.
if ( is_multisite() ) {
	$she_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $she_site_ids as $she_site_id ) {
		switch_to_blog( $she_site_id );
		she_header_cleanup_site();
		restore_current_blog();
	}
} else {
	she_header_cleanup_site();
}

// Per-user dismissal flags are stored in global user meta (shared across the
// whole network on multisite), so remove them once for every user.
delete_metadata( 'user', 0, 'she_pro_launch_notice_dismissed', '', true );
delete_metadata( 'user', 0, 'she_dismissed_notice_plugin', '', true );
delete_metadata( 'user', 0, 'she_pro_live_notice_dismissed', '', true );
delete_metadata( 'user', 0, 'she_sale_notice_dismissed', '', true );

/**
 * Remove everything the POSIMYTH Analytics SDK stored — including the sharing consent.
 *
 * Consent must not survive uninstalling. Left behind, the opt-in option stays in wp_options and
 * reinstalling silently resumes sending without ever asking again; someone who removes the plugin has
 * withdrawn from the arrangement, and reinstalling has to start from a clean slate.
 *
 * No sibling check, unlike Nexter Extension's and Nexter Blocks' uninstall scripts. Those two share
 * one consent under `nexter_suite`, so they must not clear it while the other is still installed.
 * Sticky Header Effects has its OWN key and its own suite (see she_posimyth_analytics_boot() in the
 * main plugin file) and is the only member, so there is nothing to preserve for anyone else.
 *
 * @since 2.2.1
 */
$she_sdk_base = __DIR__ . '/includes/posimyth-sdk/class-posimyth-tracker-base.php';
$she_tracker  = __DIR__ . '/includes/posimyth-sdk/class-posimyth-tracker-she.php';

if ( file_exists( $she_sdk_base ) && file_exists( $she_tracker ) ) {
	require_once $she_sdk_base;
	require_once $she_tracker;
}

// method_exists too, not only class_exists: an active POSIMYTH sibling loads before uninstall.php
// runs, so an OLDER copy of Posimyth_Tracker_Base may already be defined without purge_state() — our
// subclass then extends that copy, and calling the missing method would fatal mid-uninstall.
if ( class_exists( 'Posimyth_Tracker_SHE' ) && method_exists( 'Posimyth_Tracker_SHE', 'purge_state' ) ) {
	Posimyth_Tracker_SHE::purge_state( true, 'she_suite' );
} else {
	// Fall back to clearing by name, so a broken or partial install still cleans up after itself.
	wp_clear_scheduled_hook( 'posimyth_heartbeat_she' );

	delete_option( 'posimyth_she_install_time' );
	delete_option( 'posimyth_she_usage' );
	delete_option( 'posimyth_she_first_use_at' );
	delete_option( 'posimyth_she_activate_reported' );
	delete_transient( 'posimyth_she_deact_reported' );

	// Site options first (that is how they are written), then the legacy per-blog shape.
	delete_site_option( 'posimyth_she_share_analytics' );
	delete_site_option( 'posi_consent_dismissed_she_suite' );
	delete_site_option( 'posi_consent_snoozed_until_she_suite' );
	delete_site_option( 'posi_consent_grace_start_she_suite' );
	delete_option( 'posimyth_she_share_analytics' );
	delete_option( 'posi_consent_dismissed_she_suite' );
	delete_option( 'posi_consent_snoozed_until_she_suite' );
	delete_option( 'posi_consent_grace_start_she_suite' );
}

// Left behind by the SDK's cached user count and the heartbeat catch-up lock. Both are transients, so
// they expire on their own, but an uninstall should not leave rows for a plugin that is gone.
delete_transient( 'posimyth_she_user_count' );
delete_transient( 'posimyth_she_hb_catchup' );
