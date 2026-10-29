<?php
/**
 * Uninstall cleanup: options, legacy transients, scheduled tasks.
 *
 * Self-contained: the plugin is not loaded during uninstall.
 *
 * @package Wycan\FreemiusStats
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'wfs_sync_all' );
wp_unschedule_hook( 'wfs_sync_product' );

foreach ( [ 'wycan_freemius_products', 'wycan_freemius_counts', 'wycan_freemius_stats_version' ] as $option ) {
	delete_option( $option );
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_freemius_count_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_freemius_count_' ) . '%'
	)
);

if ( wp_using_ext_object_cache() ) {
	wp_cache_flush();
}
