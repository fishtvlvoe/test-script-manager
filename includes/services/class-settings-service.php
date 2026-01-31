<?php
/**
 * Settings Service class.
 *
 * Handles plugin settings retrieval, validation, and IP whitelist management.
 *
 * @package TestScriptManager
 */

namespace TSM\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Service class.
 *
 * Provides centralized settings management:
 * - Execution timeout configuration
 * - Output size limits
 * - IP whitelist for execution security
 * - Settings validation and sanitization
 */
class SettingsService {

	/**
	 * Option name for execution timeout (seconds).
	 */
	const OPTION_TIMEOUT = 'tsm_execution_timeout';

	/**
	 * Option name for output limit (bytes).
	 */
	const OPTION_OUTPUT_LIMIT = 'tsm_output_limit';

	/**
	 * Option name for IP whitelist enabled flag.
	 */
	const OPTION_IP_WHITELIST_ENABLED = 'tsm_ip_whitelist_enabled';

	/**
	 * Option name for IP whitelist (newline-separated).
	 */
	const OPTION_IP_WHITELIST = 'tsm_ip_whitelist';

	/**
	 * Default timeout in seconds.
	 */
	const DEFAULT_TIMEOUT = 30;

	/**
	 * Minimum timeout in seconds.
	 */
	const MIN_TIMEOUT = 1;

	/**
	 * Maximum timeout in seconds.
	 */
	const MAX_TIMEOUT = 300;

	/**
	 * Default output limit in bytes (10MB).
	 */
	const DEFAULT_OUTPUT_LIMIT = 10485760;

	/**
	 * Get execution timeout in seconds.
	 *
	 * Retrieves the configured timeout and clamps it to valid range (1-300).
	 *
	 * @return int Timeout in seconds (1-300).
	 */
	public static function get_timeout() {
		$timeout = (int) get_option( self::OPTION_TIMEOUT, self::DEFAULT_TIMEOUT );

		// Clamp to valid range.
		return max( self::MIN_TIMEOUT, min( $timeout, self::MAX_TIMEOUT ) );
	}

	/**
	 * Get output limit in bytes.
	 *
	 * @return int Output limit in bytes.
	 */
	public static function get_output_limit() {
		return (int) get_option( self::OPTION_OUTPUT_LIMIT, self::DEFAULT_OUTPUT_LIMIT );
	}

	/**
	 * Check if IP whitelist is enabled.
	 *
	 * @return bool True if IP whitelist is enabled, false otherwise.
	 */
	public static function is_ip_whitelist_enabled() {
		return (bool) get_option( self::OPTION_IP_WHITELIST_ENABLED, false );
	}

	/**
	 * Get IP whitelist as newline-separated string.
	 *
	 * @return string IP whitelist (one IP per line).
	 */
	public static function get_ip_whitelist() {
		return get_option( self::OPTION_IP_WHITELIST, '' );
	}

	/**
	 * Check if an IP address is whitelisted.
	 *
	 * If whitelist is disabled or empty, all IPs are allowed.
	 * Supports both exact IP matching and CIDR notation.
	 *
	 * @param string|null $ip IP address to check. If null, uses $_SERVER['REMOTE_ADDR'].
	 * @return bool True if IP is whitelisted or whitelist is disabled.
	 */
	public static function is_ip_whitelisted( $ip = null ) {
		// If whitelist is not enabled, allow all.
		if ( ! self::is_ip_whitelist_enabled() ) {
			return true;
		}

		// Get IP to check.
		if ( null === $ip ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}

		// Get whitelist.
		$whitelist_string = self::get_ip_whitelist();
		if ( empty( $whitelist_string ) ) {
			// Empty whitelist means allow all.
			return true;
		}

		// Parse whitelist into array.
		$whitelist = array_filter( array_map( 'trim', explode( "\n", $whitelist_string ) ) );
		if ( empty( $whitelist ) ) {
			return true;
		}

		// Check each entry.
		foreach ( $whitelist as $entry ) {
			// Exact IP match.
			if ( $entry === $ip ) {
				return true;
			}

			// CIDR range match.
			if ( strpos( $entry, '/' ) !== false && self::ip_in_range( $ip, $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an IP is within a CIDR range.
	 *
	 * @param string $ip    IP address to check.
	 * @param string $range CIDR range (e.g., "192.168.1.0/24").
	 * @return bool True if IP is within range.
	 */
	private static function ip_in_range( $ip, $range ) {
		// Validate CIDR format.
		if ( strpos( $range, '/' ) === false ) {
			return false;
		}

		list( $subnet, $bits ) = explode( '/', $range, 2 );

		// Validate subnet IP.
		if ( ! filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		// Validate bits.
		$bits = (int) $bits;
		if ( $bits < 0 || $bits > 32 ) {
			return false;
		}

		// Validate IP to check.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		// Convert to long integers.
		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );

		// Calculate mask.
		$mask = -1 << ( 32 - $bits );

		// Apply mask and compare.
		return ( $ip_long & $mask ) === ( $subnet_long & $mask );
	}

	/**
	 * Sanitize IP whitelist input.
	 *
	 * Validates each line and keeps only valid IPs or CIDR ranges.
	 * Used as sanitize callback for Settings API.
	 *
	 * @param string $value Raw input value.
	 * @return string Sanitized whitelist (one valid IP/CIDR per line).
	 */
	public static function sanitize_ip_whitelist( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$lines      = explode( "\n", $value );
		$valid_ips  = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			// Check if valid IP.
			if ( filter_var( $line, FILTER_VALIDATE_IP ) ) {
				$valid_ips[] = $line;
				continue;
			}

			// Check if valid CIDR.
			if ( self::is_valid_cidr( $line ) ) {
				$valid_ips[] = $line;
			}
		}

		return implode( "\n", $valid_ips );
	}

	/**
	 * Validate CIDR notation.
	 *
	 * @param string $cidr CIDR string to validate (e.g., "192.168.1.0/24").
	 * @return bool True if valid CIDR notation.
	 */
	private static function is_valid_cidr( $cidr ) {
		// Must contain a slash.
		if ( strpos( $cidr, '/' ) === false ) {
			return false;
		}

		$parts = explode( '/', $cidr, 2 );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		list( $ip, $bits ) = $parts;

		// Validate IP part.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		// Validate bits.
		if ( ! is_numeric( $bits ) ) {
			return false;
		}

		$bits = (int) $bits;

		// IPv4 CIDR range: 0-32.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return $bits >= 0 && $bits <= 32;
		}

		// IPv6 CIDR range: 0-128.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $bits >= 0 && $bits <= 128;
		}

		return false;
	}

	/**
	 * Sanitize timeout value.
	 *
	 * Clamps value to valid range (1-300 seconds).
	 * Used as sanitize callback for Settings API.
	 *
	 * @param mixed $value Raw input value.
	 * @return int Sanitized timeout (1-300).
	 */
	public static function sanitize_timeout( $value ) {
		$timeout = (int) $value;
		return max( self::MIN_TIMEOUT, min( $timeout, self::MAX_TIMEOUT ) );
	}

	/**
	 * Sanitize output limit value.
	 *
	 * Ensures value is a positive integer (in bytes).
	 * Used as sanitize callback for Settings API.
	 *
	 * @param mixed $value Raw input value.
	 * @return int Sanitized output limit in bytes.
	 */
	public static function sanitize_output_limit( $value ) {
		$limit = (int) $value;
		return max( 1048576, $limit ); // Minimum 1MB.
	}

	/**
	 * Sanitize boolean value for IP whitelist enabled.
	 *
	 * @param mixed $value Raw input value.
	 * @return bool Sanitized boolean.
	 */
	public static function sanitize_boolean( $value ) {
		return (bool) $value;
	}
}
