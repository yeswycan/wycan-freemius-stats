<?php
/**
 * [freemius_installs] shortcode.
 *
 * @package Wycan\FreemiusStats\Front
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Plugin;

/**
 * HTML ported byte for byte from 1.1.0. The public strings are deliberately emitted without
 * a translation function: 1.1.0 shipped no compiled translation, so the pages always showed
 * these English texts, and the front output is frozen.
 */
final class Shortcode {

	public function __construct( private readonly Plugin $plugin ) {}

	public function register(): void {
		add_shortcode( 'freemius_installs', [ $this, 'render' ] );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'slug'        => '',
				'acf_field'   => '',
				'format'      => 'number',
				'active_only' => 'true',
			],
			$atts
		);

		if ( ! empty( $atts['acf_field'] ) ) {
			$product_slug = AcfSlug::read( (string) $atts['acf_field'] );
			if ( false === $product_slug ) {
				return '<span class="freemius-error">' . sprintf(
					esc_html( 'Error: Unable to retrieve ACF field "%s"' ),
					esc_html( (string) $atts['acf_field'] )
				) . '</span>';
			}
		} elseif ( ! empty( $atts['slug'] ) ) {
			$product_slug = (string) $atts['slug'];
		} else {
			return '<span class="freemius-error">' . esc_html( 'Error: You must specify either "slug" or "acf_field"' ) . '</span>';
		}

		$count = $this->plugin->get_install_count( $product_slug, 'true' === $atts['active_only'] );

		if ( false === $count ) {
			return '<span class="freemius-error">' . sprintf(
				esc_html( 'Error: Unable to retrieve data for "%s"' ),
				esc_html( $product_slug )
			) . '</span>';
		}

		return '<span class="freemius-install-count">' . self::format( $count, 'text' === $atts['format'] ) . '</span>';
	}

	/**
	 * Shared with the widget: "1 234" or "1 234 active installations".
	 */
	public static function format( int $count, bool $text ): string {
		if ( ! $text ) {
			return number_format_i18n( $count );
		}
		return sprintf( 1 === $count ? '%s active installation' : '%s active installations', number_format_i18n( $count ) );
	}
}
