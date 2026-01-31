<?php
/**
 * Execution Result Page Template.
 *
 * Displays execution results in a standalone page with:
 * - Header with script name, status badge, execution time, memory usage
 * - Errors section (if any errors, exceptions, or fatal errors)
 * - Output section with type-aware formatting (JSON, HTML, table, text)
 * - Footer with execution timestamp
 *
 * @package TestScriptManager
 * @var array $data Template data from Result_Page::render()
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TSM\Services\OutputFormatter;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( sprintf( __( 'Execution Result: %s', 'test-script-manager' ), $data['script_name'] ) ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="tsm-result-page">
	<div class="tsm-result-container">
		<!-- Header -->
		<header class="tsm-result-header">
			<h1><?php echo esc_html( $data['script_name'] ); ?></h1>
			<div class="tsm-result-meta">
				<span class="tsm-status tsm-status-<?php echo esc_attr( $data['status'] ); ?>">
					<?php echo esc_html( ucfirst( str_replace( '_', ' ', $data['status'] ) ) ); ?>
				</span>
				<span class="tsm-time">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
					</svg>
					<?php echo esc_html( OutputFormatter::format_time( $data['execution_time'] ) ); ?>
				</span>
				<span class="tsm-memory">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
						<path d="M13 7H7v6h6V7z"/>
						<path fill-rule="evenodd" d="M7 2a1 1 0 012 0v1h2V2a1 1 0 112 0v1h2a2 2 0 012 2v2h1a1 1 0 110 2h-1v2h1a1 1 0 110 2h-1v2a2 2 0 01-2 2h-2v1a1 1 0 11-2 0v-1H9v1a1 1 0 11-2 0v-1H5a2 2 0 01-2-2v-2H2a1 1 0 110-2h1V9H2a1 1 0 010-2h1V5a2 2 0 012-2h2V2zM5 5h10v10H5V5z" clip-rule="evenodd"/>
					</svg>
					<?php echo esc_html( size_format( $data['memory_usage'] ) ); ?>
				</span>
			</div>
		</header>

		<!-- Export Buttons -->
		<div class="tsm-export-buttons">
			<button type="button" class="button tsm-export-btn" data-format="csv">
				<span class="dashicons dashicons-media-spreadsheet"></span>
				<?php esc_html_e( 'Export CSV', 'test-script-manager' ); ?>
			</button>
			<button type="button" class="button tsm-export-btn" data-format="json">
				<span class="dashicons dashicons-media-code"></span>
				<?php esc_html_e( 'Export JSON', 'test-script-manager' ); ?>
			</button>
			<button type="button" class="button tsm-export-btn" data-format="excel">
				<span class="dashicons dashicons-media-document"></span>
				<?php esc_html_e( 'Export Excel', 'test-script-manager' ); ?>
			</button>
		</div>

		<!-- Errors Section -->
		<?php if ( ! empty( $data['errors'] ) || $data['exception'] || $data['fatal'] ) : ?>
		<section class="tsm-errors">
			<h2><?php esc_html_e( 'Errors', 'test-script-manager' ); ?></h2>

			<?php
			// Fatal error.
			if ( $data['fatal'] ) :
				$fatal = $data['fatal'];
				?>
				<div class="tsm-error-item tsm-error-fatal">
					<span class="tsm-error-level"><?php esc_html_e( 'Fatal Error', 'test-script-manager' ); ?></span>
					<span class="tsm-error-message"><?php echo esc_html( $fatal['message'] ); ?></span>
					<span class="tsm-error-location">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: file path, 2: line number */
								__( '%1$s on line %2$d', 'test-script-manager' ),
								$fatal['file'],
								$fatal['line']
							)
						);
						?>
					</span>
				</div>
			<?php endif; ?>

			<?php
			// Exception.
			if ( $data['exception'] ) :
				$exc = $data['exception'];
				?>
				<div class="tsm-error-item tsm-error-exception">
					<span class="tsm-error-level"><?php echo esc_html( $exc['type'] ); ?></span>
					<span class="tsm-error-message"><?php echo esc_html( $exc['message'] ); ?></span>
					<span class="tsm-error-location">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: file path, 2: line number */
								__( '%1$s on line %2$d', 'test-script-manager' ),
								$exc['file'],
								$exc['line']
							)
						);
						?>
					</span>
					<?php if ( ! empty( $exc['trace'] ) ) : ?>
						<div class="tsm-stack-trace"><?php echo esc_html( $exc['trace'] ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			// Regular errors (warnings, notices).
			foreach ( $data['errors'] as $error ) :
				$error_class = 'tsm-error-' . strtolower( str_replace( ' ', '_', $error['level'] ) );
				?>
				<div class="tsm-error-item <?php echo esc_attr( $error_class ); ?>">
					<span class="tsm-error-level"><?php echo esc_html( $error['level'] ); ?></span>
					<span class="tsm-error-message"><?php echo esc_html( $error['message'] ); ?></span>
					<span class="tsm-error-location">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: file path, 2: line number */
								__( '%1$s on line %2$d', 'test-script-manager' ),
								$error['file'],
								$error['line']
							)
						);
						?>
					</span>
				</div>
			<?php endforeach; ?>
		</section>
		<?php endif; ?>

		<!-- Output Section -->
		<section class="tsm-output">
			<h2>
				<?php esc_html_e( 'Output', 'test-script-manager' ); ?>
				<?php if ( ! empty( $data['output'] ) ) : ?>
					<button type="button" class="tsm-copy-output button button-small"><?php esc_html_e( 'Copy', 'test-script-manager' ); ?></button>
				<?php endif; ?>
			</h2>

			<?php if ( empty( $data['output'] ) ) : ?>
				<p class="tsm-empty"><?php esc_html_e( 'No output.', 'test-script-manager' ); ?></p>
			<?php elseif ( 'table' === $data['output_type'] && ! empty( $data['table_html'] ) ) : ?>
				<div class="tsm-table-output"><?php echo wp_kses_post( $data['table_html'] ); ?></div>
			<?php elseif ( 'json' === $data['output_type'] ) : ?>
				<div class="tsm-json-viewer" data-json="<?php echo esc_attr( $data['output'] ); ?>"></div>
			<?php elseif ( 'html' === $data['output_type'] ) : ?>
				<div class="tsm-html-output"><?php echo wp_kses_post( $data['output'] ); ?></div>
			<?php else : ?>
				<pre class="tsm-output-content"><?php echo esc_html( $data['output'] ); ?></pre>
			<?php endif; ?>
		</section>

		<!-- Footer -->
		<footer class="tsm-result-footer">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: execution timestamp */
					__( 'Executed at: %s', 'test-script-manager' ),
					$data['executed_at']
				)
			);
			?>
		</footer>
	</div>
	<?php wp_footer(); ?>

	<!-- Export Buttons CSS -->
	<style>
		.tsm-export-buttons {
			margin: 15px 0;
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
		}
		.tsm-export-buttons .button {
			display: inline-flex;
			align-items: center;
			gap: 5px;
		}
		.tsm-export-buttons .dashicons {
			font-size: 16px;
			width: 16px;
			height: 16px;
			line-height: 16px;
		}
	</style>

	<!-- Export Buttons JavaScript -->
	<script>
	(function() {
		'use strict';

		var executionId = <?php echo (int) $data['execution_id']; ?>;
		var restNonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
		var restUrl = '<?php echo esc_js( rest_url( 'test-script-manager/v1/executions/' ) ); ?>';

		document.querySelectorAll('.tsm-export-btn').forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				var format = this.dataset.format;
				var url = restUrl + executionId + '/export?format=' + format + '&_wpnonce=' + restNonce;
				window.location.href = url;
			});
		});
	})();
	</script>
</body>
</html>
