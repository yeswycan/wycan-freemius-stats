<?php
/**
 * [freemius_version] shortcode (new in 2.0.0).
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
 * Attributes: slug, acf_field (priority, same rules as [freemius_installs]) and field
 * (version by default, date, requires, tested). Published versions only.
 * On error, visitors see nothing and users who can edit see the reason.
 */
final class VersionShortcode {

	public const FIELDS = [ 'version', 'date', 'requires', 'tested' ];

	public function __construct( private readonly Plugin $plugin ) {}

	public function register(): void {
		add_shortcode( 'freemius_version', [ $this, 'render' ] );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'slug'      => '',
				'acf_field' => '',
				'field'     => 'version',
			],
			$atts,
			'freemius_version'
		);

		if ( ! empty( $atts['acf_field'] ) ) {
			$slug = AcfSlug::read( (string) $atts['acf_field'] );
			if ( false === $slug ) {
				/* translators: %s: ACF field name */
				return $this->error( sprintf( __( 'Unable to read the ACF field "%s".', 'wycan-freemius-stats' ), (string) $atts['acf_field'] ) );
			}
		} elseif ( ! empty( $atts['slug'] ) ) {
			$slug = (string) $atts['slug'];
		} else {
			return $this->error( __( 'Specify either "slug" or "acf_field".', 'wycan-freemius-stats' ) );
		}

		$product = $this->plugin->products->find( $slug );
		if ( null === $product || '' === $product['product_id'] ) {
			/* translators: %s: product slug */
			return $this->error( sprintf( __( 'Unknown product "%s".', 'wycan-freemius-stats' ), $slug ) );
		}

		$release = $this->plugin->versions->get( $product );
		if ( null === $release ) {
			/* translators: %s: product slug */
			return $this->error( sprintf( __( 'No published version available for "%s".', 'wycan-freemius-stats' ), $slug ) );
		}

		$field = in_array( $atts['field'], self::FIELDS, true ) ? (string) $atts['field'] : 'version';
		$value = 'date' === $field ? self::format_date( $release['date'] ) : $release[ $field ];
		if ( '' === $value ) {
			/* translators: 1: field name, 2: product slug */
			return $this->error( sprintf( __( 'The "%1$s" field is empty for "%2$s".', 'wycan-freemius-stats' ), $field, $slug ) );
		}

		return '<span class="freemius-version">' . esc_html( $value ) . '</span>';
	}

	/**
	 * Freemius dates are UTC ("2026-03-06 12:00:00"); shown with the site date format and timezone.
	 */
	public static function format_date( string $created ): string {
		if ( '' === $created ) {
			return '';
		}
		$timestamp = strtotime( $created . ' UTC' );
		return false === $timestamp ? $created : (string) wp_date( (string) get_option( 'date_format' ), $timestamp );
	}

	private function error( string $message ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<span class="freemius-error">' . esc_html( __( 'Freemius version:', 'wycan-freemius-stats' ) . ' ' . $message ) . '</span>';
	}
}
