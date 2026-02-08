<?php
/**
 * Uninstall Script
 * Cleanup on plugin uninstall
 *
 * @package Shaped_Core
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/* =========================================================================
 * CLEANUP OPTIONS
 * ========================================================================= */

// Core options
delete_option('shaped_discounts');
delete_option('shaped_discount_ranges');

// RoomCloud module options (credentials and channel_id are in wp-config.php, not database)
delete_option('shaped_rc_hotel_id');
delete_option('shaped_rc_rate_id');
delete_option('shaped_rc_room_mapping');
delete_option('shaped_rc_error_log');
delete_option('shaped_rc_retry_queue');

/* =========================================================================
 * CLEANUP SCHEDULED EVENTS
 * ========================================================================= */

wp_clear_scheduled_hook('shaped_check_abandoned_bookings');
wp_clear_scheduled_hook('shaped_daily_charge_fallback');
wp_clear_scheduled_hook('shaped_charge_single_booking');

/* =========================================================================
 * CLEANUP POST META
 * ========================================================================= */

global $wpdb;

// Clean up booking metadata
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_shaped_%'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_roomcloud_%'");

/* =========================================================================
 * FLUSH REWRITE RULES
 * ========================================================================= */

flush_rewrite_rules();
