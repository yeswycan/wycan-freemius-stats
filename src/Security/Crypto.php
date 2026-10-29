<?php
/**
 * Symmetric encryption for the API tokens stored in the database.
 *
 * @package Wycan\FreemiusStats\Security
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * libsodium secretbox (native extension, or the sodium_compat polyfill bundled with WordPress).
 *
 * The key derives from the site security keys: a database dump alone does not reveal the
 * tokens. Rotating AUTH_KEY or SECURE_AUTH_KEY makes them unreadable (state "to re-enter").
 */
final class Crypto {

	public const PREFIX   = 'v1:';
	private const CONTEXT = 'wycan-freemius-stats|token|';

	public static function encrypt( string $plain ): string {
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, self::key() );
		return self::PREFIX . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function decrypt( string $payload ): ?string {
		if ( ! self::is_encrypted( $payload ) ) {
			return null;
		}

		$raw = base64_decode( substr( $payload, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}

		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		try {
			$plain = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );
		} catch ( \Throwable $e ) {
			return null;
		}

		return false === $plain ? null : $plain;
	}

	public static function is_encrypted( string $value ): bool {
		return str_starts_with( $value, self::PREFIX );
	}

	private static function key(): string {
		$material = ( defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? (string) SECURE_AUTH_KEY : '' );

		if ( '' === trim( $material ) || str_contains( $material, 'put your unique phrase here' ) ) {
			// wp_salt() generates and persists a random salt when wp-config.php has none.
			$material = wp_salt( 'secure_auth' );
		}

		return sodium_crypto_generichash( self::CONTEXT . $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
