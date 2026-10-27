<?php
/**
 * Remove all plugin data on uninstall.
 *
 * @package NextialFormEvents
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nextial_fe_subscriptions" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nextial_fe_deliveries" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nextial_fe_tokens" ); // phpcs:ignore WordPress.DB

foreach ( array( 'nextial_fe_access_key', 'nextial_fe_db_version', 'nextial_fe_test_receiver', 'nextial_fe_receiver_log', 'nextial_fe_oauth_clients', 'nextial_fe_cimd_store', 'nextial_fe_oauth_log', 'nextial_fe_disabled_forms', 'nextial_fe_excluded_fields', 'nextial_fe_recent_submissions' ) as $nextial_fe_option ) {
	delete_option( $nextial_fe_option );
}
wp_clear_scheduled_hook( 'nextial_fe_cleanup' );
wp_unschedule_hook( 'nextial_fe_deliver' ); // All argument variants.

// Transients (authorization codes, client metadata cache, notices, rate limits).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_nextial\\_fe\\_%' OR option_name LIKE '\\_transient\\_timeout\\_nextial\\_fe\\_%'" ); // phpcs:ignore WordPress.DB

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'nextial_fe_deliver' );
}
