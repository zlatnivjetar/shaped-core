<?php
/**
 * Shaped Core Helper Functions
 * 
 * Utility functions used throughout the plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get a Shaped option with fallback
 */
function shaped_get_option(string $key, $default = null) {
    return get_option($key, $default);
}

/**
 * Get the current property's admin email
 * Can be filtered per-client
 */
function shaped_get_admin_email(): string {
    return apply_filters('shaped/admin_email', get_option('admin_email'));
}

/**
 * Get property name for emails
 */
function shaped_get_property_name(): string {
    return apply_filters('shaped/property_name', get_bloginfo('name'));
}

/**
 * Get property email (for From headers)
 */
function shaped_get_property_email(): string {
    $default = 'info@' . parse_url(home_url(), PHP_URL_HOST);
    return apply_filters('shaped/property_email', $default);
}

/**
 * Format currency amount
 */
function shaped_format_price(float $amount, string $currency = 'EUR'): string {
    $symbol = shaped_get_currency_symbol($currency);
    return $symbol . number_format($amount, 2);
}

/**
 * Get currency symbol
 */
function shaped_get_currency_symbol(string $currency = 'EUR'): string {
    $symbols = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'HRK' => 'kn',
    ];
    
    // Try MPHB first
    if (function_exists('MPHB')) {
        return MPHB()->settings()->currency()->getCurrencySymbol();
    }
    
    return $symbols[strtoupper($currency)] ?? $currency;
}

/**
 * Check if RoomCloud module is active
 */
function shaped_is_roomcloud_active(): bool {
    return SHAPED_ENABLE_ROOMCLOUD && class_exists('Shaped_RC_API');
}

/**
 * Check if Reviews module is active
 */
function shaped_is_reviews_active(): bool {
    return SHAPED_ENABLE_REVIEWS && class_exists('Shaped_Reviews_Sync');
}

/**
 * Log a message (only if WP_DEBUG is enabled)
 */
function shaped_log(string $message, string $level = 'info'): void {
    if (!WP_DEBUG) {
        return;
    }
    
    $prefix = '[Shaped ' . ucfirst($level) . ']';
    error_log($prefix . ' ' . $message);
}
