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

/* =========================================================================
 * AMENITY ICON HELPERS
 * ========================================================================= */

/**
 * Get amenity icon data for a facility term
 *
 * @param WP_Term|string $facility Term object or slug
 * @param array          $args     Optional arguments (weight, class, etc.)
 * @return array|null Icon data array or null if facility is invalid
 *
 * @example
 * $icon = shaped_get_amenity_icon($facility);
 * echo $icon['html']; // Outputs: <i class="ph ph-wifi-high"></i>
 */
function shaped_get_amenity_icon(WP_Term|string $facility, array $args = []): ?array {
    static $mapper = null;

    if ($mapper === null) {
        if (!class_exists('Shaped_Amenity_Mapper')) {
            return null;
        }
        $mapper = new Shaped_Amenity_Mapper();
    }

    return $mapper->get_icon($facility, $args);
}

/**
 * Get all amenities from the registry
 *
 * @return array Amenities array
 */
function shaped_get_amenities_registry(): array {
    static $mapper = null;

    if ($mapper === null) {
        if (!class_exists('Shaped_Amenity_Mapper')) {
            return [];
        }
        $mapper = new Shaped_Amenity_Mapper();
    }

    return $mapper->get_all_amenities();
}

/**
 * Render amenity badge HTML
 *
 * @param WP_Term|string $facility Term object or slug
 * @param string         $label    Optional custom label
 * @param array          $args     Optional arguments
 * @return string HTML output
 */
function shaped_render_amenity_badge(WP_Term|string $facility, string $label = '', array $args = []): string {
    $icon_data = shaped_get_amenity_icon($facility, $args);

    if (!$icon_data) {
        return '';
    }

    $display_label = !empty($label) ? $label : $icon_data['label'];

    $html = '<li class="mphb-amenity-item">';
    $html .= '<span class="mphb-amenity-icon">' . $icon_data['html'] . '</span>';
    $html .= '<span class="mphb-amenity-text">' . esc_html($display_label) . '</span>';
    $html .= '</li>';

    return $html;
}

/**
 * Get sorted amenities for a room type
 *
 * @param int $room_type_id Room type post ID
 * @return array Sorted array of icon data
 */
function shaped_get_amenities_for_room(int $room_type_id): array {
    static $mapper = null;

    if ($mapper === null) {
        if (!class_exists('Shaped_Amenity_Mapper')) {
            return [];
        }
        $mapper = new Shaped_Amenity_Mapper();
    }

    return $mapper->get_room_amenities($room_type_id);
}
