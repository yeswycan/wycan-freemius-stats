<?php
/**
 * Settings screen registration and assets.
 *
 * @package Wycan\FreemiusStats\Admin
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Plugin;

final class Admin {

	public const SLUG = 'wycan-freemius-stats';
	public const HOOK = 'settings_page_wycan-freemius-stats';

	public function __construct( private readonly Plugin $plugin ) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		( new ConnectionTest( $this->plugin ) )->register();
	}

	public static function url( string $tab = 'products' ): string {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );
		return 'products' === $tab ? $url : add_query_arg( 'tab', $tab, $url );
	}

	public static function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'products';
		return array_key_exists( $tab, Layout::tabs() ) ? $tab : 'products';
	}

	public function add_menu(): void {
		add_options_page(
			__( 'Freemius Stats', 'wycan-freemius-stats' ),
			__( 'Freemius Stats', 'wycan-freemius-stats' ),
			'manage_options',
			self::SLUG,
			[ new SettingsPage( $this->plugin ), 'render' ]
		);
	}

	public function enqueue( string $hook ): void {
		if ( self::HOOK !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wfs-admin', WFS_URL . 'assets/admin/admin.css', [], WFS_VERSION );
		wp_enqueue_script( 'wfs-admin', WFS_URL . 'assets/admin/admin.js', [], WFS_VERSION, true );
		wp_add_inline_script( 'wfs-admin', 'window.WFS_ADMIN = ' . wp_json_encode( $this->script_config() ) . ';', 'before' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function script_config(): array {
		return [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => ConnectionTest::ACTION,
			'nonce'   => wp_create_nonce( ConnectionTest::ACTION ),
			'i18n'    => [
				'testing'        => __( 'Testing…', 'wycan-freemius-stats' ),
				'networkError'   => __( 'The request could not be sent.', 'wycan-freemius-stats' ),
				'confirmRemove'  => __( 'Remove this product? Its counters disappear from the pages that use it until you save.', 'wycan-freemius-stats' ),
				'confirmDefault' => __( 'This product is the default of the Elementor widgets: widgets set to "Fixed product" without an explicit choice will switch to the next product. Remove it anyway?', 'wycan-freemius-stats' ),
				'newProduct'     => __( 'New product', 'wycan-freemius-stats' ),
				'copied'         => __( 'Copied', 'wycan-freemius-stats' ),
				'copy'           => __( 'Copy', 'wycan-freemius-stats' ),
				'edit'           => __( 'Edit', 'wycan-freemius-stats' ),
				'close'          => __( 'Close', 'wycan-freemius-stats' ),
				'resultSummary'  => /* translators: 1: active installs, 2: total installs */ __( '%1$s active installations, %2$s in total.', 'wycan-freemius-stats' ),
			],
		];
	}
}
