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
        $snapshot = Shaped_RC_API::get_configuration_snapshot();
        $service_url = $snapshot['service_url'];
        $username = $snapshot['username'];
        $password_configured = !empty($snapshot['password_configured']);
        $channel_id = $snapshot['channel_id'];
        $hotel_id = $snapshot['hotel_id'];
        $rate_id = $snapshot['rate_id'];
        $room_mapping = is_array($snapshot['room_mapping']) ? $snapshot['room_mapping'] : [];
        $issues = $this->get_config_issues($snapshot);
        $missing_room_mappings = $this->get_missing_room_mappings($room_mapping);

        WP_CLI::line('RoomCloud Configuration:');
        WP_CLI::line('');
        WP_CLI::line('Credentials (from wp-config.php):');
        WP_CLI::line('  Service URL: ' . ($service_url ?: '(not set in wp-config.php)'));
        WP_CLI::line('  Username: ' . ($username ?: '(not set in wp-config.php)'));
        WP_CLI::line('  Password: ' . ($password_configured ? '************' : '(not set in wp-config.php)'));
        WP_CLI::line('  Channel ID: ' . ($channel_id ?: '(not set in wp-config.php)'));
        WP_CLI::line('');
        WP_CLI::line('Configuration IDs (from database):');
        WP_CLI::line('  Hotel ID: ' . ($hotel_id ?: '(not set)'));
        WP_CLI::line('  Rate ID: ' . ($rate_id ?: '(not set)'));
        WP_CLI::line('  Room mappings: ' . count($room_mapping));
        WP_CLI::line('');

        if (!empty($missing_room_mappings)) {
            WP_CLI::warning('Missing room mappings for: ' . implode(', ', $missing_room_mappings));
        }

        if (empty($issues) && Shaped_RC_API::is_configured()) {
            WP_CLI::success('RoomCloud is fully configured');
            return;
        }

        foreach ($issues as $issue) {
            WP_CLI::warning($issue);
        }
    }

    /**
     * Repair direct website bookings already synced or queued for RoomCloud.
     *
     * @synopsis [--booking=<id>] [--dry-run]
     */
    public function repair_direct_bookings($args, $assoc_args) {
        $booking_id = isset($assoc_args['booking']) ? absint($assoc_args['booking']) : null;
        $dry_run = isset($assoc_args['dry-run']);
        $snapshot = Shaped_RC_API::get_configuration_snapshot();
        $issues = $this->get_config_issues($snapshot);

        if (!Shaped_RC_API::is_configured()) {
            WP_CLI::error('RoomCloud credentials are not configured.');
        }

        if (!empty($issues)) {
            foreach ($issues as $issue) {
                WP_CLI::warning($issue);
            }
        }

        $candidates = Shaped_RC_Sync_Manager::get_repair_candidates($booking_id);

        if (empty($candidates)) {
            WP_CLI::success($booking_id
                ? "No repair action needed for booking #{$booking_id}"
                : 'No direct bookings need repair');
            return;
        }

        WP_CLI::line(sprintf(
            '%s %d booking(s) for RoomCloud repair.',
            $dry_run ? 'Dry run for' : 'Processing',
            count($candidates)
        ));

        $counts = [
            'synced' => 0,
            'dry_run' => 0,
            'skip' => 0,
            'failed' => 0,
        ];

        foreach ($candidates as $candidate_id) {
            $result = Shaped_RC_Sync_Manager::repair_direct_booking((int) $candidate_id, [
                'dry_run' => $dry_run,
            ]);

            $state = isset($result['state']) && is_array($result['state']) ? $result['state'] : [];
            $status = isset($state['status']) ? $state['status'] : 'NONE';
            $prepaid = isset($state['prepaid']) ? number_format((float) $state['prepaid'], 2, '.', '') : '0.00';

            if (($result['action'] ?? '') === 'synced') {
                $counts['synced']++;
                WP_CLI::success("#{$candidate_id}: synced {$status} (prepaid {$prepaid})");
                continue;
            }

            if (($result['action'] ?? '') === 'dry_run') {
                $counts['dry_run']++;
                WP_CLI::line("#{$candidate_id}: would sync {$status} (prepaid {$prepaid})");
                continue;
            }

            if (($result['action'] ?? '') === 'skip') {
                $counts['skip']++;
                WP_CLI::line("#{$candidate_id}: skipped ({$result['reason']})");
                continue;
            }

            $counts['failed']++;
            WP_CLI::warning("#{$candidate_id}: failed ({$result['reason']})");
        }

        WP_CLI::line('');
        WP_CLI::line('Repair summary:');
        WP_CLI::line('  Synced: ' . $counts['synced']);
        WP_CLI::line('  Dry run: ' . $counts['dry_run']);
        WP_CLI::line('  Skipped: ' . $counts['skip']);
        WP_CLI::line('  Failed: ' . $counts['failed']);

        if ($counts['failed'] > 0) {
            WP_CLI::warning('Some repairs failed. Check RoomCloud logs and configuration.');
            return;
        }

        WP_CLI::success($dry_run ? 'Dry run completed.' : 'Repair completed.');
    }

    /**
     * Identify configuration issues that can break RoomCloud sync.
     */
    private function get_config_issues(array $snapshot): array {
        $issues = [];

        if (empty($snapshot['service_url'])) {
            $issues[] = 'Missing SHAPED_RC_SERVICE_URL';
        }
        if (empty($snapshot['username'])) {
            $issues[] = 'Missing SHAPED_RC_USERNAME';
        }
        if (empty($snapshot['password_configured'])) {
            $issues[] = 'Missing SHAPED_RC_PASSWORD';
        }
        if (empty($snapshot['hotel_id'])) {
            $issues[] = 'Missing shaped_rc_hotel_id';
        }
        if (empty($snapshot['rate_id'])) {
            $issues[] = 'Missing shaped_rc_rate_id';
        }
        if (empty($snapshot['channel_id'])) {
            $issues[] = 'Missing SHAPED_RC_CHANNEL_ID';
        }
        if (empty($snapshot['room_mapping']) || !is_array($snapshot['room_mapping'])) {
            $issues[] = 'No RoomCloud room mapping configured';
        }

        return $issues;
    }

    /**
     * Identify unmapped MotoPress room types.
     */
    private function get_missing_room_mappings(array $room_mapping): array {
        if (!class_exists('Shaped_Pricing')) {
            return [];
        }

        $room_types = Shaped_Pricing::fetch_room_types();
        $missing = [];

        foreach ($room_types as $slug => $label) {
            if (empty($room_mapping[$slug])) {
                $missing[] = $slug;
            }
        }

        return $missing;
    }
}

WP_CLI::add_command('roomcloud', 'Shaped_RC_CLI');
