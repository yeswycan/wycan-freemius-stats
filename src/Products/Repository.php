<?php
/**
 * Products option: reading, cleaning, order.
 *
 * @package Wycan\FreemiusStats\Products
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Products;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Security\TokenStore;

/**
 * Option `wycan_freemius_products`: a list of { slug, name, product_id, api_token }, same
 * structure as 1.1.0, token encrypted. The order is a public contract: the first product is
 * the default of the Elementor widget (`product_slug` control), so it is never reordered.
 */
final class Repository {

	public const OPTION = 'wycan_freemius_products';
	public const GROUP  = 'wycan_freemius_stats';

	/** @var array<int, array{slug: string, name: string, product_id: string, api_token: string}>|null */
	private ?array $cache = null;

	public function register_setting(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ self::class, 'sanitize' ],
				'show_in_rest'      => false,
				'default'           => [],
			]
		);
		add_action( 'update_option_' . self::OPTION, [ $this, 'flush' ] );
		add_action( 'add_option_' . self::OPTION, [ $this, 'flush' ] );
	}

	public function flush(): void {
		$this->cache = null;
	}

	/**
	 * @return array<int, array{slug: string, name: string, product_id: string, api_token: string}>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$this->cache = self::normalize( get_option( self::OPTION, [] ) );
		}
		return $this->cache;
	}

	/**
	 * @return array{slug: string, name: string, product_id: string, api_token: string}|null
	 */
	public function find( string $slug ): ?array {
		foreach ( $this->all() as $product ) {
			if ( $product['slug'] === $slug ) {
				return $product;
			}
		}
		return null;
	}

	/**
	 * Read-side normalization: tolerates the 1.1.0 shapes (holes, missing keys, corrupted
	 * option) without writing anything. Writing goes through sanitize().
	 *
	 * @param mixed $raw
	 * @return array<int, array{slug: string, name: string, product_id: string, api_token: string}>
	 */
	public static function normalize( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$list = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$list[] = [
				'slug'       => self::text( $row['slug'] ?? '' ),
				'name'       => self::text( $row['name'] ?? '' ),
				'product_id' => self::text( $row['product_id'] ?? '' ),
				'api_token'  => self::text( $row['api_token'] ?? '' ),
			];
		}
		return $list;
	}

	/**
	 * sanitize_callback of the option: runs on every write (form, migration, code).
	 *
	 * Row keys: slug, name, product_id, api_token (new token, plain or already encrypted),
	 * api_token_delete, stored_slug (slug of the row when the form was rendered, to keep the
	 * saved token when the field is left empty). Empty rows are dropped, slugs are unique,
	 * the order of the submitted rows is kept and the list is reindexed.
	 *
	 * @param mixed $input
	 * @return array<int, array{slug: string, name: string, product_id: string, api_token: string}>
	 */
	public static function sanitize( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$stored = [];
		foreach ( self::normalize( get_option( self::OPTION, [] ) ) as $product ) {
			$stored[ $product['slug'] ] = $product;
		}

		$clean = [];
		$seen  = [];
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$slug   = sanitize_title( self::text( $row['slug'] ?? '' ) );
			$name   = sanitize_text_field( self::text( $row['name'] ?? '' ) );
			$id_raw = self::text( $row['product_id'] ?? '' );
			$token  = self::text( $row['api_token'] ?? '' );
			$delete = ! empty( $row['api_token_delete'] );

			if ( '' === $slug && '' === $name && '' === $id_raw && '' === $token ) {
				continue;
			}
			if ( '' === $slug ) {
				$slug = sanitize_title( $name );
			}
			if ( '' === $slug ) {
				self::error( 'wfs_slug_missing', __( 'A product without a slug was ignored.', 'wycan-freemius-stats' ) );
				continue;
			}
			if ( isset( $seen[ $slug ] ) ) {
				self::error(
					'wfs_slug_duplicate',
					sprintf(
						/* translators: %s: product slug */
						__( 'The slug "%s" is used twice: only the first product was kept.', 'wycan-freemius-stats' ),
						$slug
					)
				);
				continue;
			}
			$seen[ $slug ] = true;

			$product_id = ctype_digit( $id_raw ) && (int) $id_raw > 0 ? (string) (int) $id_raw : '';
			if ( '' === $product_id && '' !== $id_raw ) {
				self::error(
					'wfs_product_id_invalid',
					sprintf(
						/* translators: %s: product slug */
						__( 'The Freemius product ID of "%s" must be a positive whole number.', 'wycan-freemius-stats' ),
						$slug
					)
				);
			}

			$stored_slug = sanitize_title( self::text( $row['stored_slug'] ?? $slug ) );
			$previous    = $stored[ $stored_slug ]['api_token'] ?? '';

			if ( $delete ) {
				$api_token = '';
			} elseif ( '' !== $token ) {
				$api_token = TokenStore::is_sealed( $token ) ? $token : TokenStore::seal( $token );
			} elseif ( '' !== $previous && ! TokenStore::is_sealed( $previous ) ) {
				// Plain token inherited from 1.1.0: sealed on first write.
				$api_token = TokenStore::seal( $previous );
			} else {
				$api_token = $previous;
			}

			$clean[] = [
				'slug'       => $slug,
				'name'       => $name,
				'product_id' => $product_id,
				'api_token'  => $api_token,
			];
		}

		return $clean;
	}

	private static function text( mixed $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	private static function error( string $code, string $message ): void {
		// Only defined on admin screens; the migration and programmatic writes run without it.
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( self::OPTION, $code, $message );
		}
	}
}
