<?php
/**
 * Freemius API client (product scope, Bearer token).
 *
 * @package Wycan\FreemiusStats\Api
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Client {

	public const BASE_URL = 'https://api.freemius.com/v1';

	/**
	 * GET /products/{id}/installs/count.json
	 */
	public function installs_count( string $product_id, string $token, bool $active_only, int $timeout ): ApiResult {
		$path   = '/products/' . rawurlencode( $product_id ) . '/installs/count.json' . ( $active_only ? '?is_active=true' : '' );
		$result = $this->get( $path, $token, $timeout );
		if ( ! $result->ok ) {
			return $result;
		}

		$count = $result->data['count'] ?? null;
		if ( is_string( $count ) && ctype_digit( $count ) ) {
			$count = (int) $count;
		}
		if ( ! is_int( $count ) || $count < 0 ) {
			return ApiResult::failure( ApiResult::ERROR_INVALID, 'The response has no valid "count" field.', $result->status );
		}

		return ApiResult::success( [ 'count' => $count ], $result->status );
	}

	/**
	 * GET /products/{id}.json (name and slug of the product).
	 */
	public function product( string $product_id, string $token, int $timeout ): ApiResult {
		$result = $this->get( '/products/' . rawurlencode( $product_id ) . '.json', $token, $timeout );
		if ( ! $result->ok ) {
			return $result;
		}
		$title = $result->data['title'] ?? null;
		$slug  = $result->data['slug'] ?? null;
		if ( ! is_string( $title ) || ! is_string( $slug ) ) {
			return ApiResult::failure( ApiResult::ERROR_INVALID, 'The response has no "title" and "slug" fields.', $result->status );
		}
		return ApiResult::success( [ 'title' => $title, 'slug' => $slug ], $result->status );
	}

	/**
	 * GET /products/{id}/tags/latest.json?type=released (latest published version only).
	 */
	public function latest_release( string $product_id, string $token, int $timeout ): ApiResult {
		$result = $this->get( '/products/' . rawurlencode( $product_id ) . '/tags/latest.json?type=released', $token, $timeout );
		if ( ! $result->ok ) {
			return $result;
		}
		$version = $result->data['version'] ?? null;
		if ( ! is_string( $version ) || '' === $version ) {
			return ApiResult::failure( ApiResult::ERROR_INVALID, 'The response has no "version" field.', $result->status );
		}
		return ApiResult::success(
			[
				'version'  => $version,
				'date'     => is_string( $result->data['created'] ?? null ) ? $result->data['created'] : '',
				'requires' => is_string( $result->data['requires_platform_version'] ?? null ) ? $result->data['requires_platform_version'] : '',
				'tested'   => is_string( $result->data['tested_up_to_version'] ?? null ) ? $result->data['tested_up_to_version'] : '',
			],
			$result->status
		);
	}

	private function get( string $path, string $token, int $timeout ): ApiResult {
		$response = wp_remote_get(
			self::BASE_URL . $path,
			[
				'timeout' => max( 1, $timeout ),
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return ApiResult::failure( ApiResult::ERROR_NETWORK, $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : [];

		if ( 401 === $status || 403 === $status ) {
			return ApiResult::failure( ApiResult::ERROR_UNAUTHORIZED, $this->api_message( $body, 'Token refused.' ), $status );
		}
		if ( 404 === $status ) {
			return ApiResult::failure( ApiResult::ERROR_NOT_FOUND, $this->api_message( $body, 'Product not found.' ), $status );
		}
		if ( 200 !== $status ) {
			return ApiResult::failure( 0 === $status || $status >= 500 ? ApiResult::ERROR_NETWORK : ApiResult::ERROR_HTTP, $this->api_message( $body, 'HTTP ' . $status ), $status );
		}
		if ( [] === $body ) {
			return ApiResult::failure( ApiResult::ERROR_INVALID, 'The response is not valid JSON.', $status );
		}

		return ApiResult::success( $body, $status );
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private function api_message( array $body, string $fallback ): string {
		$message = $body['error']['message'] ?? null;
		return is_string( $message ) && '' !== $message ? $message : $fallback;
	}
}
