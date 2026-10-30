<?php
/**
 * [freemius_changelog] shortcode (new in 2.1.0).
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
 * Attributes: slug and acf_field (priority, same rules as [freemius_installs]).
 * Full changelog of the latest published version, as returned by Freemius.
 * On error, visitors see nothing and users who can edit see the reason.
 */
final class ChangelogShortcode {

	public function __construct( private readonly Plugin $plugin ) {}

	public function register(): void {
		add_shortcode( 'freemius_changelog', [ $this, 'render' ] );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'slug'      => '',
				'acf_field' => '',
			],
			$atts,
			'freemius_changelog'
		);

		$product = ShortcodeProduct::resolve( $this->plugin->products, $atts );
		if ( is_string( $product ) ) {
			return $this->error( $product );
		}

		$changelog = $this->plugin->versions->changelog( $product );
		if ( null === $changelog ) {
			/* translators: %s: product slug */
			return $this->error( sprintf( __( 'No changelog available for "%s".', 'wycan-freemius-stats' ), $product['slug'] ) );
		}

		return '<div class="freemius-changelog">' . wp_kses_post( $changelog['html'] ) . '</div>';
	}

	private function error( string $message ): string {
		return ShortcodeProduct::error( __( 'Freemius changelog:', 'wycan-freemius-stats' ), $message );
	}
}
