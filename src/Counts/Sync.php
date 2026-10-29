<?php
/**
 * Synchronization of one product with Freemius.
 *
 * @package Wycan\FreemiusStats\Counts
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Counts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Api\Client;
use Wycan\FreemiusStats\Security\TokenStore;

final class Sync {

	/** First display without any value: the visitor waits, briefly. */
	public const TIMEOUT_FOREGROUND = 5;
	public const TIMEOUT_BACKGROUND = 15;

	public function __construct(
		private readonly Client $client,
		private readonly CountStore $store,
	) {}

	/**
	 * One count, stored on success.
	 *
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 */
	public function count( array $product, bool $active_only, int $timeout ): ?int {
		$token = TokenStore::get( $product );
		if ( null === $token || '' === $product['product_id'] ) {
			return null;
		}

		$result = $this->client->installs_count( $product['product_id'], $token, $active_only, $timeout );
		if ( ! $result->ok ) {
			$this->store->record_failure( $product['product_id'], CountStore::field( $active_only ), $result );
			return null;
		}

		$this->store->set_count( $product['product_id'], $active_only, (int) $result->data['count'] );
		return (int) $result->data['count'];
	}

	/**
	 * Latest published version, stored on success.
	 *
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 * @return array{version: string, date: string, requires: string, tested: string}|null
	 */
	public function version( array $product, int $timeout ): ?array {
		$token = TokenStore::get( $product );
		if ( null === $token || '' === $product['product_id'] ) {
			return null;
		}

		$release = $this->client->latest_release( $product['product_id'], $token, $timeout );
		if ( ! $release->ok ) {
			$this->store->record_failure( $product['product_id'], CountStore::FIELD_VERSION, $release );
			return null;
		}

		$this->store->set_version( $product['product_id'], $release->data );
		return $release->data;
	}

	/**
	 * Every value of a product: both counts and the latest published version.
	 *
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 * @return bool Whether everything succeeded.
	 */
	public function product( array $product, int $timeout = self::TIMEOUT_BACKGROUND ): bool {
		$token = TokenStore::get( $product );
		if ( null === $token || '' === $product['product_id'] ) {
			return false;
		}

		$ok = null !== $this->count( $product, true, $timeout );
		$ok = null !== $this->count( $product, false, $timeout ) && $ok;

		$ok = null !== $this->version( $product, $timeout ) && $ok;

		if ( $ok ) {
			$this->store->clear_error( $product['product_id'] );
		}
		return $ok;
	}
}
