<?php
/**
 * RoomCloud Integration Module
 * Bidirectional sync between MotoPress Hotel Booking and RoomCloud Channel Manager
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Module constants
define('SHAPED_RC_VERSION', '1.1.0');
define('SHAPED_RC_DIR', SHAPED_DIR . 'modules/roomcloud/');
define('SHAPED_RC_URL', SHAPED_URL . 'modules/roomcloud/');
define('SHAPED_RC_LOGS_DIR', SHAPED_RC_DIR . 'logs/');

// Ensure logs directory exists
if (!file_exists(SHAPED_RC_LOGS_DIR)) {
    wp_mkdir_p(SHAPED_RC_LOGS_DIR);
}

// Load core classes
require_once SHAPED_RC_DIR . 'includes/class-error-logger.php';
require_once SHAPED_RC_DIR . 'includes/class-availability-manager.php';
require_once SHAPED_RC_DIR . 'includes/class-api.php';
require_once SHAPED_RC_DIR . 'includes/class-sync-manager.php';
require_once SHAPED_RC_DIR . 'includes/class-webhook-handler.php';
require_once SHAPED_RC_DIR . 'includes/class-admin-settings.php';

// Load WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    require_once SHAPED_RC_DIR . 'cli/class-cli.php';
}

// Initialize module
add_action('plugins_loaded', function() {
    // Check dependencies
    if (!function_exists('MPHB')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Shaped RoomCloud Module:</strong> MotoPress Hotel Booking plugin is required.</p></div>';
        });
        return;
    }

    if (!class_exists('Shaped_Pricing')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Shaped RoomCloud Module:</strong> Shaped Core plugin must be active.</p></div>';
        });
        return;
    }

    // Initialize components
    Shaped_RC_Error_Logger::init();
    Shaped_RC_Availability_Manager::init();
    Shaped_RC_API::init();
    Shaped_RC_Sync_Manager::init();
    Shaped_RC_Webhook_Handler::init();
    Shaped_RC_Admin_Settings::init();

    error_log('[RoomCloud] Module initialized v' . SHAPED_RC_VERSION);
}, 25);

// Hook into Shaped Core activation for database setup
add_action('shaped_activate_module_roomcloud', 'shaped_rc_create_database_table');

function shaped_rc_create_database_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'roomcloud_sync_queue';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        operation VARCHAR(50) NOT NULL,
        payload TEXT,
        attempts INT DEFAULT 0,
        last_error TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        next_retry DATETIME,
        INDEX booking_idx (booking_id),
        INDEX retry_idx (next_retry)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    error_log('[RoomCloud] Database table created/verified');
}
