<?php
/**
 * Install count as seen by the front: last known value first, network last.
 *
 * @package Wycan\FreemiusStats\Counts
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Counts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Products\Repository;

final class InstallCounts {

	/** A failed foreground call is not retried before this delay. */
	public const FAILURE_WINDOW = 5 * MINUTE_IN_SECONDS;

	public function __construct(
		private readonly Repository $products,
		private readonly CountStore $store,
		private readonly Sync $sync,
	) {}

	/**
	 * @return int|false Same contract as 1.1.0: false when nothing can be displayed.
	 */
	public function get( string $slug, bool $active_only ): int|false {
		$product = $this->products->find( $slug );
		if ( null === $product || '' === $product['product_id'] ) {
			return false;
		}

		$known = $this->store->count( $product['product_id'], $active_only );
		if ( null !== $known ) {
			return $known;
		}

		if ( $this->store->failed_recently( $product['product_id'], CountStore::field( $active_only ), self::FAILURE_WINDOW ) ) {
			return false;
		}

		return $this->sync->count( $product, $active_only, Sync::TIMEOUT_FOREGROUND ) ?? false;
	}
}
