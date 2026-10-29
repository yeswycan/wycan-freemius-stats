<?php
/**
 * Test de la migration 1.1.0 vers 2.0.0 et du nettoyage de l'option produits.
 *
 * Usage : php -d mysqli.default_socket=<socket> tests/test-migration.php <chemin wordpress>
 * Le plugin 2.0.0 doit être actif. L'option est restaurée à la fin. Aucun jeton réel.
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

define( 'WFS_TOKEN_9009', 'TEST-TOKEN-FROM-CONSTANT-0000' );
$_SERVER['HTTP_HOST']   = 'wp-plugins.local';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_USE_THEMES', false );
require $wp_root . '/wp-load.php';

use Wycan\FreemiusStats\Products\Repository;
use Wycan\FreemiusStats\Security\Crypto;
use Wycan\FreemiusStats\Security\TokenStore;
use Wycan\FreemiusStats\Upgrade\Migrator;

if ( ! class_exists( Migrator::class ) ) {
	fwrite( STDERR, "Le plugin 2.0.0 n'est pas actif.\n" );
	exit( 1 );
}

$failures = 0;
function check( string $label, bool $ok, string $detail = '' ): void {
	global $failures;
	echo ( $ok ? 'OK   ' : 'ECHEC' ) . ' ' . $label . ( $ok || '' === $detail ? '' : ' : ' . $detail ) . "\n";
	if ( ! $ok ) {
		++$failures;
	}
}

/** Écrit l'option brute, sans passer par sanitize (comme la 1.1.0 ou un enregistrement corrompu). */
function write_raw( mixed $value, string $autoload = 'yes' ): void {
	global $wpdb;
	$wpdb->delete( $wpdb->options, [ 'option_name' => Repository::OPTION ] );
	if ( null !== $value ) {
		$wpdb->insert( $wpdb->options, [ 'option_name' => Repository::OPTION, 'option_value' => maybe_serialize( $value ), 'autoload' => $autoload ] );
	}
	delete_option( Migrator::OPTION );
	wp_cache_flush();
	\Wycan\FreemiusStats\Plugin::instance()->products->flush();
}
function raw_value(): mixed {
	global $wpdb;
	wp_cache_flush();
	return maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", Repository::OPTION ) ) );
}
function autoload_value(): ?string {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Repository::OPTION ) );
}

$backup          = raw_value();
$backup_autoload = autoload_value();

// 1. Option 1.1.0 : jetons en clair, index troués, ligne vide, ligne sans slug.
$legacy = [
	0 => [ 'slug' => 'wycan-alpha', 'name' => 'Wycan Alpha', 'product_id' => '1001', 'api_token' => 'TEST-TOKEN-ALPHA-0000' ],
	2 => [ 'slug' => 'wycan-beta', 'name' => 'Wycan Beta', 'product_id' => '1002', 'api_token' => 'TEST-TOKEN-BETA-0000' ],
	3 => [ 'slug' => '', 'name' => '', 'product_id' => '', 'api_token' => '' ],
	5 => [ 'slug' => 'Wycan Gamma', 'name' => 'Wycan Gamma', 'product_id' => ' 1003 ', 'api_token' => '' ],
	7 => [ 'slug' => 'wycan-alpha', 'name' => 'Doublon', 'product_id' => '1', 'api_token' => 'x' ],
];
write_raw( $legacy );
set_transient( 'freemius_count_wycan-alpha_active', 1234, HOUR_IN_SECONDS );
set_transient( 'freemius_count_wycan-alpha_all', 1500, HOUR_IN_SECONDS );

Migrator::maybe_run();
$after = raw_value();

check( 'liste réindexée 0..n-1', is_array( $after ) && array_keys( $after ) === [ 0, 1, 2 ] );
check( 'ordre conservé', array_column( $after, 'slug' ) === [ 'wycan-alpha', 'wycan-beta', 'wycan-gamma' ], implode( ',', array_column( $after, 'slug' ) ) );
check( 'ligne vide et doublon ignorés', count( $after ) === 3 );
check( 'slug normalisé (sanitize_title)', 'wycan-gamma' === $after[2]['slug'] );
check( 'identifiant nettoyé', '1003' === $after[2]['product_id'] );
check( 'structure des clés inchangée', array_keys( $after[0] ) === [ 'slug', 'name', 'product_id', 'api_token' ] );
check( 'jeton alpha chiffré', Crypto::is_encrypted( $after[0]['api_token'] ) );
check( 'jeton alpha déchiffrable', 'TEST-TOKEN-ALPHA-0000' === Crypto::decrypt( $after[0]['api_token'] ) );
check( 'jeton beta déchiffrable', 'TEST-TOKEN-BETA-0000' === Crypto::decrypt( $after[1]['api_token'] ) );
check( 'jeton vide reste vide', '' === $after[2]['api_token'] );
check( 'option hors chargement automatique', in_array( autoload_value(), [ 'no', 'off' ], true ), (string) autoload_value() );
check( 'transitoires 1.1.0 supprimés', false === get_transient( 'freemius_count_wycan-alpha_active' ) && false === get_transient( 'freemius_count_wycan-alpha_all' ) );
check( 'version enregistrée', WFS_VERSION === get_option( Migrator::OPTION ) );

// 2. Idempotence : une seconde exécution ne change rien.
delete_option( Migrator::OPTION );
Migrator::maybe_run();
check( 'seconde exécution sans changement', maybe_serialize( raw_value() ) === maybe_serialize( $after ) );

// 3. Option corrompue.
foreach ( [ 'chaîne vide' => '', 'null sérialisé' => null, 'nombre' => 42, 'liste de scalaires' => [ 'a', 'b' ] ] as $label => $corrupted ) {
	global $wpdb;
	$wpdb->delete( $wpdb->options, [ 'option_name' => Repository::OPTION ] );
	$wpdb->insert( $wpdb->options, [ 'option_name' => Repository::OPTION, 'option_value' => null === $corrupted ? '' : maybe_serialize( $corrupted ), 'autoload' => 'yes' ] );
	delete_option( Migrator::OPTION );
	wp_cache_flush();
	Migrator::maybe_run();
	check( 'option corrompue (' . $label . ') ramenée à une liste vide', [] === raw_value() );
}
write_raw( null );
Migrator::maybe_run();
check( 'option absente : liste vide lisible', [] === \Wycan\FreemiusStats\Plugin::instance()->products->all() );

// 4. États du jeton.
$sealed = TokenStore::seal( 'TEST-TOKEN-SEALED-1234' );
check( 'état constante prioritaire', TokenStore::STATE_CONSTANT === TokenStore::state( [ 'product_id' => '9009', 'api_token' => $sealed ] ) );
check( 'valeur depuis la constante', 'TEST-TOKEN-FROM-CONSTANT-0000' === TokenStore::get( [ 'product_id' => '9009', 'api_token' => $sealed ] ) );
check( 'état option', TokenStore::STATE_OPTION === TokenStore::state( [ 'product_id' => '1', 'api_token' => $sealed ] ) );
check( 'indice : 4 derniers caractères', '1234' === TokenStore::hint( [ 'product_id' => '1', 'api_token' => $sealed ] ) );
check( 'état illisible', TokenStore::STATE_UNREADABLE === TokenStore::state( [ 'product_id' => '1', 'api_token' => 'v1:AAAA' ] ) );
check( 'illisible : aucune valeur, aucune erreur fatale', null === TokenStore::get( [ 'product_id' => '1', 'api_token' => 'v1:AAAA' ] ) );
check( 'état absent', TokenStore::STATE_NONE === TokenStore::state( [ 'product_id' => '1', 'api_token' => '' ] ) );
check( 'deux chiffrements du même jeton diffèrent (nonce)', $sealed !== TokenStore::seal( 'TEST-TOKEN-SEALED-1234' ) );

// 5. Nettoyage du formulaire : conservation, remplacement, suppression, renommage.
write_raw( [ [ 'slug' => 'wycan-alpha', 'name' => 'Wycan Alpha', 'product_id' => '1001', 'api_token' => $sealed ] ], 'no' );
$form = Repository::sanitize(
	[
		'7'  => [ 'slug' => 'wycan-alpha', 'name' => 'Alpha <b>x</b>', 'product_id' => '1001', 'api_token' => '', 'stored_slug' => 'wycan-alpha' ],
		'12' => [ 'slug' => 'nouveau', 'name' => 'Nouveau', 'product_id' => '2002', 'api_token' => 'TEST-TOKEN-NEW-5678' ],
		'3'  => [ 'slug' => '', 'name' => '', 'product_id' => '', 'api_token' => '' ],
		'4'  => [ 'slug' => 'sans-id', 'name' => 'Sans id', 'product_id' => 'abc', 'api_token' => '' ],
	]
);
check( 'champ jeton vide : jeton conservé', $sealed === $form[0]['api_token'] );
check( 'nom assaini', 'Alpha x' === $form[0]['name'], $form[0]['name'] );
check( 'nouveau jeton chiffré', Crypto::is_encrypted( $form[1]['api_token'] ) && 'TEST-TOKEN-NEW-5678' === Crypto::decrypt( $form[1]['api_token'] ) );
check( 'ordre des lignes soumises conservé, index réécrits', array_keys( $form ) === [ 0, 1, 2 ] && 'sans-id' === $form[2]['slug'] );
check( 'identifiant invalide vidé', '' === $form[2]['product_id'] );

$form = Repository::sanitize( [ [ 'slug' => 'wycan-alpha', 'name' => 'Wycan Alpha', 'product_id' => '1001', 'api_token' => '', 'api_token_delete' => '1', 'stored_slug' => 'wycan-alpha' ] ] );
check( 'case supprimer : jeton effacé', '' === $form[0]['api_token'] );

$form = Repository::sanitize( [ [ 'slug' => 'alpha-renomme', 'name' => 'Wycan Alpha', 'product_id' => '1001', 'api_token' => '', 'stored_slug' => 'wycan-alpha' ] ] );
check( 'slug renommé : jeton suivi grâce à stored_slug', $sealed === $form[0]['api_token'] && 'alpha-renomme' === $form[0]['slug'] );

$form = Repository::sanitize( [ [ 'slug' => 'wycan-alpha', 'name' => 'A', 'product_id' => '1001', 'api_token' => 'TEST-TOKEN-REPLACED-9999', 'stored_slug' => 'wycan-alpha' ] ] );
check( 'nouveau jeton remplace l\'ancien', 'TEST-TOKEN-REPLACED-9999' === Crypto::decrypt( $form[0]['api_token'] ) );

check( 'entrée non tableau (null de options.php) : liste vide', [] === Repository::sanitize( null ) );
check( 'écriture par update_option passe par sanitize', ( update_option( Repository::OPTION, [ [ 'slug' => 'x', 'name' => 'X', 'product_id' => '5', 'api_token' => 'TEST-TOKEN-X-0000' ] ], false ) ) && Crypto::is_encrypted( raw_value()[0]['api_token'] ) );

// Restauration.
write_raw( $backup, $backup_autoload ?? 'no' );
update_option( Migrator::OPTION, WFS_VERSION, true );

echo $failures ? "\n{$failures} échec(s).\n" : "\nTous les contrôles passent.\n";
exit( $failures ? 1 : 0 );
