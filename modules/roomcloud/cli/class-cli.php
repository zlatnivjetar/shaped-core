<?php
/**
 * WP-CLI commands for RoomCloud management
 * Usage: wp roomcloud <command>
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class Shaped_RC_CLI {
    
    /**
     * Clear all retry queue entries
     * 
     * @synopsis [--confirm]
     */
    public function clear_queue($args, $assoc_args) {
        if (!isset($assoc_args['confirm'])) {
            WP_CLI::confirm('This will delete all retry queue entries. Continue?');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'roomcloud_sync_queue';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $wpdb->query("TRUNCATE TABLE $table");
        
        WP_CLI::success("Cleared {$count} entries from retry queue");
    }
    
    /**
     * Clear retry queue for deleted bookings only
     */
    public function clean_queue() {
        global $wpdb;
        $table = $wpdb->prefix . 'roomcloud_sync_queue';
        
        $items = $wpdb->get_results("SELECT id, booking_id FROM $table");
        $deleted = 0;
        
        foreach ($items as $item) {
            $booking = get_post($item->booking_id);
            if (!$booking || $booking->post_type !== 'mphb_booking') {
                $wpdb->delete($table, ['id' => $item->id]);
                $deleted++;
            }
        }
        
        WP_CLI::success("Removed {$deleted} entries for deleted bookings");
    }
    
    /**
     * Delete all test bookings (IDs 17000-18000)
     * 
     * @synopsis [--confirm]
     */
    public function delete_test_bookings($args, $assoc_args) {
        if (!isset($assoc_args['confirm'])) {
            WP_CLI::confirm('This will permanently delete all bookings with IDs 17000-18000. Continue?');
        }
        
        global $wpdb;
        
        // Get all booking IDs in range
        $booking_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'mphb_booking' 
             AND ID >= %d AND ID <= %d",
            17000, 18000
        ));
        
        $deleted = 0;
        foreach ($booking_ids as $booking_id) {
            // Delete reserved rooms first
            $reserved_rooms = get_posts([
                'post_type' => 'mphb_reserved_room',
                'post_parent' => $booking_id,
                'posts_per_page' => -1,
                'fields' => 'ids',
            ]);
            
            foreach ($reserved_rooms as $room_id) {
                wp_delete_post($room_id, true);
            }
            
            // Delete booking
            wp_delete_post($booking_id, true);
            $deleted++;
        }
        
        WP_CLI::success("Deleted {$deleted} test bookings");
        
        // Clean queue
        $this->clean_queue();
    }
    
    /**
     * Clear all inventory data
     * 
     * @synopsis [--confirm]
     */
    public function clear_inventory($args, $assoc_args) {
        if (!isset($assoc_args['confirm'])) {
            WP_CLI::confirm('This will delete all cached RoomCloud inventory. Continue?');
        }
        
        Shaped_RC_Availability_Manager::clear_all_inventory();
        WP_CLI::success('Inventory cleared');
    }
    
    /**
     * Show retry queue status
     */
    public function queue_status() {
        global $wpdb;
        $table = $wpdb->prefix . 'roomcloud_sync_queue';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE next_retry <= %s AND attempts < 5",
            current_time('mysql')
        ));
        $failed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE attempts >= 5");
        
        WP_CLI::line("Retry Queue Status:");
        WP_CLI::line("  Total entries: {$total}");
        WP_CLI::line("  Pending retry: {$pending}");
        WP_CLI::line("  Failed (max attempts): {$failed}");
        
        // Show deleted booking entries
        $items = $wpdb->get_results("SELECT id, booking_id FROM $table LIMIT 100");
        $orphaned = 0;
        foreach ($items as $item) {
            $booking = get_post($item->booking_id);
            if (!$booking || $booking->post_type !== 'mphb_booking') {
                $orphaned++;
            }
        }
        
        if ($orphaned > 0) {
            WP_CLI::warning("  {$orphaned} entries reference deleted bookings");
            WP_CLI::line("  Run: wp roomcloud clean-queue");
        }
    }
    
    /**
     * Truncate error log file
     *
     * @synopsis [--confirm]
     */
    public function clear_log($args, $assoc_args) {
        if (!isset($assoc_args['confirm'])) {
            WP_CLI::confirm('This will clear the sync error log. Continue?');
        }

        $log_file = SHAPED_RC_LOGS_DIR . 'sync-errors.log';

        if (file_exists($log_file)) {
            $size = filesize($log_file);
            file_put_contents($log_file, '');
            WP_CLI::success('Log cleared (' . size_format($size) . ')');
        } else {
            WP_CLI::warning('Log file does not exist');
        }
    }

    /**
     * Show current RoomCloud configuration
     *
     * Displays the current RoomCloud API configuration (password is masked).
     *
     * ## EXAMPLES
     *
     *     wp roomcloud config
     *
     * @synopsis
     */
    public function config() {
        // Credentials and Channel ID from wp-config.php
        $service_url = defined('SHAPED_RC_SERVICE_URL') ? SHAPED_RC_SERVICE_URL : '';
        $username = defined('SHAPED_RC_USERNAME') ? SHAPED_RC_USERNAME : '';
        $password = defined('SHAPED_RC_PASSWORD') ? SHAPED_RC_PASSWORD : '';
        $channel_id = defined('SHAPED_RC_CHANNEL_ID') ? SHAPED_RC_CHANNEL_ID : '';

        // IDs from database
        $hotel_id = get_option('shaped_rc_hotel_id', '');
        $rate_id = get_option('shaped_rc_rate_id', '');

        WP_CLI::line('RoomCloud Configuration:');
        WP_CLI::line('');
        WP_CLI::line('Credentials (from wp-config.php):');
        WP_CLI::line('  Service URL: ' . ($service_url ?: '(not set in wp-config.php)'));
        WP_CLI::line('  Username: ' . ($username ?: '(not set in wp-config.php)'));
        WP_CLI::line('  Password: ' . ($password ? str_repeat('*', min(strlen($password), 12)) : '(not set in wp-config.php)'));
        WP_CLI::line('  Channel ID: ' . ($channel_id ?: '(not set in wp-config.php)'));
        WP_CLI::line('');
        WP_CLI::line('Configuration IDs (from database):');
        WP_CLI::line('  Hotel ID: ' . ($hotel_id ?: '(not set)'));
        WP_CLI::line('  Rate ID: ' . ($rate_id ?: '(not set)'));
        WP_CLI::line('');

        if (Shaped_RC_API::is_configured()) {
            WP_CLI::success('RoomCloud is configured');
        } else {
            WP_CLI::warning('RoomCloud is not fully configured - check wp-config.php constants');
        }
    }
}

WP_CLI::add_command('roomcloud', 'Shaped_RC_CLI');