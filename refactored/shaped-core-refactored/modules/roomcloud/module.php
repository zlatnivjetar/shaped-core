<?php
/**
 * RoomCloud Module Bootstrap
 * 
 * Loaded only when:
 *   1. SHAPED_ENABLE_ROOMCLOUD === true
 *   2. This file exists
 */

if (!defined('ABSPATH')) exit;

// Dependency check
if (!class_exists('Shaped_Pricing')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Shaped RoomCloud:</strong> Shaped Core must be loaded first.</p></div>';
    });
    return;
}

// Module constants
define('SHAPED_RC_VERSION', '1.1.0');
define('SHAPED_RC_DIR', __DIR__ . '/');
define('SHAPED_RC_LOGS_DIR', SHAPED_RC_DIR . 'logs/');

// Ensure logs directory
if (!file_exists(SHAPED_RC_LOGS_DIR)) {
    wp_mkdir_p(SHAPED_RC_LOGS_DIR);
}

// Load module classes
require_once SHAPED_RC_DIR . 'includes/class-error-logger.php';
require_once SHAPED_RC_DIR . 'includes/class-api.php';
require_once SHAPED_RC_DIR . 'includes/class-availability-manager.php';
require_once SHAPED_RC_DIR . 'includes/class-sync-manager.php';
require_once SHAPED_RC_DIR . 'includes/class-webhook-handler.php';
require_once SHAPED_RC_DIR . 'includes/class-admin-settings.php';

// WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    require_once SHAPED_RC_DIR . 'cli/class-cli.php';
}

// Initialize
add_action('init', function() {
    Shaped_RC_Error_Logger::init();
    Shaped_RC_API::init();
    Shaped_RC_Availability_Manager::init();
    Shaped_RC_Sync_Manager::init();
    Shaped_RC_Webhook_Handler::init();
    Shaped_RC_Admin_Settings::init();
}, 5);

// Module activation (runs on Shaped Core activation if enabled)
add_action('shaped_activate_module_roomcloud', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'roomcloud_sync_queue';
    $charset = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table (
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
    ) $charset;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});