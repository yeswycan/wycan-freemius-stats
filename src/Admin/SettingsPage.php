<?php
/**
 * Settings screen: products and usage.
 *
 * @package Wycan\FreemiusStats\Admin
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Counts\CountStore;
use Wycan\FreemiusStats\Front\VersionShortcode;
use Wycan\FreemiusStats\Plugin;
use Wycan\FreemiusStats\Products\Repository;
use Wycan\FreemiusStats\Security\TokenStore;

/**
 * One form posted to options.php (Settings API). The product row is rendered once: for each
 * saved product, and once more inside a <template> with the __INDEX__ placeholder that the
 * script replaces. The Settings API notices are printed by WordPress itself (options-head.php).
 */
final class SettingsPage {

	private const INDEX_PLACEHOLDER = '__INDEX__';

	public function __construct( private readonly Plugin $plugin ) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'wycan-freemius-stats' ), 403 );
		}

		$tab = Admin::current_tab();
		if ( 'usage' === $tab ) {
			Layout::open( 'usage', __( 'Usage', 'wycan-freemius-stats' ), __( 'Shortcodes, Elementor widget and PHP function, ready to copy.', 'wycan-freemius-stats' ) );
			$this->usage();
		} else {
			Layout::open( 'products', __( 'Products', 'wycan-freemius-stats' ), __( 'Freemius products whose installations and versions are displayed on the site.', 'wycan-freemius-stats' ) );
			$this->products();
		}
		Layout::close();
	}

	// -------------------------------------------------------------------------
	// Products
	// -------------------------------------------------------------------------

	private function products(): void {
		$products = $this->plugin->products->all();

		echo '<form method="post" action="options.php" class="wfs-form" autocomplete="off" data-wfs-form>';
		settings_fields( Repository::GROUP );

		echo '<div class="wfs-toolbar">';
		echo '<p class="wfs-toolbar-text">' . esc_html__( 'Each product needs its Freemius ID and the API token of that product. The connection test checks both before saving and fills in the name and the slug.', 'wycan-freemius-stats' ) . '</p>';
		printf(
			'<button type="button" class="button" data-wfs-add data-next-index="%d">%s</button>',
			count( $products ),
			esc_html__( 'Add a product', 'wycan-freemius-stats' )
		);
		echo '</div>';

		printf(
			'<p class="wfs-empty" data-wfs-empty%s>%s</p>',
			[] === $products ? '' : ' hidden',
			esc_html__( 'No product yet. Add one, test the connection, then save.', 'wycan-freemius-stats' )
		);

		echo '<div class="wfs-products" data-wfs-products>';
		foreach ( $products as $index => $product ) {
			$this->card( (string) $index, $product, 0 === $index, false );
		}
		echo '</div>';

		echo '<template data-wfs-template>';
		$this->card( self::INDEX_PLACEHOLDER, [ 'slug' => '', 'name' => '', 'product_id' => '', 'api_token' => '' ], false, true );
		echo '</template>';

		echo '<div class="wfs-save-bar">';
		submit_button( __( 'Save the products', 'wycan-freemius-stats' ), 'primary', 'submit', false );
		echo '<span class="wfs-save-hint" data-wfs-unsaved hidden>' . esc_html__( 'Changes not saved yet.', 'wycan-freemius-stats' ) . '</span>';
		echo '</div>';
		echo '</form>';
	}

	/**
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 */
	private function card( string $index, array $product, bool $is_default, bool $is_new ): void {
		$name  = static fn( string $key ): string => Repository::OPTION . '[' . $index . '][' . $key . ']';
		$id    = static fn( string $key ): string => 'wfs-' . $index . '-' . $key;
		$store = $this->plugin->store;
		$state = $is_new ? null : ProductState::of( $product, $store );

		printf( '<article class="wfs-product%s" data-wfs-product>', $is_new ? ' is-open is-new' : '' );
		printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name( 'stored_slug' ) ), esc_attr( $product['slug'] ) );

		echo '<header class="wfs-product-head">';
		echo '<div class="wfs-product-identity">';
		printf(
			'<h2 class="wfs-product-name" data-wfs-name-label>%s</h2>',
			esc_html( '' !== $product['name'] ? $product['name'] : ( '' !== $product['slug'] ? $product['slug'] : __( 'New product', 'wycan-freemius-stats' ) ) )
		);
		echo '<p class="wfs-product-meta">';
		printf( '<code data-wfs-slug-label%s>%s</code>', '' === $product['slug'] ? ' hidden' : '', esc_html( $product['slug'] ) );
		printf(
			'<span data-wfs-id-wrap%s>%s <span data-wfs-id-label>%s</span></span>',
			'' === $product['product_id'] ? ' hidden' : '',
			esc_html__( 'ID', 'wycan-freemius-stats' ),
			esc_html( $product['product_id'] )
		);
		echo '</p>';
		printf(
			'<span class="wfs-badge wfs-badge-default" data-wfs-default-badge%s>%s</span>',
			$is_default ? '' : ' hidden',
			esc_html__( 'Default product of the widgets', 'wycan-freemius-stats' )
		);
		echo '</div>';

		echo '<div class="wfs-product-status">';
		if ( null !== $state ) {
			printf( '<span class="wfs-badge wfs-badge-%s">%s</span>', esc_attr( $state['tone'] ), esc_html( $state['label'] ) );
			if ( '' !== $state['detail'] ) {
				echo '<p class="wfs-product-detail">' . esc_html( $state['detail'] ) . '</p>';
			}
			$this->values( $product, $store );
		}
		echo '</div>';

		echo '<div class="wfs-product-actions">';
		printf(
			'<button type="button" class="button" data-wfs-toggle aria-expanded="%s" aria-controls="%s">%s</button>',
			$is_new ? 'true' : 'false',
			esc_attr( $id( 'body' ) ),
			esc_html( $is_new ? __( 'Close', 'wycan-freemius-stats' ) : __( 'Edit', 'wycan-freemius-stats' ) )
		);
		printf( '<button type="button" class="button" data-wfs-test>%s</button>', esc_html__( 'Test connection', 'wycan-freemius-stats' ) );
		printf( '<button type="button" class="wfs-link-btn wfs-text-error" data-wfs-remove>%s</button>', esc_html__( 'Remove', 'wycan-freemius-stats' ) );
		echo '</div>';
		echo '</header>';

		printf( '<div class="wfs-product-body" id="%s"%s>', esc_attr( $id( 'body' ) ), $is_new ? '' : ' hidden' );

		$this->row(
			$id( 'product_id' ),
			__( 'Freemius product ID', 'wycan-freemius-stats' ),
			__( 'Freemius dashboard, your product, Settings: the ID is shown at the top of the page.', 'wycan-freemius-stats' ),
			sprintf(
				'<input type="text" inputmode="numeric" pattern="[0-9]*" class="regular-text" id="%s" name="%s" value="%s" placeholder="12345" data-wfs-field="product_id">',
				esc_attr( $id( 'product_id' ) ),
				esc_attr( $name( 'product_id' ) ),
				esc_attr( $product['product_id'] )
			)
		);

		$this->row(
			$id( 'api_token' ),
			__( 'API token', 'wycan-freemius-stats' ),
			__( 'Freemius dashboard, your product, Settings, "API & Keys" tab: copy the "API Bearer Authorization Token". Stored encrypted, never displayed again.', 'wycan-freemius-stats' ),
			$this->token_control( $product, $id( 'api_token' ), $name )
		);

		$this->row(
			$id( 'name' ),
			__( 'Name', 'wycan-freemius-stats' ),
			__( 'Shown in the Elementor widget. Filled in by the connection test, editable.', 'wycan-freemius-stats' ),
			sprintf(
				'<input type="text" class="regular-text" id="%s" name="%s" value="%s" data-wfs-field="name">',
				esc_attr( $id( 'name' ) ),
				esc_attr( $name( 'name' ) ),
				esc_attr( $product['name'] )
			)
		);

		$this->row(
			$id( 'slug' ),
			__( 'Slug', 'wycan-freemius-stats' ),
			__( 'Identifier used by the shortcodes and the ACF field: lowercase letters, digits and dashes. Filled in by the connection test. Changing it breaks the pages that use the old slug.', 'wycan-freemius-stats' ),
			sprintf(
				'<input type="text" class="regular-text" id="%s" name="%s" value="%s" data-wfs-field="slug">',
				esc_attr( $id( 'slug' ) ),
				esc_attr( $name( 'slug' ) ),
				esc_attr( $product['slug'] )
			)
		);

		echo '<p class="wfs-test-result" role="status" aria-live="polite" data-wfs-test-result></p>';
		echo '</div></article>';
	}

	private function row( string $for, string $label, string $help, string $control ): void {
		echo '<div class="wfs-setting-row">';
		printf( '<div class="wfs-setting-label"><label for="%s">%s</label><p>%s</p></div>', esc_attr( $for ), esc_html( $label ), esc_html( $help ) );
		echo '<div class="wfs-setting-control">' . $control . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the callers.
		echo '</div>';
	}

	/**
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 * @param callable(string): string                                               $name
	 */
	private function token_control( array $product, string $input_id, callable $name ): string {
		$state = TokenStore::state( $product );

		if ( TokenStore::STATE_CONSTANT === $state ) {
			return sprintf(
				'<p><span class="wfs-badge wfs-badge-ok">%s</span></p><p class="wfs-field-desc">%s</p>',
				esc_html__( 'Defined in wp-config.php', 'wycan-freemius-stats' ),
				sprintf(
					/* translators: %s: PHP constant name */
					esc_html__( 'The constant %s takes precedence over the saved token. Remove it from wp-config.php to manage the token here.', 'wycan-freemius-stats' ),
					'<code>' . esc_html( TokenStore::constant_name( $product['product_id'] ) ) . '</code>'
				)
			);
		}

		$html = sprintf(
			'<input type="password" class="regular-text" id="%s" name="%s" value="" autocomplete="new-password" spellcheck="false" placeholder="%s" data-wfs-field="api_token">',
			esc_attr( $input_id ),
			esc_attr( $name( 'api_token' ) ),
			esc_attr( TokenStore::STATE_NONE === $state ? __( 'Paste the token', 'wycan-freemius-stats' ) : __( 'Leave empty to keep the saved token', 'wycan-freemius-stats' ) )
		);

		if ( TokenStore::STATE_OPTION === $state ) {
			$html .= sprintf(
				'<p class="wfs-field-desc">%s</p>',
				sprintf(
					/* translators: %s: last four characters of the token */
					esc_html__( 'Saved token ending with %s.', 'wycan-freemius-stats' ),
					'<code>…' . esc_html( TokenStore::hint( $product ) ) . '</code>'
				)
			);
		} elseif ( TokenStore::STATE_UNREADABLE === $state ) {
			$html .= '<p class="wfs-field-desc wfs-text-error">' . esc_html__( 'The saved token cannot be read any more (security keys changed): paste it again.', 'wycan-freemius-stats' ) . '</p>';
		}

		if ( TokenStore::STATE_NONE !== $state ) {
			$html .= sprintf(
				'<p><label class="wfs-check"><input type="checkbox" name="%s" value="1"> %s</label></p>',
				esc_attr( $name( 'api_token_delete' ) ),
				esc_html__( 'Delete the saved token', 'wycan-freemius-stats' )
			);
		}

		return $html;
	}

	/**
	 * @param array{slug: string, name: string, product_id: string, api_token: string} $product
	 */
	private function values( array $product, CountStore $store ): void {
		if ( '' === $product['product_id'] ) {
			return;
		}
		$active  = $store->count( $product['product_id'], true );
		$total   = $store->count( $product['product_id'], false );
		$version = $store->version( $product['product_id'] );
		$synced  = max(
			$store->synced_at( $product['product_id'], CountStore::FIELD_ACTIVE ),
			$store->synced_at( $product['product_id'], CountStore::FIELD_ALL ),
			$store->synced_at( $product['product_id'], CountStore::FIELD_VERSION )
		);
		if ( null === $active && null === $total && null === $version ) {
			return;
		}

		echo '<dl class="wfs-product-values">';
		if ( null !== $active ) {
			printf( '<div><dt>%s</dt><dd>%s</dd></div>', esc_html__( 'Active', 'wycan-freemius-stats' ), esc_html( number_format_i18n( $active ) ) );
		}
		if ( null !== $total ) {
			printf( '<div><dt>%s</dt><dd>%s</dd></div>', esc_html__( 'Total', 'wycan-freemius-stats' ), esc_html( number_format_i18n( $total ) ) );
		}
		if ( null !== $version ) {
			printf( '<div><dt>%s</dt><dd>%s</dd></div>', esc_html__( 'Version', 'wycan-freemius-stats' ), esc_html( $version['version'] ) );
		}
		if ( $synced > 0 ) {
			printf(
				'<div><dt>%s</dt><dd>%s</dd></div>',
				esc_html__( 'Synchronized', 'wycan-freemius-stats' ),
				esc_html(
					sprintf(
						/* translators: %s: human readable delay */
						__( '%s ago', 'wycan-freemius-stats' ),
						human_time_diff( $synced )
					)
				)
			);
		}
		echo '</dl>';
	}

	// -------------------------------------------------------------------------
	// Usage
	// -------------------------------------------------------------------------

	private function usage(): void {
		$products = $this->plugin->products->all();

		$this->group_title( __( 'Ready to copy', 'wycan-freemius-stats' ) );
		if ( [] === $products ) {
			echo '<div class="wfs-notice wfs-notice-info">' . esc_html__( 'Add a product first: its shortcodes will appear here.', 'wycan-freemius-stats' ) . '</div>';
		} else {
			echo '<table class="widefat wfs-table"><thead><tr>';
			printf(
				'<th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th>',
				esc_html__( 'Product', 'wycan-freemius-stats' ),
				esc_html__( 'Install count', 'wycan-freemius-stats' ),
				esc_html__( 'Latest version', 'wycan-freemius-stats' )
			);
			echo '</tr></thead><tbody>';
			foreach ( $products as $product ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( '' !== $product['name'] ? $product['name'] : $product['slug'] ),
					$this->copyable( '[freemius_installs slug="' . $product['slug'] . '"]' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
					$this->copyable( '[freemius_version slug="' . $product['slug'] . '"]' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				);
			}
			echo '</tbody></table>';
		}

		$this->group_title( __( 'Shortcode: install count', 'wycan-freemius-stats' ) );
		echo '<p>' . esc_html__( 'Displays the number of installations of a product. With an ACF field, one template serves every product page.', 'wycan-freemius-stats' ) . '</p>';
		$this->attributes_table(
			[
				[ 'acf_field', __( 'Name of the ACF field of the current page that holds the product slug. Takes precedence over slug.', 'wycan-freemius-stats' ), '' ],
				[ 'slug', __( 'Product slug, as configured in Products.', 'wycan-freemius-stats' ), '' ],
				[ 'format', __( '"number" shows the number only, "text" adds "active installations".', 'wycan-freemius-stats' ), 'number' ],
				[ 'active_only', __( '"true" counts active installations only, "false" counts every installation.', 'wycan-freemius-stats' ), 'true' ],
			]
		);
		echo '<p>' . $this->copyable( '[freemius_installs acf_field="freemius_product_slug" format="text"]' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		echo '<p class="wfs-field-desc">' . esc_html__( 'Output: <span class="freemius-install-count">1 234</span>. On error: <span class="freemius-error">…</span>, visible to everyone (frozen behaviour of the 1.x pages).', 'wycan-freemius-stats' ) . '</p>';

		$this->group_title( __( 'Shortcode: latest version', 'wycan-freemius-stats' ) );
		echo '<p>' . esc_html__( 'Displays the latest published version of a product (betas are ignored), its date or its WordPress compatibility.', 'wycan-freemius-stats' ) . '</p>';
		$this->attributes_table(
			[
				[ 'acf_field', __( 'Same rule as above.', 'wycan-freemius-stats' ), '' ],
				[ 'slug', __( 'Product slug.', 'wycan-freemius-stats' ), '' ],
				[ 'field', sprintf( /* translators: %s: list of values */ __( 'Value to display: %s.', 'wycan-freemius-stats' ), implode( ', ', VersionShortcode::FIELDS ) ) . ' ' . __( '"date" follows the site date format.', 'wycan-freemius-stats' ), 'version' ],
			]
		);
		echo '<p>' . $this->copyable( '[freemius_version acf_field="freemius_product_slug" field="date"]' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		echo '<p class="wfs-field-desc">' . esc_html__( 'Output: <span class="freemius-version">1.4.2</span>. On error, visitors see nothing; users who can edit see the reason. Usable in the Elementor "Text Editor" widget.', 'wycan-freemius-stats' ) . '</p>';

		$this->group_title( __( 'Elementor widget', 'wycan-freemius-stats' ) );
		echo '<p>' . esc_html__( 'The "Freemius Install Count" widget (General category) offers the same options as the shortcode, plus text before and after, color, typography and alignment.', 'wycan-freemius-stats' ) . '</p>';
		echo '<div class="wfs-notice wfs-notice-warning">' . esc_html__( 'In "Fixed product" mode, a widget that was never given an explicit product shows the first product of the list. Keep the first product in place, or check your widgets after removing it.', 'wycan-freemius-stats' ) . '</div>';

		$this->group_title( __( 'PHP function', 'wycan-freemius-stats' ) );
		echo '<p>' . esc_html__( 'In a theme template. Returns a whole number, or false when no value can be displayed.', 'wycan-freemius-stats' ) . '</p>';
		echo '<p>' . $this->copyable( "<?php \$count = wycan_get_freemius_installs( 'my-plugin', true ); ?>" ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.

		$this->group_title( __( 'How values are refreshed', 'wycan-freemius-stats' ) );
		echo '<ul class="wfs-list">';
		echo '<li>' . esc_html__( 'The pages never call Freemius: they show the last known value, stored on this site.', 'wycan-freemius-stats' ) . '</li>';
		echo '<li>' . esc_html__( 'Values are refreshed every hour in the background, and right after saving a new product or token.', 'wycan-freemius-stats' ) . '</li>';
		echo '<li>' . esc_html__( 'If Freemius is unreachable, the last value stays displayed and the error is shown in Products.', 'wycan-freemius-stats' ) . '</li>';
		echo '<li>' . esc_html__( 'The very first display of a product without any value calls Freemius once (5 seconds at most), then waits 5 minutes before trying again.', 'wycan-freemius-stats' ) . '</li>';
		echo '</ul>';

		$this->group_title( __( 'Token security', 'wycan-freemius-stats' ) );
		echo '<ul class="wfs-list">';
		echo '<li>' . esc_html__( 'A Freemius product token also allows writes on that product (customers, payments): treat it like a password.', 'wycan-freemius-stats' ) . '</li>';
		echo '<li>' . esc_html__( 'Tokens are encrypted in the database with a key derived from the WordPress security keys, and never sent back to the browser. A database export alone does not reveal them; a copy of wp-config.php does.', 'wycan-freemius-stats' ) . '</li>';
		echo '<li>' . sprintf(
			/* translators: %s: PHP constant name pattern */
			esc_html__( 'Alternatively, define %s in wp-config.php: it takes precedence over the saved token.', 'wycan-freemius-stats' ),
			'<code>define( \'WFS_TOKEN_12345\', \'…\' );</code>'
		) . '</li>';
		echo '</ul>';
	}

	private function group_title( string $title ): void {
		echo '<h2 class="wfs-section-group-title">' . esc_html( $title ) . '</h2>';
	}

	/**
	 * @param array<int, array{0: string, 1: string, 2: string}> $rows attribute, description, default.
	 */
	private function attributes_table( array $rows ): void {
		echo '<table class="widefat wfs-table wfs-attributes"><thead><tr>';
		printf(
			'<th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th>',
			esc_html__( 'Attribute', 'wycan-freemius-stats' ),
			esc_html__( 'Role', 'wycan-freemius-stats' ),
			esc_html__( 'Default', 'wycan-freemius-stats' )
		);
		echo '</tr></thead><tbody>';
		foreach ( $rows as [ $attribute, $description, $default ] ) {
			printf(
				'<tr><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
				esc_html( $attribute ),
				esc_html( $description ),
				'' === $default ? '<span class="wfs-muted">' . esc_html__( 'none', 'wycan-freemius-stats' ) . '</span>' : '<code>' . esc_html( $default ) . '</code>'
			);
		}
		echo '</tbody></table>';
	}

	private function copyable( string $code ): string {
		return sprintf(
			'<span class="wfs-copy"><code>%s</code><button type="button" class="wfs-link-btn" data-wfs-copy="%s">%s</button></span>',
			esc_html( $code ),
			esc_attr( $code ),
			esc_html__( 'Copy', 'wycan-freemius-stats' )
		);
	}
}
