<?php
/**
 * Latest published version and its changelog as seen by the front.
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
		return $this->serve(
			$product,
			CountStore::FIELD_VERSION,
			fn( string $id ) => $this->store->version( $id ),
			fn() => $this->sync->version( $product, Sync::TIMEOUT_FOREGROUND )
		);
	}

	/**
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 * @return array{version: string, html: string, truncated: bool, at: int}|null
	 */
	public function changelog( array $product ): ?array {
		return $this->serve(
			$product,
			CountStore::FIELD_CHANGELOG,
			fn( string $id ) => $this->store->changelog( $id ),
			fn() => $this->sync->changelog( $product, Sync::TIMEOUT_FOREGROUND )
		);
	}

	/**
	 * Last known value first. Freshness always follows the version: the changelog is only
	 * fetched again when a new release shows up.
	 *
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 */
	private function serve( array $product, string $field, callable $known, callable $fetch ): ?array {
		if ( '' === $product['product_id'] ) {
			return null;
		}

		$value = $known( $product['product_id'] );
		if ( null !== $value ) {
			$this->scheduler->refresh_if_stale( $product['product_id'], CountStore::FIELD_VERSION );
			return $value;
		}

		if ( $this->store->failed_recently( $product['product_id'], $field, InstallCounts::FAILURE_WINDOW ) ) {
			return null;
		}

		return $fetch();
	}
}
