<?php
/**
 * Bootstrap and 1.1.0 facade.
 *
 * @package Wycan\FreemiusStats
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Api\Client;
use Wycan\FreemiusStats\Counts\CountStore;
use Wycan\FreemiusStats\Counts\InstallCounts;
use Wycan\FreemiusStats\Counts\Sync;
use Wycan\FreemiusStats\Front\InstallCountWidget;
use Wycan\FreemiusStats\Front\Shortcode;
use Wycan\FreemiusStats\Products\Repository;
use Wycan\FreemiusStats\Upgrade\Migrator;

final class Plugin {

	private static ?Plugin $instance = null;

	public readonly Repository $products;
	public readonly CountStore $store;
	public readonly Client $client;
	public readonly Sync $sync;
	public readonly InstallCounts $counts;

	private function __construct() {
		$this->products = new Repository();
		$this->store    = new CountStore();
		$this->client   = new Client();
		$this->sync     = new Sync( $this->client, $this->store );
		$this->counts   = new InstallCounts( $this->products, $this->store, $this->sync );
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** 1.1.0 name, kept for templates. */
	public static function get_instance(): self {
		return self::instance();
	}

	public static function boot(): void {
		$plugin = self::instance();

		Migrator::maybe_run();

		add_action( 'init', [ $plugin, 'init' ] );
		add_action( 'elementor/widgets/register', [ $plugin, 'register_widget' ] );
	}

	public static function activate(): void {
		Migrator::maybe_run();
	}

	public static function deactivate(): void {
	}

	public function init(): void {
		load_plugin_textdomain( 'wycan-freemius-stats', false, dirname( WFS_BASENAME ) . '/languages' );
		$this->products->register_setting();
		( new Shortcode( $this ) )->register();
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public function register_widget( $widgets_manager ): void {
		if ( ! class_exists( 'Wycan_Freemius_Stats_Widget', false ) ) {
			class_alias( InstallCountWidget::class, 'Wycan_Freemius_Stats_Widget' );
		}
		$widgets_manager->register( new InstallCountWidget() );
	}

	/**
	 * 1.1.0 signature: untyped on purpose, templates may pass anything.
	 *
	 * @param mixed $product_slug
	 * @param mixed $active_only
	 * @return int|false
	 */
	public function get_install_count( $product_slug, $active_only = true ) {
		if ( ! is_scalar( $product_slug ) ) {
			return false;
		}
		return $this->counts->get( (string) $product_slug, (bool) $active_only );
	}
}
