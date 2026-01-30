<?php
/**
 * Output Formatter class.
 *
 * Provides output detection and formatting utilities for script execution results.
 *
 * @package TestScriptManager
 */

namespace TSM\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output Formatter class.
 *
 * Utility class for detecting and formatting different output types:
 * - Table data detection and HTML table generation
 * - Output type detection (JSON, HTML, table, text)
 * - Human-readable memory and time formatting
 *
 * All methods are static (utility class pattern).
 */
class OutputFormatter {

	/**
	 * Check if data is suitable for table display.
	 *
	 * Table data must be:
	 * - A non-empty array
	 * - First element must be array or object
	 * - All elements must have consistent structure (same keys)
	 *
	 * @param mixed $data Data to check.
	 * @return bool True if data is suitable for table display.
	 */
	public static function is_table_data( $data ) {
		// Must be non-empty array.
		if ( ! is_array( $data ) || empty( $data ) ) {
			return false;
		}

		// Get first element.
		$first = reset( $data );

		// First element must be array or object.
		if ( ! is_array( $first ) && ! is_object( $first ) ) {
			return false;
		}

		// Get keys from first element.
		$first_keys = is_object( $first ) ? array_keys( get_object_vars( $first ) ) : array_keys( $first );

		if ( empty( $first_keys ) ) {
			return false;
		}

		// Check all elements have same keys (consistent structure).
		foreach ( $data as $item ) {
			if ( is_object( $item ) ) {
				$item = get_object_vars( $item );
			}

			if ( ! is_array( $item ) ) {
				return false;
			}

			$item_keys = array_keys( $item );

			// Keys must match (order doesn't matter).
			$diff1 = array_diff( $first_keys, $item_keys );
			$diff2 = array_diff( $item_keys, $first_keys );

			if ( ! empty( $diff1 ) || ! empty( $diff2 ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Format array data as HTML table.
	 *
	 * @param array $data Array of arrays/objects to format.
	 * @return string HTML table markup.
	 */
	public static function format_as_table( $data ) {
		if ( ! self::is_table_data( $data ) ) {
			return '';
		}

		// Get headers from first row.
		$first   = reset( $data );
		$headers = is_object( $first ) ? array_keys( get_object_vars( $first ) ) : array_keys( $first );

		// Build HTML.
		$html = '<div class="tsm-table-wrapper">';
		$html .= '<table class="tsm-result-table">';

		// Header row.
		$html .= '<thead><tr>';
		foreach ( $headers as $header ) {
			$html .= '<th>' . esc_html( $header ) . '</th>';
		}
		$html .= '</tr></thead>';

		// Data rows.
		$html .= '<tbody>';
		foreach ( $data as $row ) {
			if ( is_object( $row ) ) {
				$row = get_object_vars( $row );
			}

			$html .= '<tr>';
			foreach ( $headers as $header ) {
				$value = isset( $row[ $header ] ) ? $row[ $header ] : null;
				$html .= '<td>' . esc_html( self::format_cell_value( $value ) ) . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody>';

		$html .= '</table>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Format a cell value for display.
	 *
	 * @param mixed $value Cell value.
	 * @return string Formatted value.
	 */
	public static function format_cell_value( $value ) {
		if ( null === $value ) {
			return 'NULL';
		}

		if ( true === $value ) {
			return 'true';
		}

		if ( false === $value ) {
			return 'false';
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			return wp_json_encode( $value );
		}

		return (string) $value;
	}

	/**
	 * Detect the type of output content.
	 *
	 * @param string $output Output string to analyze.
	 * @return string Output type: 'json', 'table', 'html', or 'text'.
	 */
	public static function detect_output_type( $output ) {
		if ( empty( $output ) ) {
			return 'text';
		}

		$trimmed = trim( $output );

		// Check for JSON.
		if ( self::is_json( $trimmed ) ) {
			return 'json';
		}

		// Check for table HTML.
		if ( stripos( $trimmed, '<table' ) === 0 || strpos( $trimmed, '<div class="tsm-table-wrapper">' ) === 0 ) {
			return 'table';
		}

		// Check for general HTML.
		if ( preg_match( '/<[a-z][\s\S]*>/i', $trimmed ) ) {
			return 'html';
		}

		return 'text';
	}

	/**
	 * Check if string is valid JSON.
	 *
	 * @param string $string String to check.
	 * @return bool True if valid JSON.
	 */
	public static function is_json( $string ) {
		if ( ! is_string( $string ) ) {
			return false;
		}

		$string = trim( $string );

		// Must start with { or [.
		if ( strlen( $string ) < 2 ) {
			return false;
		}

		$first_char = $string[0];
		if ( '{' !== $first_char && '[' !== $first_char ) {
			return false;
		}

		// Attempt decode.
		json_decode( $string );

		return ( json_last_error() === JSON_ERROR_NONE );
	}

	/**
	 * Format bytes to human-readable string.
	 *
	 * @param int $bytes Number of bytes.
	 * @return string Human-readable size string.
	 */
	public static function format_memory( $bytes ) {
		if ( $bytes < 0 ) {
			$bytes = 0;
		}

		// Use WordPress size_format function.
		return size_format( $bytes, 2 );
	}

	/**
	 * Format seconds to human-readable string.
	 *
	 * @param float $seconds Execution time in seconds.
	 * @return string Human-readable time string.
	 */
	public static function format_time( $seconds ) {
		if ( $seconds < 0 ) {
			$seconds = 0;
		}

		if ( $seconds < 0.001 ) {
			// Sub-millisecond: show as microseconds.
			return sprintf( '%.4f ms', $seconds * 1000 );
		}

		if ( $seconds < 1 ) {
			// Milliseconds.
			return sprintf( '%.2f ms', $seconds * 1000 );
		}

		// Seconds.
		return sprintf( '%.2f s', $seconds );
	}

	/**
	 * Format output for display with syntax highlighting hints.
	 *
	 * @param string $output  Raw output.
	 * @param string $type    Output type from detect_output_type().
	 * @return array Formatted output with metadata.
	 */
	public static function format_for_display( $output, $type = null ) {
		if ( null === $type ) {
			$type = self::detect_output_type( $output );
		}

		return array(
			'raw'      => $output,
			'type'     => $type,
			'language' => self::get_highlight_language( $type ),
			'formatted' => self::apply_formatting( $output, $type ),
		);
	}

	/**
	 * Get Monaco Editor language for output type.
	 *
	 * @param string $type Output type.
	 * @return string Monaco language ID.
	 */
	public static function get_highlight_language( $type ) {
		$languages = array(
			'json'  => 'json',
			'html'  => 'html',
			'table' => 'html',
			'text'  => 'plaintext',
		);

		return isset( $languages[ $type ] ) ? $languages[ $type ] : 'plaintext';
	}

	/**
	 * Apply basic formatting to output.
	 *
	 * @param string $output Raw output.
	 * @param string $type   Output type.
	 * @return string Formatted output.
	 */
	private static function apply_formatting( $output, $type ) {
		if ( 'json' === $type ) {
			// Pretty-print JSON.
			$decoded = json_decode( $output );
			if ( null !== $decoded ) {
				return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			}
		}

		return $output;
	}
}
