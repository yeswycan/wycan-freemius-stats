<?php
/**
 * Product slug read from an ACF field of the current post.
 *
 * @package Wycan\FreemiusStats\Front
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AcfSlug {

	public static function available(): bool {
		return function_exists( 'get_field' );
	}

	/**
	 * @return string|false False when ACF is missing or the field is empty (1.1.0 contract).
	 */
	public static function read( string $field ): string|false {
		if ( ! self::available() ) {
			return false;
		}
		$value = get_field( $field );
		if ( empty( $value ) || ! is_scalar( $value ) ) {
			return false;
		}
		return (string) $value;
	}
}
