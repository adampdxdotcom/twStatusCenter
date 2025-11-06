<?php
/**
 * Renders the main dashboard page for the TW Status Center.
 *
 * This file contains the logic for detecting other TW plugins, fetching their
 * statistics, and displaying them in a clear and organized table.
 *
 * @package TW_Status_Center
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the relevant statistics for a given TW plugin.
 *
 * This is the central "brain" for metrics. It knows how to query the
 * specific data associated with each plugin in the suite.
 *
 * @param string $plugin_name The name of the plugin (e.g., 'TW Plays').
 * @param bool   $is_active   Whether the plugin is currently active.
 * @return string A formatted string of the plugin's metrics, or a status message.
 */
function twsc_get_plugin_metrics( $plugin_name, $is_active ) {
	if ( ! $is_active ) {
		return '<em>Plugin is not active.</em>';
	}

	$metrics = [];

	switch ( $plugin_name ) {

		case 'TW Plays':
			if ( post_type_exists( 'play' ) ) {
				$play_count = wp_count_posts( 'play' )->publish;
				$actor_count = wp_count_posts( 'actor' )->publish;
				$crew_count = wp_count_posts( 'crew' )->publish;
				$metrics[] = "<strong>{$play_count}</strong> Plays";
				$metrics[] = "<strong>{$actor_count}</strong> Actors";
				$metrics[] = "<strong>{$crew_count}</strong> Crew";
			} else {
				return '<em>Play CPT not registered.</em>';
			}
			break;

		case 'TW Forms':
			if ( post_type_exists( 'tw_form' ) ) {
				$form_count = wp_count_posts( 'tw_form' )->publish;
				$metrics[] = "<strong>{$form_count}</strong> Forms";

				// Query for unread submissions
				$unread_query = new WP_Query([
					'post_type'      => 'messages',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'meta_query'     => [
						[
							'key'   => 'entry_status',
							'value' => 'Unread',
						],
					],
				]);
				$metrics[] = "<strong>{$unread_query->found_posts}</strong> Unread Submissions";
			} else {
				return '<em>Form CPT not registered.</em>';
			}
			break;

		case 'TW Calendar':
			if ( post_type_exists( 'event' ) ) {
				$event_count = wp_count_posts( 'event' )->publish;
				$metrics[] = "<strong>{$event_count}</strong> Events";
			} else {
				return '<em>Event CPT not registered.</em>';
			}
			break;

		case 'TW Scripts':
			global $tw_scripts_files;
			if ( isset( $tw_scripts_files ) && is_array( $tw_scripts_files ) ) {
				$php_count = count( $tw_scripts_files['php'] ?? [] );
				$js_count  = count( $tw_scripts_files['js'] ?? [] );
				$css_count = count( $tw_scripts_files['css'] ?? [] );
				$total = $php_count + $js_count + $css_count;
				$metrics[] = "<strong>{$total}</strong> Scripts Found ({$php_count} PHP, {$js_count} JS, {$css_count} CSS)";
			} else {
				return '<em>Could not detect script files.</em>';
			}
			break;

		default:
			return '<em>No metrics available.</em>';
	}

	return implode( ' &nbsp;|&nbsp; ', $metrics );
}


/**
 * Renders the main dashboard page content.
 */
function twsc_render_dashboard_page() {
	// Security check.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tw-status-center' ) );
	}

	// We need access to the get_plugins() function.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all_plugins = get_plugins();
	$tw_plugins  = [];

	// Filter the list to include only our suite plugins.
	foreach ( $all_plugins as $plugin_path => $plugin_data ) {
		if ( strpos( $plugin_data['Name'], 'TW ' ) === 0 ) {
			// Add the plugin's path to its data for the is_plugin_active() check.
			$plugin_data['path'] = $plugin_path;
			$tw_plugins[] = $plugin_data;
		}
	}
	?>

	<div class="wrap twsc-wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p class="description">
			This dashboard provides an at-a-glance overview of your entire Theatre West plugin suite.
		</p>

		<h2>Settings</h2>

		<form method="post" action="options.php">
			<?php
				// This prints out all hidden setting fields, nonces, etc.
				settings_fields( 'twsc_settings_group' );

				// Get our saved options, with a default.
				$options = get_option( 'twsc_settings', [ 'log_level' => 'info' ] );
				$current_level = $options['log_level'];
			?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="twsc_log_level">Logging Level</label></th>
						<td>
							<select name="twsc_settings[log_level]" id="twsc_log_level">
								<option value="debug" <?php selected( 'debug', $current_level ); ?>>Full Debug (Logs Everything)</option>
								<option value="info" <?php selected( 'info', $current_level ); ?>>Standard (Info, Warnings & Errors)</option>
								<option value="warning" <?php selected( 'warning', $current_level ); ?>>Warnings & Errors Only</option>
								<option value="error" <?php selected( 'error', $current_level ); ?>>Errors Only</option>
							</select>
							<p class="description">
								Select the minimum severity level to record in the log. Lower levels are more verbose.
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
		<hr />
		<h2>Suite Status</h2>
		<div class="twsc-status-table-container">
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th style="width: 20%;">Plugin</th>
						<th style="width: 10%;">Version</th>
						<th style="width: 10%;">Status</th>
						<th>Metrics</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $tw_plugins ) ) : ?>
						<tr>
							<td colspan="4">No Theatre West plugins were detected.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $tw_plugins as $plugin ) :
							$is_active = is_plugin_active( $plugin['path'] );
						?>
							<tr>
								<td><strong><?php echo esc_html( $plugin['Name'] ); ?></strong></td>
								<td><?php echo esc_html( $plugin['Version'] ); ?></td>
								<td>
									<?php if ( $is_active ) : ?>
										<span class="twsc-status-label twsc-status-active">Active</span>
									<?php else : ?>
										<span class="twsc-status-label twsc-status-inactive">Inactive</span>
									<?php endif; ?>
								</td>
								<td>
									<?php echo wp_kses_post( twsc_get_plugin_metrics( $plugin['Name'], $is_active ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<style>
		.twsc-status-label {
			display: inline-block;
			padding: 4px 8px;
			border-radius: 4px;
			font-weight: bold;
			color: #fff;
			font-size: 0.9em;
			line-height: 1;
		}
		.twsc-status-active {
			background-color: #2271b1; /* WordPress Blue */
		}
		.twsc-status-inactive {
			background-color: #949494; /* Gray */
		}
		.twsc-status-table-container {
			margin-top: 20px;
		}
		.twsc-status-table-container td strong {
			font-size: 1.1em;
		}
	</style>

	<?php
}
