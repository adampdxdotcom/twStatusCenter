<?php
/**
 * Plugin Name:       TW Status Center
 * Description:       Provides a central dashboard to monitor the status and metrics of all Theatre West (TW) plugins.
 * Version:           1.0.0
 * Author:            Adam Michaels
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tw-status-center
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =========================================================================
// == Constants
// =========================================================================
define( 'TWSC_PATH', plugin_dir_path( __FILE__ ) );
define( 'TWSC_VERSION', '1.0.0' );

// Define the name of our custom log table in a constant for easy access.
global $wpdb;
define( 'TWSC_LOG_TABLE', $wpdb->prefix . 'tw_suite_logs' );


// =========================================================================
// == Plugin Activation Hook
// =========================================================================

/**
 * Runs only when the plugin is activated.
 * Creates the custom database table for logging.
 */
function twsc_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = TWSC_LOG_TABLE;

	$sql = "CREATE TABLE {$table_name} (
		log_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		log_time datetime NOT NULL default '0000-00-00 00:00:00',
		plugin_source varchar(100) NOT NULL,
		log_level varchar(20) NOT NULL,
		message longtext NOT NULL,
		PRIMARY KEY  (log_id),
		KEY idx_log_time (log_time),
		KEY idx_plugin_source (plugin_source)
	) {$charset_collate};";

	// dbDelta is the WordPress function for creating/updating tables.
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'twsc_activate' );


// =========================================================================
// == Global Logging Function
// =========================================================================

if ( ! function_exists( 'tw_suite_log' ) ) {
	/**
	 * A globally available function to log events from any TW plugin.
	 *
	 * This function is the central entry point for the logging system. Other plugins
	 * will check if this function exists before calling it.
	 *
	 * @param string $source  The name of the plugin or component logging the event (e.g., 'TW Forms').
	 * @param string $message The message to log. Can be a string, array, or object.
	 * @param string $level   The log level (e.g., 'INFO', 'WARNING', 'ERROR'). Defaults to 'INFO'.
	 */
	function tw_suite_log( $source, $message, $level = 'INFO' ) {
		global $wpdb;

		// If the message is an array or object, format it for readability.
		if ( is_array( $message ) || is_object( 'message' ) ) {
			$message = print_r( $message, true );
		}

		$wpdb->insert(
			TWSC_LOG_TABLE,
			array(
				'log_time'      => current_time( 'mysql' ),
				'plugin_source' => sanitize_text_field( $source ),
				'log_level'     => sanitize_text_field( $level ),
				'message'       => $message,
			),
			array(
				'%s', // log_time
				'%s', // plugin_source
				'%s', // log_level
				'%s', // message
			)
		);
	}
}


// =========================================================================
// == Admin Menu & Page Setup
// =========================================================================

if ( ! function_exists( 'twsc_register_admin_menu' ) ) {
	/**
	 * Registers the admin menu pages for the Status Center.
	 */
	function twsc_register_admin_menu() {
		// --- 1. Load the files that contain the page rendering functions ---
		require_once TWSC_PATH . 'admin/dashboard-page.php';
		require_once TWSC_PATH . 'admin/log-viewer-page.php';

		// --- 2. Create the main top-level menu item ---
		add_menu_page(
			'TW Status Center',                 // Page Title
			'TW Status',                        // Menu Title (shorter)
			'manage_options',                   // Capability
			'tw-status-center',                 // Menu Slug
			'twsc_render_dashboard_page',       // Callback function to render the page
			'dashicons-dashboard',              // Icon
			6                                   // Position (high up the menu)
		);

		// --- 3. Create the "Log Viewer" submenu item ---
		add_submenu_page(
			'tw-status-center',                 // Parent Slug
			'Suite Log Viewer',                 // Page Title
			'Log Viewer',                       // Menu Title
			'manage_options',                   // Capability
			'tw-suite-log-viewer',              // Menu Slug
			'twsc_render_log_viewer_page'       // Callback function
		);
	}
	add_action( 'admin_menu', 'twsc_register_admin_menu' );
}
