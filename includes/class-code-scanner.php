<?php
/**
 * Code Scanner class for dangerous function detection.
 *
 * Uses PHP tokenizer to accurately detect dangerous function calls,
 * avoiding false positives from comments or strings.
 *
 * @package TestScriptManager
 */

namespace TSM;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CodeScanner class.
 *
 * Scans PHP code for dangerous functions using the tokenizer.
 * This approach is more accurate than regex because it:
 * - Ignores function names in comments
 * - Ignores function names in strings
 * - Only detects actual function calls
 */
class CodeScanner {

	/**
	 * List of dangerous functions that can execute arbitrary code
	 * or shell commands.
	 *
	 * @var array
	 */
	private static $dangerous_functions = array(
		'eval',
		'exec',
		'system',
		'shell_exec',
		'passthru',
		'popen',
		'proc_open',
		'pcntl_exec',
		'create_function', // Deprecated in PHP 7.2, removed in PHP 8.0.
	);

	/**
	 * Token types that represent dangerous language constructs.
	 *
	 * Note: eval is a language construct (T_EVAL), not a function (T_STRING).
	 *
	 * @var array
	 */
	private static $dangerous_token_types = array(
		T_EVAL   => 'eval',
		T_EXIT   => 'exit', // exit/die can be used to abort execution.
		T_INCLUDE => 'include',
		T_INCLUDE_ONCE => 'include_once',
		T_REQUIRE => 'require',
		T_REQUIRE_ONCE => 'require_once',
	);

	/**
	 * Scan code for dangerous function calls.
	 *
	 * Uses PHP tokenizer to parse the code and find function calls
	 * that match the dangerous functions list.
	 *
	 * @param string $code PHP code to scan (without <?php tag).
	 *
	 * @return array List of dangerous functions found (unique, sorted).
	 */
	public static function scan( $code ) {
		// Prepend <?php if not present for tokenizer.
		$code_to_tokenize = $code;
		if ( strpos( trim( $code ), '<?php' ) !== 0 && strpos( trim( $code ), '<?' ) !== 0 ) {
			$code_to_tokenize = '<?php ' . $code;
		}

		// Get tokens.
		$tokens = token_get_all( $code_to_tokenize );

		$found_functions = array();

		foreach ( $tokens as $token ) {
			// Skip string tokens (single characters like '(', ';').
			if ( ! is_array( $token ) ) {
				continue;
			}

			$token_type = $token[0];
			$token_value = isset( $token[1] ) ? strtolower( $token[1] ) : '';

			// Check for dangerous language constructs (eval, exit, include, etc.).
			if ( isset( self::$dangerous_token_types[ $token_type ] ) ) {
				// Only include eval in the dangerous list (others are less dangerous).
				if ( T_EVAL === $token_type ) {
					$found_functions[] = 'eval';
				}
				continue;
			}

			// Check if it's a string token (function name, variable, constant, etc.).
			if ( T_STRING === $token_type ) {
				if ( in_array( $token_value, self::$dangerous_functions, true ) ) {
					$found_functions[] = $token_value;
				}
			}
		}

		// Remove duplicates and sort for consistent output.
		$unique = array_unique( $found_functions );
		sort( $unique );

		return array_values( $unique );
	}

	/**
	 * Check if dangerous functions should be blocked.
	 *
	 * In production (WP_DEBUG = false), dangerous functions are blocked.
	 * In development (WP_DEBUG = true), they are allowed.
	 *
	 * @return bool True if dangerous functions should be blocked.
	 */
	public static function should_block_dangerous() {
		// Block if WP_DEBUG is not defined or is false.
		return ! ( defined( 'WP_DEBUG' ) && WP_DEBUG );
	}

	/**
	 * Validate code for dangerous function usage.
	 *
	 * Combines scan() and should_block_dangerous() to provide
	 * a complete validation result.
	 *
	 * @param string $code PHP code to validate.
	 *
	 * @return array {
	 *     Validation result.
	 *
	 *     @type bool        $valid              True if code is safe to execute.
	 *     @type array       $dangerous_functions List of dangerous functions found.
	 *     @type string|null $message            Error message if invalid, null if valid.
	 *     @type bool        $debug_mode         Whether WP_DEBUG is enabled.
	 * }
	 */
	public static function validate_code( $code ) {
		$dangerous = self::scan( $code );
		$should_block = self::should_block_dangerous();

		// If no dangerous functions found, always valid.
		if ( empty( $dangerous ) ) {
			return array(
				'valid'               => true,
				'dangerous_functions' => array(),
				'message'             => null,
				'debug_mode'          => ! $should_block,
			);
		}

		// Dangerous functions found.
		if ( $should_block ) {
			// Production mode: block execution.
			return array(
				'valid'               => false,
				'dangerous_functions' => $dangerous,
				'message'             => sprintf(
					/* translators: %s: comma-separated list of dangerous function names */
					__( 'Code contains dangerous functions that are blocked in production: %s', 'test-script-manager' ),
					implode( ', ', $dangerous )
				),
				'debug_mode'          => false,
			);
		}

		// Debug mode: allow but warn.
		return array(
			'valid'               => true,
			'dangerous_functions' => $dangerous,
			'message'             => sprintf(
				/* translators: %s: comma-separated list of dangerous function names */
				__( 'Warning: Code contains dangerous functions (allowed in debug mode): %s', 'test-script-manager' ),
				implode( ', ', $dangerous )
			),
			'debug_mode'          => true,
		);
	}

	/**
	 * Get list of all dangerous functions.
	 *
	 * Useful for documentation or UI display.
	 *
	 * @return array List of dangerous function names.
	 */
	public static function get_dangerous_functions_list() {
		return self::$dangerous_functions;
	}
}
