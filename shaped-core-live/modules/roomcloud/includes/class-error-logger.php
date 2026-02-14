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
    
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        // No cron scheduling - all errors logged to file only
    }
    
    /**
     * Log critical error (file and error_log only)
     */
    public static function log_critical($message, $context = [])
    {
        self::write_to_file('CRITICAL', $message, $context);
        error_log('[RoomCloud CRITICAL] ' . $message);
    }
    
    /**
     * Log error (file and error_log only)
     */
    public static function log_error($message, $context = [])
    {
        self::write_to_file('ERROR', $message, $context);
        error_log('[RoomCloud ERROR] ' . $message);
    }
    
    /**
     * Log warning (file and error_log only)
     */
    public static function log_warning($message, $context = [])
    {
        self::write_to_file('WARNING', $message, $context);
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