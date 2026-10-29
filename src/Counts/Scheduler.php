<?php
/**
 * Background synchronization: hourly task, single events, sync on save.
 *
 * @package Wycan\FreemiusStats\Counts
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Counts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Products\Repository;

final class Scheduler {

	public const HOOK_ALL     = 'wfs_sync_all';
	public const HOOK_PRODUCT = 'wfs_sync_product';

	/** A value older than this is served as is, and refreshed in the background. */
	public const MAX_AGE = HOUR_IN_SECONDS;

	public function __construct(
		private readonly Repository $products,
		private readonly Sync $sync,
		private readonly CountStore $store,
	) {}

	public function register(): void {
		add_action( self::HOOK_ALL, [ $this, 'sync_all' ] );
		add_action( self::HOOK_PRODUCT, [ $this, 'sync_product_id' ] );
		add_action( 'update_option_' . Repository::OPTION, [ $this, 'on_products_saved' ], 10, 2 );
		add_action( 'add_option_' . Repository::OPTION, fn( string $option, mixed $value ) => $this->on_products_saved( [], $value ), 10, 2 );
		add_action( 'admin_init', [ $this, 'ensure_scheduled' ] );
	}

	public function ensure_scheduled(): void {
		if ( false === wp_next_scheduled( self::HOOK_ALL ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK_ALL );
		}
	}

	public function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK_ALL );
		wp_unschedule_hook( self::HOOK_PRODUCT );
	}

	/**
	 * Queues a background refresh of one product (deduplicated).
	 */
	public function request( string $product_id ): void {
		if ( false === wp_next_scheduled( self::HOOK_PRODUCT, [ $product_id ] ) ) {
			wp_schedule_single_event( time(), self::HOOK_PRODUCT, [ $product_id ] );
		}
	}

	public function refresh_if_stale( string $product_id, string $field ): void {
		if ( $this->store->synced_at( $product_id, $field ) < time() - self::MAX_AGE ) {
			$this->request( $product_id );
		}
	}

	public function sync_all(): void {
		foreach ( $this->products->all() as $product ) {
			if ( '' !== $product['product_id'] ) {
				$this->sync->product( $product );
			}
		}
	}

	public function sync_product_id( string $product_id ): void {
		foreach ( $this->products->all() as $product ) {
			if ( $product['product_id'] === $product_id ) {
				$this->sync->product( $product );
				return;
			}
		}
	}

	/**
	 * Saving the products: values of removed products are dropped, new or re-tokened products
	 * are synchronized right away so the settings screen shows their state after the redirect.
	 *
	 * @param mixed $old
	 * @param mixed $new
	 */
	public function on_products_saved( mixed $old, mixed $new ): void {
		$this->products->flush();
		$before = [];
		foreach ( Repository::normalize( $old ) as $product ) {
			$before[ $product['product_id'] ] = $product;
		}
		$after = Repository::normalize( $new );

		$this->store->prune( array_values( array_filter( array_column( $after, 'product_id' ) ) ) );

		foreach ( $after as $product ) {
			if ( '' === $product['product_id'] ) {
				continue;
			}
			$previous = $before[ $product['product_id'] ] ?? null;
			if ( null === $previous || $previous['api_token'] !== $product['api_token'] ) {
				$this->sync->product( $product, Sync::TIMEOUT_FOREGROUND );
			}
		}
	}
}
