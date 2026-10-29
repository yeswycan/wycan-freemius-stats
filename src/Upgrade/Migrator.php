<?php
/**
 * Versioned, idempotent data migrations.
 *
 * @package Wycan\FreemiusStats\Upgrade
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Upgrade;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Products\Repository;

final class Migrator {

	public const OPTION = 'wycan_freemius_stats_version';

	public static function maybe_run(): void {
		$installed = get_option( self::OPTION, '' );
		$installed = is_string( $installed ) ? $installed : '';

		if ( WFS_VERSION === $installed ) {
			return;
		}

		if ( version_compare( $installed, '2.0.0', '<' ) ) {
			self::to_2_0_0();
		}

		update_option( self::OPTION, WFS_VERSION, true );
	}

	/**
	 * 1.1.0 to 2.0.0: tokens encrypted, rows cleaned and reindexed (order kept), corrupted
	 * option reset to a list, option taken out of autoload, legacy transients dropped.
	 * Running it twice changes nothing: already encrypted tokens are kept as they are.
	 */
	public static function to_2_0_0(): void {
		$raw   = get_option( Repository::OPTION, [] );
		$clean = Repository::sanitize( is_array( $raw ) ? $raw : [] );

		if ( $raw !== $clean ) {
			update_option( Repository::OPTION, $clean, false );
		}
		wp_set_option_autoload( Repository::OPTION, false );

		self::delete_legacy_transients();
	}

	private static function delete_legacy_transients(): void {
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
	}
}
