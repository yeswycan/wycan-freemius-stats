<?php
/**
 * Latest published version as seen by the front.
 *
 * @package Wycan\FreemiusStats\Counts
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Counts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Versions {

	public function __construct(
		private readonly CountStore $store,
		private readonly Sync $sync,
		private readonly Scheduler $scheduler,
	) {}

	/**
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 * @return array{version: string, date: string, requires: string, tested: string}|null
	 */
	public function get( array $product ): ?array {
		if ( '' === $product['product_id'] ) {
			return null;
		}

		$known = $this->store->version( $product['product_id'] );
		if ( null !== $known ) {
			$this->scheduler->refresh_if_stale( $product['product_id'], CountStore::FIELD_VERSION );
			return $known;
		}

		if ( $this->store->failed_recently( $product['product_id'], CountStore::FIELD_VERSION, InstallCounts::FAILURE_WINDOW ) ) {
			return null;
		}

		return $this->sync->version( $product, Sync::TIMEOUT_FOREGROUND );
	}
}
