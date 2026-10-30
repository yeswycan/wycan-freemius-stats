<?php
/**
 * Durable values synchronized from Freemius.
 *
 * @package Wycan\FreemiusStats\Counts
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Counts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Api\ApiResult;

/**
 * Option `wycan_freemius_counts` (not autoloaded), keyed by Freemius product ID:
 *   active, all : { value: int, at: timestamp }
 *   version         : { version, date, requires, tested, at }
 *   changelog       : { version, html, truncated, at }
 *   install_id      : string (any install of the product, needed by the update endpoint)
 *   error           : { code, message, status, at }  (last failure, for the settings screen)
 *   changelog_error : { code, message, status, at }  (kept apart: it does not affect the counts)
 *   failed          : { active|all|version|changelog: timestamp } (negative cache)
 */
final class CountStore {

	public const OPTION = 'wycan_freemius_counts';

	public const FIELD_ACTIVE    = 'active';
	public const FIELD_ALL       = 'all';
	public const FIELD_VERSION   = 'version';
	public const FIELD_CHANGELOG = 'changelog';

	/** @var array<string, array<string, mixed>>|null */
	private ?array $cache = null;

	public static function field( bool $active_only ): string {
		return $active_only ? self::FIELD_ACTIVE : self::FIELD_ALL;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$raw         = get_option( self::OPTION, [] );
			$this->cache = is_array( $raw ) ? $raw : [];
		}
		return $this->cache;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function entry( string $product_id ): array {
		$entry = $this->all()[ $product_id ] ?? [];
		return is_array( $entry ) ? $entry : [];
	}

	public function count( string $product_id, bool $active_only ): ?int {
		$value = $this->entry( $product_id )[ self::field( $active_only ) ]['value'] ?? null;
		return is_int( $value ) ? $value : null;
	}

	public function synced_at( string $product_id, string $field ): int {
		$at = $this->entry( $product_id )[ $field ]['at'] ?? 0;
		return is_int( $at ) ? $at : 0;
	}

	/**
	 * @return array{version: string, date: string, requires: string, tested: string}|null
	 */
	public function version( string $product_id ): ?array {
		$data = $this->entry( $product_id )[ self::FIELD_VERSION ] ?? null;
		if ( ! is_array( $data ) || ! is_string( $data['version'] ?? null ) ) {
			return null;
		}
		return [
			'version'  => $data['version'],
			'date'     => (string) ( $data['date'] ?? '' ),
			'requires' => (string) ( $data['requires'] ?? '' ),
			'tested'   => (string) ( $data['tested'] ?? '' ),
		];
	}

	/**
	 * @return array{version: string, html: string, truncated: bool, at: int}|null
	 */
	public function changelog( string $product_id ): ?array {
		$data = $this->entry( $product_id )[ self::FIELD_CHANGELOG ] ?? null;
		if ( ! is_array( $data ) || ! is_string( $data['version'] ?? null ) || ! is_string( $data['html'] ?? null ) ) {
			return null;
		}
		return [
			'version'   => $data['version'],
			'html'      => $data['html'],
			'truncated' => true === ( $data['truncated'] ?? false ),
			'at'        => (int) ( $data['at'] ?? 0 ),
		];
	}

	public function install_id( string $product_id ): ?string {
		$id = $this->entry( $product_id )['install_id'] ?? null;
		return is_string( $id ) && '' !== $id ? $id : null;
	}

	/**
	 * @return array{code: string, message: string, status: int, at: int}|null
	 */
	public function error( string $product_id ): ?array {
		return $this->read_error( $product_id, 'error' );
	}

	/**
	 * @return array{code: string, message: string, status: int, at: int}|null
	 */
	public function changelog_error( string $product_id ): ?array {
		return $this->read_error( $product_id, 'changelog_error' );
	}

	/**
	 * @return array{code: string, message: string, status: int, at: int}|null
	 */
	private function read_error( string $product_id, string $slot ): ?array {
		$error = $this->entry( $product_id )[ $slot ] ?? null;
		if ( ! is_array( $error ) ) {
			return null;
		}
		return [
			'code'    => (string) ( $error['code'] ?? '' ),
			'message' => (string) ( $error['message'] ?? '' ),
			'status'  => (int) ( $error['status'] ?? 0 ),
			'at'      => (int) ( $error['at'] ?? 0 ),
		];
	}

	public function set_count( string $product_id, bool $active_only, int $value ): void {
		$entry                                 = $this->entry( $product_id );
		$entry[ self::field( $active_only ) ] = [ 'value' => $value, 'at' => time() ];
		unset( $entry['failed'][ self::field( $active_only ) ] );
		$this->save( $product_id, $entry );
	}

	/**
	 * @param array{version: string, date: string, requires: string, tested: string} $data
	 */
	public function set_version( string $product_id, array $data ): void {
		$entry                         = $this->entry( $product_id );
		$entry[ self::FIELD_VERSION ] = $data + [ 'at' => time() ];
		unset( $entry['failed'][ self::FIELD_VERSION ] );
		$this->save( $product_id, $entry );
	}

	public function set_changelog( string $product_id, string $version, string $html, bool $truncated ): void {
		$entry                           = $this->entry( $product_id );
		$entry[ self::FIELD_CHANGELOG ] = [ 'version' => $version, 'html' => $html, 'truncated' => $truncated, 'at' => time() ];
		unset( $entry['failed'][ self::FIELD_CHANGELOG ], $entry['changelog_error'] );
		$this->save( $product_id, $entry );
	}

	public function set_install_id( string $product_id, string $install_id ): void {
		$entry               = $this->entry( $product_id );
		$entry['install_id'] = $install_id;
		$this->save( $product_id, $entry );
	}

	public function record_failure( string $product_id, string $field, ApiResult $result ): void {
		$slot                      = self::FIELD_CHANGELOG === $field ? 'changelog_error' : 'error';
		$entry                     = $this->entry( $product_id );
		$entry['failed'][ $field ] = time();
		$entry[ $slot ]            = [
			'code'    => $result->error,
			'message' => $result->message,
			'status'  => $result->status,
			'at'      => time(),
		];
		$this->save( $product_id, $entry );
	}

	public function clear_error( string $product_id ): void {
		$entry = $this->entry( $product_id );
		if ( ! isset( $entry['error'] ) ) {
			return;
		}
		unset( $entry['error'] );
		$this->save( $product_id, $entry );
	}

	public function failed_recently( string $product_id, string $field, int $window ): bool {
		$at = $this->entry( $product_id )['failed'][ $field ] ?? 0;
		return is_int( $at ) && $at > time() - $window;
	}

	/**
	 * Drops the values of products that no longer exist.
	 *
	 * @param string[] $product_ids
	 */
	public function prune( array $product_ids ): void {
		$all  = $this->all();
		$keep = array_intersect_key( $all, array_flip( $product_ids ) );
		if ( count( $keep ) !== count( $all ) ) {
			$this->cache = $keep;
			update_option( self::OPTION, $keep, false );
		}
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	private function save( string $product_id, array $entry ): void {
		$all                = $this->all();
		$all[ $product_id ] = $entry;
		$this->cache        = $all;
		update_option( self::OPTION, $all, false );
	}
}
