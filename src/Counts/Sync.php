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

use Wycan\FreemiusStats\Api\ApiResult;
use Wycan\FreemiusStats\Api\Client;
use Wycan\FreemiusStats\Security\TokenStore;

final class Sync {

	/** First display without any value: the visitor waits, briefly. */
	public const TIMEOUT_FOREGROUND = 5;
	public const TIMEOUT_BACKGROUND = 15;

	public const CHANGELOG_MAX_BYTES = 200 * KB_IN_BYTES;

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
	 * Changelog of the latest published version, sanitized, capped and stored on success.
	 * The timeout covers every call made here, install lookup included.
	 *
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 * @return array{version: string, html: string, truncated: bool, at: int}|null
	 */
	public function changelog( array $product, int $timeout ): ?array {
		$token = TokenStore::get( $product );
		if ( null === $token || '' === $product['product_id'] ) {
			return null;
		}

		$result = $this->fetch_changelog( $product['product_id'], $token, microtime( true ) + $timeout );
		if ( $result->ok ) {
			$fitted = self::fit( wp_kses_post( $result->data['changelog'] ) );
			if ( null === $fitted ) {
				$result = ApiResult::failure( ApiResult::ERROR_INVALID, 'The latest changelog entry alone exceeds the size limit.', $result->status );
			}
		}
		if ( ! $result->ok ) {
			$this->store->record_failure( $product['product_id'], CountStore::FIELD_CHANGELOG, $result );
			return null;
		}

		$this->store->set_changelog( $product['product_id'], $result->data['version'], $fitted['html'], $fitted['truncated'] );
		return $this->store->changelog( $product['product_id'] );
	}

	/**
	 * Every value of a product: both counts, the latest published version and, when that version
	 * changed or was never fetched, its changelog.
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

		$version = $this->version( $product, $timeout );
		$ok      = null !== $version && $ok;

		// A changelog failure is reported apart: the counts and the version stay valid.
		if ( null !== $version && $version['version'] !== ( $this->store->changelog( $product['product_id'] )['version'] ?? null ) ) {
			$this->changelog( $product, $timeout );
		}

		if ( $ok ) {
			$this->store->clear_error( $product['product_id'] );
		}
		return $ok;
	}

	private function fetch_changelog( string $product_id, string $token, float $deadline ): ApiResult {
		$install_id = $this->store->install_id( $product_id );
		$resolved   = false;

		while ( true ) {
			if ( null === $install_id ) {
				$found = $this->client->first_install_id( $product_id, $token, self::remaining( $deadline ) );
				if ( ! $found->ok ) {
					return $found;
				}
				$install_id = $found->data['install_id'];
				$resolved   = true;
				$this->store->set_install_id( $product_id, $install_id );
			}

			$result = $this->client->latest_changelog( $product_id, $install_id, $token, self::remaining( $deadline ) );
			// A deleted install answers 404: another one is looked up, once.
			if ( $result->ok || $resolved || ApiResult::ERROR_NOT_FOUND !== $result->error ) {
				return $result;
			}
			$install_id = null;
		}
	}

	private static function remaining( float $deadline ): int {
		return max( 1, (int) ceil( $deadline - microtime( true ) ) );
	}

	/**
	 * Freemius renders each version heading of the readme as an h4: the cut happens right before
	 * the first heading that would cross the limit, so every kept entry is whole.
	 *
	 * @return array{html: string, truncated: bool}|null
	 */
	private static function fit( string $html ): ?array {
		$html = trim( $html );
		if ( strlen( $html ) <= self::CHANGELOG_MAX_BYTES ) {
			return [ 'html' => $html, 'truncated' => false ];
		}

		preg_match_all( '/<h4[\s>]/i', $html, $headings, PREG_OFFSET_CAPTURE );
		$cut = 0;
		foreach ( $headings[0] as [ , $offset ] ) {
			if ( $offset > self::CHANGELOG_MAX_BYTES ) {
				break;
			}
			$cut = $offset;
		}

		return $cut > 0 ? [ 'html' => rtrim( substr( $html, 0, $cut ) ), 'truncated' => true ] : null;
	}
}
