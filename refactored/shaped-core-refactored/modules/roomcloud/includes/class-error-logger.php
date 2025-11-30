<?php
/**
 * Error Logger & Email Alerts
 * Handles logging, email notifications, and retry queue management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_Error_Logger
{
    private static $instance = null;
    private static $digest_errors = [];
    
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        /*/ Schedule daily digest
        add_action('init', [$this, 'schedule_daily_digest']);
        add_action('shaped_rc_daily_digest', [$this, 'send_daily_digest']);*/
    }
    
    /**
     * Log critical error and send immediate email
     */
    public static function log_critical($message, $context = [])
    {
        self::write_to_file('CRITICAL', $message, $context);
        self::send_immediate_alert('CRITICAL', $message, $context);
        
        // Also log to WordPress error log
        error_log('[RoomCloud CRITICAL] ' . $message);
    }
    
    /**
     * Log error that should be included in daily digest
     */
    public static function log_error($message, $context = [])
    {
        self::write_to_file('ERROR', $message, $context);
        
        // Store for daily digest
        self::$digest_errors[] = [
            'time' => current_time('mysql'),
            'message' => $message,
            'context' => $context,
        ];
        
        error_log('[RoomCloud ERROR] ' . $message);
    }
    
    /**
     * Log warning (included in digest, not critical)
     */
    public static function log_warning($message, $context = [])
    {
        self::write_to_file('WARNING', $message, $context);
        
        self::$digest_errors[] = [
            'time' => current_time('mysql'),
            'message' => $message,
            'context' => $context,
        ];
        
        error_log('[RoomCloud WARNING] ' . $message);
    }
    
    /**
     * Log info message (debugging, not in emails)
     */
    public static function log_info($message, $context = [])
    {
        self::write_to_file('INFO', $message, $context);
        error_log('[RoomCloud INFO] ' . $message);
    }
    
    /**
     * Write to log file
     */
    private static function write_to_file($level, $message, $context = [])
    {
        $log_file = SHAPED_RC_LOGS_DIR . 'sync-errors.log';
        $timestamp = current_time('Y-m-d H:i:s');
        
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $level,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= "Context: " . print_r($context, true) . "\n";
        }
        
        $log_entry .= str_repeat('-', 80) . "\n";
        
        // Append to file
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Rotate log if too large (> 5MB)
        if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) {
            $backup = SHAPED_RC_LOGS_DIR . 'sync-errors-' . date('Y-m-d-His') . '.log';
            rename($log_file, $backup);
            
            // Keep only last 5 backup files
            $backups = glob(SHAPED_RC_LOGS_DIR . 'sync-errors-*.log');
            if (count($backups) > 5) {
                usort($backups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                array_map('unlink', array_slice($backups, 0, -5));
            }
        }
    }
    
    /*
     * Send immediate email alert for critical failures
     
    private static function send_immediate_alert($level, $message, $context = [])
    {
        $to = 'david@shapedsystems.com';
        $subject = '[CRITICAL] RoomCloud Sync Failure - Preelook';
        
        $body = "A critical error occurred in the RoomCloud sync:\n\n";
        $body .= "Time: " . current_time('Y-m-d H:i:s') . "\n";
        $body .= "Level: {$level}\n";
        $body .= "Message: {$message}\n\n";
        
        if (!empty($context)) {
            $body .= "Context:\n";
            $body .= print_r($context, true) . "\n";
        }
        
        $body .= "\n---\n";
        $body .= "Site: " . get_site_url() . "\n";
        $body .= "Log file: " . SHAPED_RC_LOGS_DIR . "sync-errors.log\n";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        wp_mail($to, $subject, $body, $headers);
    }
        
    
    /**
     * Schedule daily digest
     
    public function schedule_daily_digest()
    {
        if (!wp_next_scheduled('shaped_rc_daily_digest')) {
            // Schedule for 9 AM daily
            $first_run = strtotime('tomorrow 09:00:00');
            wp_schedule_event($first_run, 'daily', 'shaped_rc_daily_digest');
        }
    }
    
    /**
     * Send daily digest email
     
    public function send_daily_digest()
    {
        // Get errors from transient (stored throughout the day)
        $digest_errors = get_transient('shaped_rc_digest_errors') ?: [];
        
        if (empty($digest_errors)) {
            return; // No errors to report
        }
        
        $to = 'david@shapedsystems.com';
        $subject = '[Digest] RoomCloud Sync Report - ' . date('Y-m-d');
        
        $body = "Daily RoomCloud sync report for Preelook Apartments\n";
        $body .= "Period: " . date('Y-m-d') . "\n";
        $body .= "Total issues: " . count($digest_errors) . "\n\n";
        $body .= str_repeat('=', 80) . "\n\n";
        
        foreach ($digest_errors as $index => $error) {
            $body .= sprintf(
                "%d. [%s] %s\n",
                $index + 1,
                $error['time'],
                $error['message']
            );
            
            if (!empty($error['context'])) {
                $body .= "   Context: " . json_encode($error['context']) . "\n";
            }
            
            $body .= "\n";
        }
        
        $body .= str_repeat('=', 80) . "\n";
        $body .= "Full logs: " . SHAPED_RC_LOGS_DIR . "sync-errors.log\n";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        wp_mail($to, $subject, $body, $headers);
        
        // Clear the digest
        delete_transient('shaped_rc_digest_errors');
    }
    
    /**
     * Add error to digest queue
     */
    public static function add_to_digest($message, $context = [])
    {
        $digest = get_transient('shaped_rc_digest_errors') ?: [];
        
        $digest[] = [
            'time' => current_time('mysql'),
            'message' => $message,
            'context' => $context,
        ];
        
        set_transient('shaped_rc_digest_errors', $digest, DAY_IN_SECONDS);
    }
    
    /**
     * Add failed operation to retry queue
     */
    public static function queue_retry($booking_id, $operation, $payload, $error)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'roomcloud_sync_queue';
        
        // Check if already queued
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE booking_id = %d AND operation = %s AND attempts < 5",
            $booking_id,
            $operation
        ));
        
        if ($existing) {
            // Update existing entry
            $wpdb->update(
                $table,
                [
                    'attempts' => $wpdb->get_var($wpdb->prepare(
                        "SELECT attempts FROM $table WHERE id = %d",
                        $existing
                    )) + 1,
                    'last_error' => $error,
                    'next_retry' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                ],
                ['id' => $existing]
            );
        } else {
            // Insert new entry
            $wpdb->insert($table, [
                'booking_id' => $booking_id,
                'operation' => $operation,
                'payload' => maybe_serialize($payload),
                'attempts' => 0,
                'last_error' => $error,
                'created_at' => current_time('mysql'),
                'next_retry' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
            ]);
        }
        
        self::log_warning("Queued retry for booking #{$booking_id}, operation: {$operation}", [
            'error' => $error,
        ]);
    }
    
    /**
     * Get pending retries
     */
    public static function get_pending_retries()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'roomcloud_sync_queue';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE next_retry <= %s 
             AND attempts < 5 
             ORDER BY created_at ASC 
             LIMIT 20",
            current_time('mysql')
        ));
    }
    
    /**
     * Remove from retry queue
     */
    public static function remove_from_queue($queue_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'roomcloud_sync_queue';
        
        $wpdb->delete($table, ['id' => $queue_id]);
    }
    
    /**
     * Mark retry as failed (max attempts reached)
     */
    public static function mark_retry_failed($queue_id, $error)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'roomcloud_sync_queue';
        
        $wpdb->update(
            $table,
            [
                'attempts' => 999, // Mark as permanently failed
                'last_error' => $error,
            ],
            ['id' => $queue_id]
        );
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $queue_id
        ));
        
        if ($item) {
            self::log_critical("Retry failed permanently after max attempts", [
                'booking_id' => $item->booking_id,
                'operation' => $item->operation,
                'error' => $error,
            ]);
        }
    }
}