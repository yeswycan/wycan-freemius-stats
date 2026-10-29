<?php
/**
 * Capture des instantanés HTML du front (shortcode et widget Elementor).
 *
 * Usage, depuis le dossier racine de WordPress, avec le binaire PHP du site :
 *   php -d mysqli.default_socket=<socket> tests/capture-snapshots.php <chemin wordpress> <dossier plugin> <dossier de sortie>
 *
 * Le script active Elementor et le plugin si besoin, remplace l'option produits par un jeu
 * de test (aucun jeton réel), fige les réponses Freemius par pre_http_request, force la
 * langue fr_FR et écrit un fichier par cas. Il est exécuté deux fois : en 1.1.0 puis en
 * 2.0.0, et les deux dossiers de sortie doivent être identiques.
 *
 * Variante sans ACF : WFS_NO_ACF=1 (la fonction get_field n'est pas définie).
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

[ , $wp_root, $plugin_dir, $out_dir ] = $argv + [ null, null, null, null ];
if ( ! $wp_root || ! $plugin_dir || ! $out_dir ) {
	fwrite( STDERR, "Arguments : <chemin wordpress> <dossier plugin> <dossier de sortie>\n" );
	exit( 1 );
}

$no_acf = '1' === getenv( 'WFS_NO_ACF' );

if ( ! $no_acf ) {
	/** Doublure minimale d'ACF : seule get_field() est consommée par le plugin. */
	function get_field( $name, $post_id = false ) {
		return $GLOBALS['wfs_acf_fields'][ $name ] ?? null;
	}
}

$GLOBALS['wfs_acf_fields'] = [ 'freemius_product_slug' => 'wycan-alpha', 'champ_vide' => '' ];

$_SERVER['HTTP_HOST']      = 'wp-plugins.local';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', false );

add_filter_early( 'locale', 'fr_FR' );
add_filter_early( 'pre_option_WPLANG', 'fr_FR' );
add_filter_early( 'determine_locale', 'fr_FR' );

function add_filter_early( string $hook, string $value ): void {
	$GLOBALS['wp_filter'][ $hook ]                   = $GLOBALS['wp_filter'][ $hook ] ?? [];
	$GLOBALS['wp_filter'][ $hook ][10]               = $GLOBALS['wp_filter'][ $hook ][10] ?? [];
	$GLOBALS['wp_filter'][ $hook ][10][ uniqid() ] = [ 'function' => static fn() => $value, 'accepted_args' => 0 ];
}

require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$plugin_main = $plugin_dir . '/wycan-freemius-stats.php';
$needed      = [ 'elementor/elementor.php', $plugin_main ];
$active      = (array) get_option( 'active_plugins', [] );
$wanted      = array_values( array_filter( $active, static fn( $file ) => in_array( $file, $needed, true ) || ! str_starts_with( (string) $file, 'wycan-' ) ) );
foreach ( $needed as $file ) {
	if ( ! in_array( $file, $wanted, true ) ) {
		$wanted[] = $file;
	}
}
sort( $wanted );
if ( $wanted !== $active ) {
	// Deux versions du plugin déclarent les mêmes classes : l'option est écrite sans inclure
	// les fichiers, puis le script est relancé.
	update_option( 'active_plugins', $wanted );
	fwrite( STDERR, "Extensions activées : relance du script.\n" );
	exit( 75 );
}

if ( ! class_exists( '\Elementor\Plugin' ) ) {
	fwrite( STDERR, "Elementor n'est pas chargé.\n" );
	exit( 1 );
}

// Jeu de test. Aucun de ces jetons n'existe : ils ne quittent jamais la machine (pre_http_request).
$products = [
	[ 'slug' => 'wycan-alpha', 'name' => 'Wycan Alpha', 'product_id' => '1001', 'api_token' => 'TEST-TOKEN-ALPHA-0000' ],
	[ 'slug' => 'wycan-beta', 'name' => 'Wycan Beta', 'product_id' => '1002', 'api_token' => 'TEST-TOKEN-BETA-0000' ],
	[ 'slug' => 'wycan-zero', 'name' => 'Wycan Zero', 'product_id' => '1003', 'api_token' => 'TEST-TOKEN-ZERO-0000' ],
	[ 'slug' => 'wycan-big', 'name' => 'Wycan Big', 'product_id' => '1004', 'api_token' => 'TEST-TOKEN-BIG-0000' ],
	[ 'slug' => 'wycan-panne', 'name' => 'Wycan Panne', 'product_id' => '1005', 'api_token' => 'TEST-TOKEN-PANNE-0000' ],
	[ 'slug' => 'wycan-json', 'name' => 'Wycan Json', 'product_id' => '1006', 'api_token' => 'TEST-TOKEN-JSON-0000' ],
	[ 'slug' => 'wycan-401', 'name' => 'Wycan Refus', 'product_id' => '1007', 'api_token' => 'TEST-TOKEN-REFUS-0000' ],
	[ 'slug' => 'wycan-sans-jeton', 'name' => 'Wycan Sans Jeton', 'product_id' => '1008', 'api_token' => '' ],
	[ 'slug' => 'wycan-sans-id', 'name' => 'Wycan Sans Id', 'product_id' => '', 'api_token' => 'TEST-TOKEN-SANSID-0000' ],
];

$counts = [
	'1001' => [ 'active' => 1234, 'all' => 1500 ],
	'1002' => [ 'active' => 1, 'all' => 42 ],
	'1003' => [ 'active' => 0, 'all' => 0 ],
	'1004' => [ 'active' => 1234567, 'all' => 9876543 ],
];

add_filter(
	'pre_http_request',
	static function ( $pre, array $args, string $url ) use ( $counts ) {
		if ( ! preg_match( '#^https://api\.freemius\.com/v1/products/(\d+)/installs/count\.json(\?is_active=true)?$#', $url, $m ) ) {
			return new WP_Error( 'wfs_test_unexpected_url', 'URL inattendue : ' . $url );
		}
		$id   = $m[1];
		$mode = isset( $m[2] ) ? 'active' : 'all';
		if ( '1005' === $id ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		}
		$body = '{"error":{"message":"Not found","code":"not_found","http":404}}';
		$code = 404;
		if ( '1006' === $id ) {
			$body = '{"unexpected":true}';
			$code = 200;
		} elseif ( '1007' === $id ) {
			$body = '{"error":{"message":"Unauthorized","code":"unauthorized","http":401}}';
			$code = 401;
		} elseif ( isset( $counts[ $id ] ) ) {
			$body = json_encode( [ 'count' => $counts[ $id ][ $mode ] ] );
			$code = 200;
		}
		return [
			'headers'  => [ 'content-type' => 'application/json' ],
			'body'     => $body,
			'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
			'cookies'  => [],
			'filename' => null,
		];
	},
	10,
	3
);

function wfs_reset_caches(): void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%freemius_count_%' OR option_name = 'wycan_freemius_counts'" );
	wp_cache_flush();
}

function wfs_widget_html( array $settings ): string {
	$class = null;
	foreach ( [ '\Wycan_Freemius_Stats_Widget', '\Wycan\FreemiusStats\Front\InstallCountWidget' ] as $candidate ) {
		if ( class_exists( $candidate ) ) {
			$class = $candidate;
			break;
		}
	}
	if ( null === $class ) {
		\Elementor\Plugin::instance()->widgets_manager->get_widget_types();
		foreach ( [ '\Wycan_Freemius_Stats_Widget', '\Wycan\FreemiusStats\Front\InstallCountWidget' ] as $candidate ) {
			if ( class_exists( $candidate ) ) {
				$class = $candidate;
				break;
			}
		}
	}
	if ( null === $class ) {
		return "CLASSE DU WIDGET INTROUVABLE\n";
	}
	$widget = new $class(
		[ 'id' => 'a1b2c3d', 'elType' => 'widget', 'widgetType' => 'freemius_install_count', 'settings' => $settings, 'elements' => [] ],
		[]
	);
	$render = new ReflectionMethod( $widget, 'render' );
	$render->setAccessible( true );
	ob_start();
	$render->invoke( $widget );
	return (string) ob_get_clean();
}

function wfs_controls_contract(): array {
	$widget = \Elementor\Plugin::instance()->widgets_manager->get_widget_types( 'freemius_install_count' );
	if ( ! $widget ) {
		return [ 'erreur' => 'widget non enregistré' ];
	}
	// Hors administration, Elementor range les contrôles de style dans une pile à part
	// (Core\Frontend\Performance) : le contrat se lit avec cette optimisation désactivée.
	$flag = new ReflectionProperty( \Elementor\Core\Frontend\Performance::class, 'is_frontend' );
	$flag->setAccessible( true );
	$flag->setValue( null, false );
	\Elementor\Plugin::instance()->controls_manager->delete_stack( $widget );
	$out = [
		'name'       => $widget->get_name(),
		'categories' => $widget->get_categories(),
		'icon'       => $widget->get_icon(),
		'controls'   => [],
	];
	foreach ( $widget->get_controls() as $key => $control ) {
		if ( str_starts_with( $key, '_' ) ) {
			continue;
		}
		$entry = [ 'type' => $control['type'] ?? null ];
		foreach ( [ 'default', 'condition', 'selectors', 'return_value', 'tab', 'responsive', 'section', 'condition' ] as $k ) {
			if ( array_key_exists( $k, $control ) ) {
				$entry[ $k ] = $control[ $k ];
			}
		}
		if ( isset( $control['options'] ) && is_array( $control['options'] ) ) {
			$entry['option_keys'] = array_keys( $control['options'] );
		}
		if ( isset( $control['selector'] ) ) {
			$entry['selector'] = $control['selector'];
		}
		$out['controls'][ $key ] = $entry;
	}
	$flag->setValue( null, true );
	\Elementor\Plugin::instance()->controls_manager->delete_stack( $widget );
	return $out;
}

$cases = [];
$s     = static fn( string $sc ): string => do_shortcode( $sc );
$w     = 'wfs_widget_html';

// Shortcode.
$cases['sc-sans-attribut']             = fn() => $s( '[freemius_installs]' );
$cases['sc-alpha-defaut']              = fn() => $s( '[freemius_installs slug="wycan-alpha"]' );
$cases['sc-alpha-text']                = fn() => $s( '[freemius_installs slug="wycan-alpha" format="text"]' );
$cases['sc-alpha-all']                 = fn() => $s( '[freemius_installs slug="wycan-alpha" active_only="false"]' );
$cases['sc-alpha-all-text']            = fn() => $s( '[freemius_installs slug="wycan-alpha" active_only="false" format="text"]' );
$cases['sc-alpha-active-majuscules']   = fn() => $s( '[freemius_installs slug="wycan-alpha" active_only="TRUE"]' );
$cases['sc-alpha-active-1']            = fn() => $s( '[freemius_installs slug="wycan-alpha" active_only="1"]' );
$cases['sc-alpha-format-inconnu']      = fn() => $s( '[freemius_installs slug="wycan-alpha" format="foo"]' );
$cases['sc-beta-text-singulier']       = fn() => $s( '[freemius_installs slug="wycan-beta" format="text"]' );
$cases['sc-beta-all-text']             = fn() => $s( '[freemius_installs slug="wycan-beta" format="text" active_only="false"]' );
$cases['sc-zero']                      = fn() => $s( '[freemius_installs slug="wycan-zero"]' );
$cases['sc-zero-text']                 = fn() => $s( '[freemius_installs slug="wycan-zero" format="text"]' );
$cases['sc-big']                       = fn() => $s( '[freemius_installs slug="wycan-big"]' );
$cases['sc-big-all-text']              = fn() => $s( '[freemius_installs slug="wycan-big" active_only="false" format="text"]' );
$cases['sc-slug-inconnu']              = fn() => $s( '[freemius_installs slug="inconnu"]' );
$cases['sc-slug-html']                 = fn() => $s( '[freemius_installs slug="<b>x</b>"]' );
$cases['sc-panne-reseau']              = fn() => $s( '[freemius_installs slug="wycan-panne"]' );
$cases['sc-json-invalide']             = fn() => $s( '[freemius_installs slug="wycan-json"]' );
$cases['sc-401']                       = fn() => $s( '[freemius_installs slug="wycan-401"]' );
$cases['sc-sans-jeton']                = fn() => $s( '[freemius_installs slug="wycan-sans-jeton"]' );
$cases['sc-sans-id']                   = fn() => $s( '[freemius_installs slug="wycan-sans-id"]' );
$cases['sc-acf-valeur']                = fn() => $s( '[freemius_installs acf_field="freemius_product_slug"]' );
$cases['sc-acf-valeur-text']           = fn() => $s( '[freemius_installs acf_field="freemius_product_slug" format="text"]' );
$cases['sc-acf-prioritaire-sur-slug']  = fn() => $s( '[freemius_installs acf_field="freemius_product_slug" slug="wycan-beta"]' );
$cases['sc-acf-vide']                  = fn() => $s( '[freemius_installs acf_field="champ_vide"]' );
$cases['sc-acf-inconnu']               = fn() => $s( '[freemius_installs acf_field="champ_inexistant"]' );
$cases['sc-acf-inconnu-html']          = fn() => $s( '[freemius_installs acf_field="<i>x</i>"]' );
$cases['sc-fonction-php-alpha']        = fn() => var_export( wycan_get_freemius_installs( 'wycan-alpha' ), true );
$cases['sc-fonction-php-alpha-all']    = fn() => var_export( wycan_get_freemius_installs( 'wycan-alpha', false ), true );
$cases['sc-fonction-php-inconnu']      = fn() => var_export( wycan_get_freemius_installs( 'inconnu' ), true );
$cases['sc-fonction-php-zero']         = fn() => var_export( wycan_get_freemius_installs( 'wycan-zero' ), true );
$cases['sc-facade-classe']             = fn() => var_export( Wycan_Freemius_Stats::get_instance()->get_install_count( 'wycan-beta', false ), true );

// Widget.
$cases['w-defauts']                    = fn() => $w( [] );
$cases['w-acf-text']                   = fn() => $w( [ 'source_type' => 'acf', 'display_format' => 'text' ] );
$cases['w-acf-champ-vide']             = fn() => $w( [ 'source_type' => 'acf', 'acf_field_name' => 'champ_vide' ] );
$cases['w-acf-champ-inconnu']          = fn() => $w( [ 'source_type' => 'acf', 'acf_field_name' => 'champ_inexistant' ] );
$cases['w-acf-nom-vide']               = fn() => $w( [ 'source_type' => 'acf', 'acf_field_name' => '' ] );
$cases['w-acf-nom-html']               = fn() => $w( [ 'source_type' => 'acf', 'acf_field_name' => '<i>x</i>' ] );
$cases['w-fixe-defaut-premier-produit'] = fn() => $w( [ 'source_type' => 'fixed' ] );
$cases['w-fixe-alpha']                 = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha' ] );
$cases['w-fixe-alpha-text']            = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'display_format' => 'text' ] );
$cases['w-fixe-alpha-all']             = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'active_only' => '' ] );
$cases['w-fixe-alpha-all-text']        = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'active_only' => '', 'display_format' => 'text' ] );
$cases['w-fixe-alpha-active-no']       = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'active_only' => 'no' ] );
$cases['w-fixe-prefixe']               = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'prefix_text' => 'Plus de' ] );
$cases['w-fixe-suffixe']               = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'suffix_text' => 'utilisateurs' ] );
$cases['w-fixe-prefixe-suffixe-text']  = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'display_format' => 'text', 'prefix_text' => 'Déjà', 'suffix_text' => '!' ] );
$cases['w-fixe-prefixe-html']          = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'prefix_text' => '<b>Plus</b> & "de"', 'suffix_text' => "<i>users</i> 'ok'" ] );
$cases['w-fixe-prefixe-zero']          = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'prefix_text' => '0', 'suffix_text' => '0' ] );
$cases['w-fixe-beta-text-singulier']   = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-beta', 'display_format' => 'text' ] );
$cases['w-fixe-zero']                  = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-zero' ] );
$cases['w-fixe-zero-text']             = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-zero', 'display_format' => 'text' ] );
$cases['w-fixe-big-text']              = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-big', 'display_format' => 'text' ] );
$cases['w-fixe-slug-vide']             = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => '' ] );
$cases['w-fixe-inconnu']               = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'inconnu' ] );
$cases['w-fixe-panne']                 = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-panne' ] );
$cases['w-fixe-401']                   = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-401' ] );
$cases['w-fixe-format-inconnu']        = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha', 'display_format' => 'foo' ] );
$cases['w-source-inconnue']            = fn() => $w( [ 'source_type' => 'foo', 'product_slug' => 'wycan-alpha' ] );
$cases['w-controles']                  = fn() => json_encode( wfs_controls_contract(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";

// Option vide : widget et shortcode sans aucun produit.
$cases['vide-sc-alpha']                = fn() => $s( '[freemius_installs slug="wycan-alpha"]' );
$cases['vide-w-defauts']               = fn() => $w( [] );
$cases['vide-w-fixe-defaut']           = fn() => $w( [ 'source_type' => 'fixed' ] );
$cases['vide-w-fixe-slug']             = fn() => $w( [ 'source_type' => 'fixed', 'product_slug' => 'wycan-alpha' ] );
$cases['vide-w-controles']             = fn() => json_encode( wfs_controls_contract(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";

if ( $no_acf ) {
	$cases = array_filter( $cases, static fn( $k ) => str_contains( $k, 'acf' ) || 'w-defauts' === $k || 'sc-fonction-php-alpha' === $k, ARRAY_FILTER_USE_KEY );
}

if ( ! is_dir( $out_dir ) && ! mkdir( $out_dir, 0755, true ) ) {
	fwrite( STDERR, "Dossier de sortie impossible.\n" );
	exit( 1 );
}

$prefix = $no_acf ? 'sans-acf-' : '';
foreach ( $cases as $name => $fn ) {
	$empty = str_starts_with( $name, 'vide-' );
	update_option( 'wycan_freemius_products', $empty ? [] : $products, false );
	wfs_reset_caches();
	\Elementor\Plugin::instance()->widgets_manager->get_widget_types(); // force l'enregistrement.
	$widget = \Elementor\Plugin::instance()->widgets_manager->get_widget_types( 'freemius_install_count' );
	if ( $widget ) {
		// Elementor met en cache la pile de contrôles par nom de widget ; les défauts dépendent de l'option.
		\Elementor\Plugin::instance()->controls_manager->delete_stack( $widget );
	}
	$html = (string) $fn();
	file_put_contents( $out_dir . '/' . $prefix . $name . '.html', $html );
}
update_option( 'wycan_freemius_products', $products, false );
wfs_reset_caches();

echo count( $cases ) . " instantanés écrits dans {$out_dir}\n";
