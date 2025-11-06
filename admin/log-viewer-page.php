<?php
/**
 * Renders the Log Viewer page for the TW Status Center.
 *
 * This file queries the custom database table for logs and displays them
 * in a formatted table, allowing for easy diagnostics.
 *
 * @package TW_Status_Center
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the main log viewer page content.
 */
function twsc_render_log_viewer_page() {
	global $wpdb;

	// Security check for user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tw-status-center' ) );
	}

	$log_table_name = TWSC_LOG_TABLE;
	$notice = '';

	// --- Handle the "Clear Log" form submission ---
	if (
		isset( $_POST['twsc_clear_log_action'] ) &&
		isset( $_POST['twsc_clear_log_nonce'] ) &&
		wp_verify_nonce( sanitize_key( $_POST['twsc_clear_log_nonce'] ), 'twsc_clear_log' )
	) {
		// Using TRUNCATE is faster than DELETE for clearing an entire table.
		$wpdb->query( "TRUNCATE TABLE {$log_table_name}" );
		$notice = '<div class="notice notice-success is-dismissible"><p>The suite log has been cleared successfully.</p></div>';
	}

	// Fetch the most recent 200 log entries to display.
	// We limit this to prevent performance issues on sites with very large logs.
	$logs = $wpdb->get_results(
		"SELECT * FROM {$log_table_name} ORDER BY log_time DESC LIMIT 200"
	);
	?>

	<div class="wrap twsc-wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p class="description">
			This page displays the most recent 200 entries from the centralized log. Events, errors, and informational messages from all TW plugins will appear here.
		</p>

		<?php echo $notice; // Display any admin notices (like the success message). ?>

		<form method="post" class="twsc-log-actions">
			<?php wp_nonce_field( 'twsc_clear_log', 'twsc_clear_log_nonce' ); ?>
			<button type="submit" name="twsc_clear_log_action" class="button button-secondary" onclick="return confirm('Are you sure you want to permanently delete all log entries?');">
				Clear Entire Log
			</button>
		</form>

		<div class="twsc-log-table-container">
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th style="width: 20%;">Timestamp</th>
						<th style="width: 15%;">Source</th>
						<th style="width: 10%;">Level</th>
						<th>Message</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="4">No logs found.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log_entry ) : ?>
							<tr>
								<td>
									<?php
										// Format the timestamp to be more readable.
										echo esc_html( date( 'Y-m-d H:i:s', strtotime( $log_entry->log_time ) ) );
									?>
								</td>
								<td><strong><?php echo esc_html( $log_entry->plugin_source ); ?></strong></td>
								<td>
									<?php
										$level = strtoupper( $log_entry->log_level );
										$level_class = 'log-level-' . strtolower( esc_attr( $level ) );
										echo '<span class="twsc-log-level ' . $level_class . '">' . esc_html( $level ) . '</span>';
									?>
								</td>
								<td>
									<div class="twsc-log-message">
										<?php
											// Use nl2br to respect newlines in log messages (e.g., from print_r).
											echo nl2br( esc_html( $log_entry->message ) );
										?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<style>
		.twsc-log-actions {
			margin-top: 15px;
		}
		.twsc-log-table-container {
			margin-top: 20px;
		}
		.twsc-log-message {
			max-height: 200px;
			overflow-y: auto;
			white-space: pre-wrap; /* Allows text to wrap and respects whitespace */
			word-break: break-word;
			font-family: monospace;
			font-size: 0.9em;
			background-color: #f6f7f7;
			padding: 5px 8px;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		.twsc-log-level {
			display: inline-block;
			padding: 4px 8px;
			border-radius: 4px;
			font-weight: bold;
			color: #fff;
			font-size: 0.9em;
			line-height: 1;
			text-align: center;
			min-width: 60px;
		}
		.twsc-log-level.log-level-info {
			background-color: #2271b1; /* WordPress Blue */
		}
		.twsc-log-level.log-level-warning {
			background-color: #f59e0b; /* Amber/Orange */
		}
		.twsc-log-level.log-level-error {
			background-color: #d63638; /* WordPress Red */
		}
		.twsc-log-level.log-level-debug {
			background-color: #64748b; /* Slate/Gray */
		}
	</style>

	<?php
}
