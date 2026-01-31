<?php
/**
 * Export Service class.
 *
 * Provides export functionality for execution results in multiple formats:
 * CSV, JSON, and Excel (XLSX).
 *
 * @package TestScriptManager
 */

namespace TSM\Services;

use TSM\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export Service class.
 *
 * Handles exporting execution results in various formats:
 * - CSV: Comma-separated values with formula injection protection
 * - JSON: Pretty-printed JSON with full execution data
 * - Excel: XLSX format with formatted columns (requires PhpSpreadsheet)
 *
 * All methods return string content that can be sent directly to browser
 * with appropriate Content-Type headers.
 */
class ExportService {

	/**
	 * Fetch execution data by ID.
	 *
	 * Retrieves execution log and joins with script information.
	 *
	 * @param int $execution_id Execution ID.
	 * @return array|null Structured execution data or null if not found.
	 */
	public static function fetch_execution_data( $execution_id ) {
		// Get execution log.
		$execution = ExecutionService::get_execution_log( $execution_id );
		if ( null === $execution ) {
			return null;
		}

		// Get script info.
		$script      = ScriptService::get( $execution['script_id'] );
		$script_name = $script ? $script['name'] : __( 'Unknown Script', 'test-script-manager' );

		// Build structured data.
		return array(
			'id'             => (int) $execution['id'],
			'script_id'      => (int) $execution['script_id'],
			'script_name'    => $script_name,
			'output'         => $execution['output'],
			'error_data'     => $execution['error_data'],
			'execution_time' => (float) $execution['execution_time'],
			'memory_usage'   => (int) $execution['memory_usage'],
			'status'         => $execution['status'],
			'executed_at'    => $execution['executed_at'],
		);
	}

	/**
	 * Export execution data as CSV.
	 *
	 * Creates CSV content with headers and sanitized cell values.
	 * Output is truncated to 1000 characters for readability.
	 *
	 * @param array $execution_data Execution data from fetch_execution_data().
	 * @return string CSV content.
	 */
	public static function export_csv( $execution_data ) {
		ob_start();
		$output = fopen( 'php://output', 'w' );

		// Write UTF-8 BOM for Excel compatibility.
		fwrite( $output, "\xEF\xBB\xBF" );

		// Write headers.
		fputcsv( $output, array(
			__( 'Script Name', 'test-script-manager' ),
			__( 'Executed At', 'test-script-manager' ),
			__( 'Status', 'test-script-manager' ),
			__( 'Execution Time (s)', 'test-script-manager' ),
			__( 'Memory Usage', 'test-script-manager' ),
			__( 'Output', 'test-script-manager' ),
		) );

		// Truncate output for CSV.
		$output_truncated = $execution_data['output'];
		if ( strlen( $output_truncated ) > 1000 ) {
			$output_truncated = substr( $output_truncated, 0, 1000 ) . '... [truncated]';
		}

		// Write data row with sanitized values.
		fputcsv( $output, array(
			self::sanitize_csv_cell( $execution_data['script_name'] ),
			self::sanitize_csv_cell( $execution_data['executed_at'] ),
			self::sanitize_csv_cell( $execution_data['status'] ),
			number_format( $execution_data['execution_time'], 4 ),
			size_format( $execution_data['memory_usage'] ),
			self::sanitize_csv_cell( $output_truncated ),
		) );

		fclose( $output );
		return ob_get_clean();
	}

	/**
	 * Export execution data as JSON.
	 *
	 * Returns pretty-printed JSON with full execution data.
	 *
	 * @param array $execution_data Execution data from fetch_execution_data().
	 * @return string JSON content.
	 */
	public static function export_json( $execution_data ) {
		// Format memory for readability.
		$export_data = array(
			'id'              => $execution_data['id'],
			'script_id'       => $execution_data['script_id'],
			'script_name'     => $execution_data['script_name'],
			'status'          => $execution_data['status'],
			'executed_at'     => $execution_data['executed_at'],
			'execution_time'  => $execution_data['execution_time'],
			'memory_usage'    => $execution_data['memory_usage'],
			'memory_readable' => size_format( $execution_data['memory_usage'] ),
			'output'          => $execution_data['output'],
			'error_data'      => $execution_data['error_data'],
		);

		return wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Export execution data as Excel (XLSX).
	 *
	 * Creates XLSX file with formatted columns and headers.
	 * Falls back to CSV if PhpSpreadsheet is not available.
	 *
	 * @param array $execution_data Execution data from fetch_execution_data().
	 * @return string XLSX content (binary) or CSV content as fallback.
	 */
	public static function export_excel( $execution_data ) {
		// Check if PhpSpreadsheet is available.
		if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
			// Try to load via Composer autoload if available.
			$autoload = TSM_PLUGIN_DIR . 'vendor/autoload.php';
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
		}

		// If still not available, fall back to CSV.
		if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
			// Return CSV as fallback with a notice.
			return self::export_csv( $execution_data );
		}

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();

		// Set headers in row 1.
		$headers = array(
			'A1' => __( 'Script Name', 'test-script-manager' ),
			'B1' => __( 'Executed At', 'test-script-manager' ),
			'C1' => __( 'Status', 'test-script-manager' ),
			'D1' => __( 'Execution Time (s)', 'test-script-manager' ),
			'E1' => __( 'Memory Usage', 'test-script-manager' ),
			'F1' => __( 'Output', 'test-script-manager' ),
		);

		foreach ( $headers as $cell => $value ) {
			$sheet->setCellValue( $cell, $value );
		}

		// Style header row.
		$sheet->getStyle( 'A1:F1' )->getFont()->setBold( true );
		$sheet->getStyle( 'A1:F1' )->getFill()
			->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
			->getStartColor()->setARGB( 'FFEEEEEE' );

		// Set data in row 2.
		$sheet->setCellValue( 'A2', $execution_data['script_name'] );
		$sheet->setCellValue( 'B2', $execution_data['executed_at'] );
		$sheet->setCellValue( 'C2', $execution_data['status'] );
		$sheet->setCellValue( 'D2', number_format( $execution_data['execution_time'], 4 ) );
		$sheet->setCellValue( 'E2', size_format( $execution_data['memory_usage'] ) );

		// Truncate output for Excel cell.
		$output_truncated = $execution_data['output'];
		if ( strlen( $output_truncated ) > 1000 ) {
			$output_truncated = substr( $output_truncated, 0, 1000 ) . '... [truncated]';
		}
		$sheet->setCellValue( 'F2', $output_truncated );

		// Set column widths.
		$sheet->getColumnDimension( 'A' )->setWidth( 20 );
		$sheet->getColumnDimension( 'B' )->setWidth( 20 );
		$sheet->getColumnDimension( 'C' )->setWidth( 10 );
		$sheet->getColumnDimension( 'D' )->setWidth( 15 );
		$sheet->getColumnDimension( 'E' )->setWidth( 15 );
		$sheet->getColumnDimension( 'F' )->setWidth( 100 );

		// Write to output.
		ob_start();
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
		$writer->save( 'php://output' );
		return ob_get_clean();
	}

	/**
	 * Sanitize CSV cell value to prevent formula injection.
	 *
	 * Cells starting with =, +, -, @ are prefixed with a tab character
	 * to prevent Excel/Google Sheets from interpreting them as formulas.
	 *
	 * @param string $value Cell value to sanitize.
	 * @return string Sanitized value.
	 */
	private static function sanitize_csv_cell( $value ) {
		$value = (string) $value;

		// Check for dangerous formula characters at start.
		if ( strlen( $value ) > 0 && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
			// Prefix with tab character to prevent formula interpretation.
			$value = "\t" . $value;
		}

		return $value;
	}
}
