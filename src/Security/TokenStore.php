<?php
/**
 * Resolution of a product API token.
 *
 * @package Wycan\FreemiusStats\Security
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order: constant WFS_TOKEN_{product_id} in wp-config.php, then the encrypted `api_token`
 * field of the product. The token is never sent to the browser nor logged.
 */
final class TokenStore {

	public const STATE_CONSTANT   = 'constant';
	public const STATE_OPTION     = 'option';
	public const STATE_UNREADABLE = 'unreadable';
	public const STATE_NONE       = 'none';

	public static function constant_name( string $product_id ): string {
		return 'WFS_TOKEN_' . $product_id;
	}

	/**
	 * @param array{product_id: string, api_token: string} $product
	 */
	public static function state( array $product ): string {
		if ( null !== self::from_constant( $product['product_id'] ) ) {
			return self::STATE_CONSTANT;
		}
		if ( '' === $product['api_token'] ) {
			return self::STATE_NONE;
		}
		return null === Crypto::decrypt( $product['api_token'] ) ? self::STATE_UNREADABLE : self::STATE_OPTION;
	}

	/**
	 * @param array{product_id: string, api_token: string} $product
	 */
	public static function get( array $product ): ?string {
		$constant = self::from_constant( $product['product_id'] );
		if ( null !== $constant ) {
			return $constant;
		}
		if ( '' === $product['api_token'] ) {
			return null;
		}
		$token = Crypto::decrypt( $product['api_token'] );
		return null === $token || '' === $token ? null : $token;
	}

	/**
	 * Last four characters, the only hint shown in the settings screen.
	 *
	 * @param array{product_id: string, api_token: string} $product
	 */
	public static function hint( array $product ): string {
		$token = self::get( $product );
		return null === $token ? '' : substr( $token, -4 );
	}

	public static function seal( string $plain ): string {
		return Crypto::encrypt( trim( $plain ) );
	}

	public static function is_sealed( string $value ): bool {
		return Crypto::is_encrypted( $value );
	}

	private static function from_constant( string $product_id ): ?string {
		if ( '' === $product_id ) {
			return null;
		}
		$name = self::constant_name( $product_id );
		if ( ! defined( $name ) ) {
			return null;
		}
		$value = constant( $name );
		return is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;
	}
}
