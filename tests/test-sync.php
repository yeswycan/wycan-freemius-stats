<?php
/**
 * Test de la synchronisation en arrière-plan, du cache négatif et du shortcode [freemius_version].
 *
 * Usage : php -d mysqli.default_socket=<socket> tests/test-sync.php <chemin wordpress>
 * Le plugin 2.0.0 doit être actif. Les options sont restaurées à la fin. Aucun jeton réel.
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$wp_root = $argv[1] ?? null;
if ( ! $wp_root ) {
	fwrite( STDERR, "Argument : <chemin wordpress>\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']   = 'wp-plugins.local';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_USE_THEMES', false );
require $wp_root . '/wp-load.php';

use Wycan\FreemiusStats\Counts\CountStore;
use Wycan\FreemiusStats\Counts\Scheduler;
use Wycan\FreemiusStats\Plugin;
use Wycan\FreemiusStats\Products\Repository;

$failures = 0;
function check( string $label, bool $ok, string $detail = '' ): void {
	global $failures;
	echo ( $ok ? 'OK   ' : 'ECHEC' ) . ' ' . $label . ( $ok || '' === $detail ? '' : ' : ' . $detail ) . "\n";
	if ( ! $ok ) {
		++$failures;
	}
}

$calls = [];
add_filter(
	'pre_http_request',
	static function ( $pre, array $args, string $url ) use ( &$calls ) {
		$calls[] = $url;
		$ok      = static fn( array $data ): array => [
			'headers'  => [],
			'body'     => json_encode( $data ),
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'cookies'  => [],
			'filename' => null,
		];
		if ( str_contains( $url, '/products/1005/' ) ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28' );
		}
		if ( preg_match( '#/products/(\d+)/installs/count\.json(\?is_active=true)?$#', $url, $m ) ) {
			return $ok( [ 'count' => isset( $m[2] ) ? 1234 : 1500 ] );
		}
		if ( preg_match( '#/products/(\d+)/tags/latest\.json\?type=released$#', $url, $m ) ) {
			return $ok(
				[
					'id'                       => '555',
					'version'                  => '1.4.2',
					'created'                  => '2026-03-06 23:30:00',
					'requires_platform_version' => '6.7',
					'tested_up_to_version'     => '7.1',
					'release_mode'             => 'released',
				]
			);
		}
		return new WP_Error( 'wfs_test_unexpected_url', 'URL inattendue : ' . $url );
	},
	10,
	3
);

$plugin = Plugin::instance();
$backup = [
	Repository::OPTION => get_option( Repository::OPTION ),
	CountStore::OPTION => get_option( CountStore::OPTION ),
	'date_format'      => get_option( 'date_format' ),
	'timezone_string'  => get_option( 'timezone_string' ),
];
$plugin->scheduler->unschedule();
delete_option( CountStore::OPTION );
update_option( 'date_format', 'd/m/Y' );
update_option( 'timezone_string', 'Europe/Paris' );

$products = [
	[ 'slug' => 'wycan-alpha', 'name' => 'Wycan Alpha', 'product_id' => '1001', 'api_token' => 'TEST-TOKEN-ALPHA-0000' ],
	[ 'slug' => 'wycan-beta', 'name' => 'Wycan Beta', 'product_id' => '1002', 'api_token' => 'TEST-TOKEN-BETA-0000' ],
	[ 'slug' => 'wycan-panne', 'name' => 'Wycan Panne', 'product_id' => '1005', 'api_token' => 'TEST-TOKEN-PANNE-0000' ],
];

// 1. Enregistrement : synchronisation immédiate des produits nouveaux.
$calls = [];
update_option( Repository::OPTION, $products, false );
check( 'enregistrement : 3 appels par produit (actifs, totaux, version)', 9 === count( $calls ), (string) count( $calls ) );
$store = new CountStore();
check( 'compteurs stockés', 1234 === $store->count( '1001', true ) && 1500 === $store->count( '1001', false ) );
check( 'version stockée', '1.4.2' === ( $store->version( '1001' )['version'] ?? null ) );
check( 'erreur de la panne conservée', 'network' === ( $store->error( '1005' )['code'] ?? null ) );

// 2. Front : aucun appel réseau quand une valeur existe.
$calls = [];
check( 'valeur connue servie sans réseau', 1234 === wycan_get_freemius_installs( 'wycan-alpha' ) && [] === $calls );
check( 'rien de programmé pour une valeur fraîche', false === wp_next_scheduled( Scheduler::HOOK_PRODUCT, [ '1001' ] ) );

// 3. Valeur de plus d'une heure : servie telle quelle, rafraîchissement programmé.
$all                          = get_option( CountStore::OPTION );
$all['1001']['active']['at'] = time() - 2 * HOUR_IN_SECONDS;
update_option( CountStore::OPTION, $all, false );
// Le magasin garde un cache mémoire : on le vide pour relire l'option modifiée.
$fresh = new ReflectionProperty( CountStore::class, 'cache' );
$fresh->setAccessible( true );
$fresh->setValue( $plugin->store, null );
$calls = [];
check( 'valeur ancienne servie sans attendre', 1234 === wycan_get_freemius_installs( 'wycan-alpha' ) && [] === $calls );
check( 'rafraîchissement programmé en arrière-plan', false !== wp_next_scheduled( Scheduler::HOOK_PRODUCT, [ '1001' ] ) );
$plugin->scheduler->request( '1001' );
check( 'programmation dédoublonnée', 1 === count( array_filter( _get_cron_array(), static fn( $events ) => isset( $events[ Scheduler::HOOK_PRODUCT ] ) ) ) );

// 4. Exécution de la tâche programmée.
$calls = [];
do_action( Scheduler::HOOK_PRODUCT, '1001' );
check( 'tâche produit : 3 appels', 3 === count( $calls ), (string) count( $calls ) );
$fresh->setValue( $plugin->store, null );
check( 'date de synchronisation rafraîchie', $plugin->store->synced_at( '1001', 'active' ) > time() - 60 );

// 5. Tâche horaire.
$plugin->scheduler->ensure_scheduled();
check( 'tâche horaire programmée', false !== wp_next_scheduled( Scheduler::HOOK_ALL ) );
check( 'récurrence horaire', 'hourly' === wp_get_scheduled_event( Scheduler::HOOK_ALL )->schedule );
$calls = [];
do_action( Scheduler::HOOK_ALL );
check( 'tâche horaire : tous les produits', 9 === count( $calls ), (string) count( $calls ) );
$plugin->scheduler->unschedule();
check( 'désactivation : plus rien de programmé', false === wp_next_scheduled( Scheduler::HOOK_ALL ) && false === wp_next_scheduled( Scheduler::HOOK_PRODUCT, [ '1001' ] ) );

// 6. Cache négatif : première valeur absente et API en panne.
$all = get_option( CountStore::OPTION );
unset( $all['1005'] );
update_option( CountStore::OPTION, $all, false );
$fresh->setValue( $plugin->store, null );
$calls = [];
check( 'panne : faux après un appel', false === wycan_get_freemius_installs( 'wycan-panne' ) && 1 === count( $calls ) );
$calls = [];
check( 'panne : pas de nouvel appel pendant 5 minutes', false === wycan_get_freemius_installs( 'wycan-panne' ) && [] === $calls );
check( 'panne : l\'autre mode a son propre cache négatif', false === wycan_get_freemius_installs( 'wycan-panne', false ) && 1 === count( $calls ) );

// 7. Shortcode [freemius_version].
wp_set_current_user( 0 );
check( 'version par défaut', '<span class="freemius-version">1.4.2</span>' === do_shortcode( '[freemius_version slug="wycan-alpha"]' ) );
check( 'champ date au format du site et fuseau Europe/Paris', '<span class="freemius-version">07/03/2026</span>' === do_shortcode( '[freemius_version slug="wycan-alpha" field="date"]' ), do_shortcode( '[freemius_version slug="wycan-alpha" field="date"]' ) );
check( 'champ requires', '<span class="freemius-version">6.7</span>' === do_shortcode( '[freemius_version slug="wycan-alpha" field="requires"]' ) );
check( 'champ tested', '<span class="freemius-version">7.1</span>' === do_shortcode( '[freemius_version slug="wycan-alpha" field="tested"]' ) );
check( 'champ inconnu : version', '<span class="freemius-version">1.4.2</span>' === do_shortcode( '[freemius_version slug="wycan-alpha" field="foo"]' ) );
check( 'visiteur, slug inconnu : rien', '' === do_shortcode( '[freemius_version slug="inconnu"]' ) );
check( 'visiteur, sans attribut : rien', '' === do_shortcode( '[freemius_version]' ) );
check( 'visiteur, produit en panne : rien', '' === do_shortcode( '[freemius_version slug="wycan-panne"]' ) );
$calls = [];
do_shortcode( '[freemius_version slug="wycan-alpha"]' );
check( 'version connue servie sans réseau', [] === $calls );
wp_set_current_user( 1 );
check( 'éditeur, slug inconnu : message', str_starts_with( do_shortcode( '[freemius_version slug="inconnu"]' ), '<span class="freemius-error">' ) );
check( 'éditeur, panne : message', str_contains( do_shortcode( '[freemius_version slug="wycan-panne"]' ), 'wycan-panne' ) );
wp_set_current_user( 0 );

// 8. Suppression d'un produit : valeurs purgées.
$calls   = [];
$current = get_option( Repository::OPTION );
update_option( Repository::OPTION, [ $current[0] ], false ); // jeton déjà chiffré : inchangé
check( 'produit retiré : valeurs purgées', [] === ( get_option( CountStore::OPTION )['1002'] ?? [] ) );
check( 'produit inchangé : aucune resynchronisation', [] === $calls );
$calls = [];
update_option( Repository::OPTION, [ [ 'slug' => 'wycan-alpha', 'name' => 'Wycan Alpha', 'product_id' => '1001', 'api_token' => 'TEST-TOKEN-ALPHA-NEW-1111' ] ], false );
check( 'nouveau jeton : resynchronisation immédiate', 3 === count( $calls ), (string) count( $calls ) );

// Restauration.
foreach ( $backup as $option => $value ) {
	if ( false === $value ) {
		delete_option( $option );
	} else {
		update_option( $option, $value, Repository::OPTION === $option || CountStore::OPTION === $option ? false : null );
	}
}
$plugin->scheduler->unschedule();

echo $failures ? "\n{$failures} échec(s).\n" : "\nTous les contrôles passent.\n";
exit( $failures ? 1 : 0 );
