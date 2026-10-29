<?php
/**
 * "Test connection" action (admin-ajax), the only asynchronous entry point of the plugin.
 *
 * @package Wycan\FreemiusStats\Admin
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wycan\FreemiusStats\Api\ApiResult;
use Wycan\FreemiusStats\Plugin;
use Wycan\FreemiusStats\Security\TokenStore;

/**
 * Tests the values typed in the form before they are saved: product ID and token (or the saved
 * token of `stored_slug` when the field is empty). Nothing is stored. Returns the product name
 * and slug (used to prefill a new product), the active and total install counts.
 */
final class ConnectionTest {

	public const ACTION  = 'wfs_test_connection';
	private const TIMEOUT = 10;

	public function __construct( private readonly Plugin $plugin ) {}

	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
	}

	public function handle(): void {
		check_ajax_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to do this.', 'wycan-freemius-stats' ) ], 403 );
		}

		$product_id  = isset( $_POST['product_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) ) : '';
		$token       = isset( $_POST['token'] ) ? trim( (string) wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- secret, compared and sent as is, never output.
		$stored_slug = isset( $_POST['stored_slug'] ) ? sanitize_title( wp_unslash( $_POST['stored_slug'] ) ) : '';

		if ( ! ctype_digit( $product_id ) || (int) $product_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Enter the Freemius product ID (a whole number).', 'wycan-freemius-stats' ) ] );
		}

		if ( '' === $token ) {
			$stored = '' !== $stored_slug ? $this->plugin->products->find( $stored_slug ) : null;
			$token  = null !== $stored ? TokenStore::get( [ 'product_id' => $product_id, 'api_token' => $stored['api_token'] ] ) : null;
			if ( null === $token ) {
				wp_send_json_error( [ 'message' => __( 'Paste the API token to test it.', 'wycan-freemius-stats' ) ] );
			}
		}
		if ( preg_match( '/\s/', $token ) ) {
			wp_send_json_error( [ 'message' => __( 'The token contains spaces: check the copy.', 'wycan-freemius-stats' ) ] );
		}

		$client  = $this->plugin->client;
		$product = $client->product( $product_id, $token, self::TIMEOUT );
		if ( ! $product->ok ) {
			wp_send_json_error( [ 'message' => self::describe( $product ) ] );
		}

		$active = $client->installs_count( $product_id, $token, true, self::TIMEOUT );
		$total  = $client->installs_count( $product_id, $token, false, self::TIMEOUT );
		if ( ! $active->ok || ! $total->ok ) {
			wp_send_json_error( [ 'message' => self::describe( $active->ok ? $total : $active ) ] );
		}

		wp_send_json_success(
			[
				'name'   => $product->data['title'],
				'slug'   => sanitize_title( $product->data['slug'] ),
				'active' => $active->data['count'],
				'total'  => $total->data['count'],
			]
		);
	}

	public static function describe( ApiResult $result ): string {
		return match ( $result->error ) {
			ApiResult::ERROR_UNAUTHORIZED => __( 'Token refused (401): it does not belong to this product, or it was revoked.', 'wycan-freemius-stats' ),
			ApiResult::ERROR_NOT_FOUND    => __( 'Product not found (404): check the product ID.', 'wycan-freemius-stats' ),
			ApiResult::ERROR_NETWORK      => sprintf(
				/* translators: %s: technical detail */
				__( 'Freemius API unreachable: %s', 'wycan-freemius-stats' ),
				$result->message
			),
			ApiResult::ERROR_INVALID      => __( 'Unexpected response from the Freemius API.', 'wycan-freemius-stats' ),
			default                       => sprintf(
				/* translators: 1: HTTP status, 2: message */
				__( 'Freemius API error (HTTP %1$d): %2$s', 'wycan-freemius-stats' ),
				$result->status,
				$result->message
			),
		};
	}
}
