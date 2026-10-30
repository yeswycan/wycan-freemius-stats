<?php
/**
 * Product targeted by a shortcode from its attributes.
 *
 * @package Wycan\FreemiusStats\Front
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Products\Repository;

final class ShortcodeProduct {

	/**
	 * Same priority as [freemius_installs]: acf_field first, then slug.
	 *
	 * @param array<string, mixed> $atts
	 * @return array{slug: string, name: string, product_id: string, api_token: string}|string The product, or the reason it was not found.
	 */
	public static function resolve( Repository $products, array $atts ): array|string {
		if ( ! empty( $atts['acf_field'] ) ) {
			$slug = AcfSlug::read( (string) $atts['acf_field'] );
			if ( false === $slug ) {
				/* translators: %s: ACF field name */
				return sprintf( __( 'Unable to read the ACF field "%s".', 'wycan-freemius-stats' ), (string) $atts['acf_field'] );
			}
		} elseif ( ! empty( $atts['slug'] ) ) {
			$slug = (string) $atts['slug'];
		} else {
			return __( 'Specify either "slug" or "acf_field".', 'wycan-freemius-stats' );
		}

		$product = $products->find( $slug );
		if ( null === $product || '' === $product['product_id'] ) {
			/* translators: %s: product slug */
			return sprintf( __( 'Unknown product "%s".', 'wycan-freemius-stats' ), $slug );
		}

		return $product;
	}

	/**
	 * Visitors see nothing, users who can edit see the reason.
	 */
	public static function error( string $prefix, string $message ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<span class="freemius-error">' . esc_html( $prefix . ' ' . $message ) . '</span>';
	}
}
