<?php
/**
 * Export API class.
 *
 * REST API endpoint for exporting execution results
 * in CSV, JSON, and Excel formats.
 *
 * @package TestScriptManager
 */

namespace TSM\API;

use TSM\Security;
use TSM\Services\ExportService;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export API class.
 *
 * Provides REST endpoint for downloading execution results:
 * - GET /test-script-manager/v1/executions/{id}/export?format=csv|json|excel
 *
 * Downloads are triggered by setting appropriate Content-Disposition headers.
 */
class Export_API {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'test-script-manager/v1';

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/executions/(?P<id>\d+)/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_execution' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'Execution ID.', 'test-script-manager' ),
					),
					'format' => array(
						'default'           => 'csv',
						'type'              => 'string',
						'enum'              => array( 'csv', 'json', 'excel' ),
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Export format: csv, json, or excel.', 'test-script-manager' ),
					),
				),
			)
		);
	}

	/**
	 * Export execution result.
	 *
	 * Streams the export file directly to browser with appropriate headers.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 */
	public function export_execution( $request ) {
		$execution_id = $request->get_param( 'id' );
		$format       = $request->get_param( 'format' );

		// Fetch execution data.
		$execution_data = ExportService::fetch_execution_data( $execution_id );
		if ( null === $execution_data ) {
			return new \WP_Error(
				'tsm_execution_not_found',
				__( 'Execution not found.', 'test-script-manager' ),
				array( 'status' => 404 )
			);
		}

		// Clear any accidental output.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Generate export content based on format.
		switch ( $format ) {
			case 'json':
				$content      = ExportService::export_json( $execution_data );
				$content_type = 'application/json; charset=utf-8';
				$extension    = 'json';
				break;

			case 'excel':
				$content = ExportService::export_excel( $execution_data );
				// Check if PhpSpreadsheet was used or fell back to CSV.
				if ( class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
					$content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
					$extension    = 'xlsx';
				} else {
					// Fallback to CSV.
					$content_type = 'text/csv; charset=utf-8';
					$extension    = 'csv';
				}
				break;

			case 'csv':
			default:
				$content      = ExportService::export_csv( $execution_data );
				$content_type = 'text/csv; charset=utf-8';
				$extension    = 'csv';
				break;
		}

		// Generate filename.
		$filename = sprintf( 'execution-%d.%s', $execution_id, $extension );

		// Set headers.
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output content.
		echo $content;

		// Exit to prevent WordPress from adding JSON wrapper.
		exit;
	}
}
