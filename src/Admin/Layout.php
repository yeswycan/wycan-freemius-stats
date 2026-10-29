<?php
/**
 * Wycan admin layout (dark sidebar, content card), prefix wfs.
 *
 * @package Wycan\FreemiusStats\Admin
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Same frame as the other Wycan plugins (WyProjection Admin\Layout). Styles: assets/admin/admin.css,
 * scoped under .wfs-admin.
 */
final class Layout {

	private const ICONS = [
		'products' => '<path d="M3 7l9-4 9 4-9 4-9-4zM3 7v10l9 4 9-4V7M12 11v10"/>',
		'usage'    => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
	];

	public static function open( string $current, string $title, string $subtitle = '' ): void {
		echo '<div class="wrap wfs-admin"><div class="wfs-layout">';
		self::sidebar( $current );
		echo '<div class="wfs-content">';
		echo '<h1 class="wfs-section-title">' . esc_html( $title ) . '</h1>';
		if ( '' !== $subtitle ) {
			echo '<p class="wfs-section-subtitle">' . esc_html( $subtitle ) . '</p>';
		}
		echo '<div class="wfs-section-divider"></div>';
		echo '<hr class="wp-header-end">';
	}

	public static function close(): void {
		echo '</div></div></div>';
	}

	/**
	 * @return array<string, array{label: string, icon: string}>
	 */
	public static function tabs(): array {
		return [
			'products' => [ 'label' => __( 'Products', 'wycan-freemius-stats' ), 'icon' => 'products' ],
			'usage'    => [ 'label' => __( 'Usage', 'wycan-freemius-stats' ), 'icon' => 'usage' ],
		];
	}

	private static function sidebar( string $current ): void {
		echo '<aside class="wfs-sidebar">';
		echo '<div class="wfs-sidebar-brand">';
		printf(
			'<a href="https://wycan.fr" target="_blank" rel="noopener noreferrer" class="wfs-sidebar-logo-link" aria-label="%s">%s</a>',
			esc_attr__( 'Wycan (opens in a new tab)', 'wycan-freemius-stats' ),
			self::logo() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		);
		echo '<span class="wfs-sidebar-brand-name">Freemius Stats</span>';
		echo '<span class="wfs-version-pill">v' . esc_html( WFS_VERSION ) . '</span>';
		echo '</div>';

		printf( '<nav class="wfs-sidebar-nav" aria-label="%s">', esc_attr__( 'Plugin navigation', 'wycan-freemius-stats' ) );
		foreach ( self::tabs() as $key => $tab ) {
			$active = $key === $current;
			printf(
				'<a href="%s" class="wfs-nav-item%s"%s>%s%s</a>',
				esc_url( Admin::url( $key ) ),
				$active ? ' is-active' : '',
				$active ? ' aria-current="page"' : '',
				self::icon( $tab['icon'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
				esc_html( $tab['label'] )
			);
		}
		echo '</nav></aside>';
	}

	private static function icon( string $name ): string {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( self::ICONS[ $name ] ?? '' ) . '</svg>';
	}

	private static function logo(): string {
		return '<svg class="wfs-sidebar-logo" viewBox="0 0 354 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
			. '<path fill="#FF6B35" d="M89.906 45c0 24.712-20.063 44.776-44.777 44.776C20.417 89.776.353 69.712.353 45 .353 20.288 20.418.224 45.129.224 69.843.224 89.906 20.288 89.906 45Zm-3.291 0c0-22.896-18.59-41.485-41.486-41.485C22.233 3.515 3.645 22.104 3.645 45s18.588 41.485 41.483 41.485c22.895 0 41.487-18.589 41.487-41.485ZM57.254 32.539l-1.87-6.451c4.47-2.674 9.637-.61 12.491 5.01.434.753.768 1.61.976 2.532.921 4.023-.615 8.36-3.414 9.683-2.8 1.324-5.817-.882-6.738-4.906-.434-1.973-.886-3.945-1.445-5.868Zm187.969 36.946c-5.114 0-9.354-.972-12.722-2.914-3.368-1.942-5.888-4.67-7.559-8.186-1.672-3.515-2.508-7.633-2.508-12.353 0-5.162.787-9.661 2.36-13.496 1.573-3.836 4.032-6.798 7.375-8.887 3.343-2.09 7.67-3.134 12.98-3.134 2.754 0 5.249.196 7.486.589 2.237.394 4.339.935 6.306 1.623v5.31c-1.967-.737-4.057-1.315-6.27-1.734-2.212-.417-4.548-.626-7.006-.626-4.277 0-7.67.811-10.178 2.434-2.507 1.622-4.301 3.958-5.383 7.006-1.082 3.049-1.624 6.687-1.624 10.915 0 5.852 1.39 10.362 4.169 13.534 2.777 3.17 7.165 4.756 13.164 4.756 2.213 0 4.463-.172 6.747-.516 2.287-.344 4.414-.885 6.381-1.623v5.385c-1.919.589-3.995 1.057-6.232 1.401-2.237.344-4.733.516-7.486.516Zm18.143-.738 18.069-47.495h7.08l17.994 47.495h-6.194L295.3 55.694h-20.651l-5.015 13.053h-6.268ZM275.682 50.9h18.511l-9.291-24.411-9.22 24.411Zm39.678 17.847-.001-47.495h5.457l27.067 38.202V21.252h6.048v47.495h-5.532l-26.992-38.128v38.128h-6.047Z"/>'
			. '<path fill="#F2EFEA" d="m131.498 68.746-11.063-47.494h5.974l8.924 39.088 10.252-33.852v-5.236h4.941L161.59 60.34l9.071-39.088h5.9l-10.989 47.495h-6.416L148.83 32.61l-10.842 36.137h-6.49Zm66.82.001V51.048l-16.964-29.796h6.268l13.719 24.633 13.717-24.633h6.27l-16.963 29.796v17.7h-6.048ZM19.537 26.088c5.222-3.193 11.215.441 13.395 8.126l9.795 34.62c-5.224 3.193-11.216-.442-13.396-8.126l-9.795-34.62Zm17.867 0c5.018-3.193 10.774.441 12.867 8.126l9.41 34.62c-5.02 3.193-10.774-.442-12.868-8.126l-9.409-34.62Z"/>'
			. '</svg>';
	}
}
