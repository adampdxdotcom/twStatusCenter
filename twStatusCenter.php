<?php
/**
 * Plugin Name:       TW Status Center
 * Description:       Provides a central dashboard to monitor the status and metrics of all Theatre West (TW) plugins.
 * Version:           1.1.0
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
define( 'TWSC_VERSION', '1.1.0' );

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

    // Add a default option for the logging level on first activation.
    if ( false === get_option( 'twsc_settings' ) ) {
        add_option( 'twsc_settings', [ 'log_level' => 'info' ] );
    }
}
register_activation_hook( __FILE__, 'twsc_activate' );


// =========================================================================
// == Global Logging Function (UPGRADED)
// =========================================================================

if ( ! function_exists( 'tw_suite_log' ) ) {
	/**
	 * A globally available function to log events from any TW plugin.
	 *
	 * This function now checks the user-defined verbosity level before
	 * saving a log to the database.
	 *
	 * @param string $source  The name of the plugin or component logging the event (e.g., 'TW Forms').
	 * @param string $message The message to log. Can be a string, array, or object.
	 * @param string $level   The log level (e.g., 'INFO', 'WARNING', 'ERROR', 'DEBUG'). Defaults to 'INFO'.
	 */
	function tw_suite_log( $source, $message, $level = 'INFO' ) {
		global $wpdb;

        // --- START: New Filtering Logic ---

        $options = get_option( 'twsc_settings', [ 'log_level' => 'info' ] );
        $min_log_level_setting = $options['log_level'];

        // Define the hierarchy of log levels. Higher number is more severe.
        $level_hierarchy = [
            'DEBUG'   => 0,
            'INFO'    => 1,
            'WARNING' => 2,
            'ERROR'   => 3,
        ];

        $incoming_level_value = $level_hierarchy[ strtoupper( $level ) ] ?? 1; // Default to INFO if unknown
        $setting_level_value = $level_hierarchy[ strtoupper( $min_log_level_setting ) ] ?? 1;

        // If the incoming log's severity is less than the minimum setting, stop and do nothing.
        if ( $incoming_level_value < $setting_level_value ) {
            return;
        }

        // --- END: New Filtering Logic ---

		// If the message is an array or object, format it for readability.
		if ( is_array( $message ) || is_object( $message ) ) {
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
// == Settings API Registration (NEW)
// =========================================================================

if ( ! function_exists( 'twsc_register_settings' ) ) {
    /**
     * Registers the plugin's settings with WordPress.
     */
    function twsc_register_settings() {
        register_setting(
            'twsc_settings_group',          // A unique name for the settings group
            'twsc_settings',                // The name of the option to be stored in the wp_options table
            'twsc_sanitize_settings'        // A callback function to sanitize the input
        );
    }
    add_action( 'admin_init', 'twsc_register_settings' );

    /**
     * Sanitizes the settings input before saving to the database.
     *
     * @param array $input The raw input from the settings form.
     * @return array The sanitized input.
     */
    function twsc_sanitize_settings( $input ) {
        $sanitized_input = [];
        $allowed_levels = [ 'debug', 'info', 'warning', 'error' ];

        if ( isset( $input['log_level'] ) && in_array( $input['log_level'], $allowed_levels, true ) ) {
            $sanitized_input['log_level'] = $input['log_level'];
        } else {
            // Default to 'info' if an invalid value is submitted.
            $sanitized_input['log_level'] = 'info';
        }

        return $sanitized_input;
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
