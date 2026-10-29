<?php
/**
 * Outcome of a Freemius API call.
 *
 * @package Wycan\FreemiusStats\Api
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApiResult {

	public const ERROR_UNAUTHORIZED = 'unauthorized';
	public const ERROR_NOT_FOUND    = 'not_found';
	public const ERROR_NETWORK      = 'network';
	public const ERROR_HTTP         = 'http';
	public const ERROR_INVALID      = 'invalid';

	/**
	 * @param array<string, mixed> $data Decoded JSON body on success.
	 */
	private function __construct(
		public readonly bool $ok,
		public readonly array $data,
		public readonly string $error,
		public readonly string $message,
		public readonly int $status,
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function success( array $data, int $status = 200 ): self {
		return new self( true, $data, '', '', $status );
	}

	public static function failure( string $error, string $message, int $status = 0 ): self {
		return new self( false, [], $error, $message, $status );
	}
}
