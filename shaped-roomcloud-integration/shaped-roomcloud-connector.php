<?php
/**
 * Plugin Name: Shaped RoomCloud Connector
 * Description: Bidirectional sync between MotoPress Hotel Booking and RoomCloud Channel Manager
 * Version: 1.1.0
 * Author: Shaped Systems
 * Text Domain: shaped-roomcloud
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SHAPED_RC_VERSION', '1.1.0');
define('SHAPED_RC_FILE', __FILE__);
define('SHAPED_RC_DIR', plugin_dir_path(__FILE__));
define('SHAPED_RC_URL', plugin_dir_url(__FILE__));
define('SHAPED_RC_LOGS_DIR', SHAPED_RC_DIR . 'logs/');

// Ensure logs directory exists
if (!file_exists(SHAPED_RC_LOGS_DIR)) {
    wp_mkdir_p(SHAPED_RC_LOGS_DIR);
}

// Load core classes
require_once SHAPED_RC_DIR . 'includes/class-error-logger.php';
require_once SHAPED_RC_DIR . 'includes/class-availability-manager.php';
require_once SHAPED_RC_DIR . 'includes/class-roomcloud-api.php';
require_once SHAPED_RC_DIR . 'includes/class-sync-manager.php';
require_once SHAPED_RC_DIR . 'includes/class-webhook-handler.php';
require_once SHAPED_RC_DIR . 'includes/class-admin-settings.php';

// Load WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    require_once SHAPED_RC_DIR . 'cli/class-roomcloud-cli.php';
}

// Initialize plugin
add_action('plugins_loaded', function() {
    // Check dependencies
    if (!function_exists('MPHB')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Shaped RoomCloud Connector:</strong> MotoPress Hotel Booking plugin is required.</p></div>';
        });
        return;
    }

    if (!class_exists('Shaped_Pricing')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Shaped RoomCloud Connector:</strong> Shaped Core plugin is required.</p></div>';
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
    
    error_log('[RoomCloud] Connector initialized v' . SHAPED_RC_VERSION);
}, 25);

// Activation hook - create database table
register_activation_hook(__FILE__, 'shaped_rc_activate');

function shaped_rc_activate() {
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

    error_log('[RoomCloud] Activation: Database table created');
}

// Deactivation hook - clear scheduled events
register_deactivation_hook(__FILE__, 'shaped_rc_deactivate');

function shaped_rc_deactivate() {
    wp_clear_scheduled_hook('shaped_rc_retry_failed_syncs');
    wp_clear_scheduled_hook('shaped_rc_daily_digest');
    wp_clear_scheduled_hook('cleanup_roomcloud_flag');
    
    error_log('[RoomCloud] Deactivation: Scheduled events cleared');
}