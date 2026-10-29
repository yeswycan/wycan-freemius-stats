<?php
/**
 * Status of a configured product for the settings screen.
 *
 * @package Wycan\FreemiusStats\Admin
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Api\ApiResult;
use Wycan\FreemiusStats\Counts\CountStore;
use Wycan\FreemiusStats\Security\TokenStore;

final class ProductState {

	public const TONE_OK      = 'ok';
	public const TONE_NEUTRAL = 'neutral';
	public const TONE_WARNING = 'warning';
	public const TONE_ERROR   = 'error';

	/**
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 * @return array{label: string, tone: string, detail: string}
	 */
	public static function of( array $product, CountStore $store ): array {
		if ( '' === $product['product_id'] ) {
			return self::state( __( 'Incomplete', 'wycan-freemius-stats' ), self::TONE_WARNING, __( 'Enter the Freemius product ID.', 'wycan-freemius-stats' ) );
		}

		$token = TokenStore::state( $product );
		if ( TokenStore::STATE_NONE === $token ) {
			return self::state( __( 'Token missing', 'wycan-freemius-stats' ), self::TONE_WARNING, __( 'Paste the product API token.', 'wycan-freemius-stats' ) );
		}
		if ( TokenStore::STATE_UNREADABLE === $token ) {
			return self::state( __( 'Token to re-enter', 'wycan-freemius-stats' ), self::TONE_ERROR, __( 'The WordPress security keys have changed since the token was saved. Paste it again.', 'wycan-freemius-stats' ) );
		}

		$has_value = null !== $store->count( $product['product_id'], true ) || null !== $store->count( $product['product_id'], false );
		$error     = $store->error( $product['product_id'] );

		if ( null !== $error && '' !== $error['code'] ) {
			$when = $error['at'] > 0 ? sprintf(
				/* translators: %s: human readable delay */
				__( '%s ago', 'wycan-freemius-stats' ),
				human_time_diff( $error['at'] )
			) : '';
			$suffix = $has_value ? ' ' . __( 'The last known value is still displayed.', 'wycan-freemius-stats' ) : '';
			$detail = trim( $when . ( '' !== $error['message'] ? ': ' . $error['message'] : '' ) ) . $suffix;

			return match ( $error['code'] ) {
				ApiResult::ERROR_UNAUTHORIZED => self::state( __( 'Token refused', 'wycan-freemius-stats' ), self::TONE_ERROR, $detail ),
				ApiResult::ERROR_NOT_FOUND    => self::state( __( 'Product not found', 'wycan-freemius-stats' ), self::TONE_ERROR, $detail ),
				ApiResult::ERROR_INVALID      => self::state( __( 'Invalid response', 'wycan-freemius-stats' ), self::TONE_ERROR, $detail ),
				default                       => self::state( __( 'API unreachable', 'wycan-freemius-stats' ), $has_value ? self::TONE_WARNING : self::TONE_ERROR, $detail ),
			};
		}

		if ( $has_value ) {
			return self::state( __( 'Connected', 'wycan-freemius-stats' ), self::TONE_OK, '' );
		}

		return self::state( __( 'Never synchronized', 'wycan-freemius-stats' ), self::TONE_NEUTRAL, __( 'Save to run the first synchronization.', 'wycan-freemius-stats' ) );
	}

	/**
	 * @return array{label: string, tone: string, detail: string}
	 */
	private static function state( string $label, string $tone, string $detail ): array {
		return [ 'label' => $label, 'tone' => $tone, 'detail' => $detail ];
	}
}
