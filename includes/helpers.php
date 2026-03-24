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
 * Get the configured RoomCloud availability authority mode.
 */
function shaped_get_roomcloud_availability_mode(): string {
    $mode = get_option('shaped_rc_availability_mode', 'motopress');

    return $mode === 'roomcloud_strict' ? 'roomcloud_strict' : 'motopress';
}

/**
 * Check whether RoomCloud is the strict availability authority.
 */
function shaped_is_roomcloud_strict_mode(): bool {
    return shaped_is_roomcloud_active() && shaped_get_roomcloud_availability_mode() === 'roomcloud_strict';
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
 * STRIPE CREDENTIAL HELPERS
 * ========================================================================= */

/**
 * Get Stripe secret key
 *
 * Priority: 1) Constants  2) Database storage
 *
 * @return string Stripe secret key or empty string
 */
function shaped_get_stripe_secret(): string {
    // If Stripe Config class exists, use its getter (handles constant + DB priority)
    if (class_exists('Shaped_Stripe_Config')) {
        return Shaped_Stripe_Config::get_stripe_secret();
    }

    // Fall back to constant
    return defined('SHAPED_STRIPE_SECRET') ? SHAPED_STRIPE_SECRET : '';
}

/**
 * Get Stripe webhook secret
 *
 * Priority: 1) Constants  2) Database storage
 *
 * @return string Stripe webhook secret or empty string
 */
function shaped_get_stripe_webhook(): string {
    // If Stripe Config class exists, use its getter (handles constant + DB priority)
    if (class_exists('Shaped_Stripe_Config')) {
        return Shaped_Stripe_Config::get_stripe_webhook();
    }

    // Fall back to constant
    return defined('SHAPED_STRIPE_WEBHOOK') ? SHAPED_STRIPE_WEBHOOK : '';
}

/* =========================================================================
 * AMENITY ICON HELPERS
 * ========================================================================= */

/**
 * Get amenity icon data for a facility term
 *
 * @param WP_Term|string $facility Term object or slug
 * @param array          $args     Optional arguments:
 *                                 - weight (string): Icon weight (regular, bold, light, thin, duotone, fill)
 *                                 - class (string): Additional CSS classes
 *                                 - skip_fallback (bool): Return null if no icon found (default: false)
 * @return array|null Icon data array or null if no match found and skip_fallback is true
 *
 * @example
 * $icon = shaped_get_amenity_icon($facility);
 * echo $icon['html']; // Outputs: <i class="ph ph-wifi-high"></i>
 *
 * @example Skip amenities without icons:
 * $icon = shaped_get_amenity_icon($facility, ['skip_fallback' => true]);
 * if ($icon) { echo $icon['html']; }
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
 * @param array          $args     Optional arguments:
 *                                 - weight (string): Icon weight
 *                                 - class (string): Additional CSS classes
 *                                 - skip_fallback (bool): Skip if no icon found (default: true)
 * @return string HTML output (empty string if no icon and skip_fallback is true)
 */
function shaped_render_amenity_badge(WP_Term|string $facility, string $label = '', array $args = []): string {
    // Default to skipping fallback icons
    $skip_fallback = $args['skip_fallback'] ?? true;
    $args['skip_fallback'] = $skip_fallback;

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
 * @param int   $room_type_id Room type post ID
 * @param array $args         Optional arguments:
 *                            - skip_fallback (bool): Skip amenities without icons (default: true)
 * @return array Sorted array of icon data (automatically filtered to exclude amenities without icons)
 */
function shaped_get_amenities_for_room(int $room_type_id, array $args = []): array {
    static $mapper = null;

    if ($mapper === null) {
        if (!class_exists('Shaped_Amenity_Mapper')) {
            return [];
        }
        $mapper = new Shaped_Amenity_Mapper();
    }

    return $mapper->get_room_amenities($room_type_id, $args);
}

/**
 * Get priority-ordered amenities for landing page room cards
 *
 * Walks the landing amenity priority list and returns the first $count
 * amenities that the room actually has. 'sleeps' is a special token
 * that pulls total capacity from MPHB.
 *
 * @param int $room_type_id Room type post ID
 * @param int $count        Max amenities to return (default 3)
 * @return array Array of amenity data arrays with keys: icon, label, html, priority
 */
function shaped_get_landing_amenities(int $room_type_id, int $count = 3): array {
    static $priority_list = null;

    if ($priority_list === null) {
        $file = SHAPED_DIR . 'config/landing-amenity-priorities.php';
        $priority_list = file_exists($file) ? include $file : [];
    }

    // Get all facility slugs for this room
    $facilities = get_the_terms($room_type_id, 'mphb_room_type_facility');
    $facility_slugs = [];
    $facility_map = [];

    if (!empty($facilities) && !is_wp_error($facilities)) {
        foreach ($facilities as $facility) {
            $facility_slugs[] = $facility->slug;
            $facility_map[$facility->slug] = $facility;
        }
    }

    $result = [];
    $selected_slugs = [];
    $selected_keys = [];

    foreach ($priority_list as $slug) {
        if (count($result) >= $count) {
            break;
        }

        // Special token: sleeps (from MPHB capacity, not taxonomy)
        if ($slug === 'sleeps') {
            if (!function_exists('MPHB')) {
                continue;
            }
            $mphb_room = MPHB()->getRoomTypeRepository()->findById($room_type_id);
            if (!$mphb_room) {
                continue;
            }
            $total = $mphb_room->getTotalCapacity();
            if ($total > 0) {
                $sleeps_amenity = [
                    'icon'     => 'bed',
                    'label'    => 'Sleeps ' . $total,
                    'html'     => '<i class="ph ph-bed" aria-hidden="true"></i>',
                    'priority' => 1,
                ];

                $result[] = $sleeps_amenity;
                $selected_keys['__sleeps__'] = true;
            }
            continue;
        }

        // Check if room has this facility and skip already-selected slugs
        if (!in_array($slug, $facility_slugs, true) || isset($selected_slugs[$slug])) {
            continue;
        }

        // Get icon data from the mapper
        $icon_data = shaped_get_amenity_icon($facility_map[$slug], ['skip_fallback' => true]);
        if ($icon_data) {
            $result[] = $icon_data;
            $selected_slugs[$slug] = true;
            $selected_keys[$icon_data['icon'] . '|' . $icon_data['label']] = true;
        }
    }

    // Two-stage strategy: keep business-priority picks first, then backfill from mapper output.
    if (count($result) < $count) {
        $mapped_amenities = shaped_get_amenities_for_room($room_type_id, ['skip_fallback' => true]);

        foreach ($mapped_amenities as $amenity) {
            if (count($result) >= $count) {
                break;
            }

            $amenity_key = ($amenity['icon'] ?? '') . '|' . ($amenity['label'] ?? '');

            if (isset($selected_keys[$amenity_key])) {
                continue;
            }

            $result[] = $amenity;
            $selected_keys[$amenity_key] = true;
        }
    }

    usort($result, function($a, $b) {
        return ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999);
    });

    if (count($result) > $count) {
        $result = array_slice($result, 0, $count);
    }

    return $result;
}

/* =========================================================================
 * ROOM GALLERY HELPERS
 * ========================================================================= */

/**
 * Get gallery image IDs for a room type
 *
 * Reads the mphb_gallery post meta (comma-separated attachment IDs).
 * Optionally prepends the featured image.
 *
 * @param int  $room_type_id   Room type post ID
 * @param bool $with_featured  Include featured image as first item (default true)
 * @return int[] Array of attachment IDs
 */
function shaped_get_room_gallery_ids(int $room_type_id, bool $with_featured = true): array {
    // MPHB stores gallery as comma-separated IDs in mphb_gallery meta
    $gallery_meta = get_post_meta($room_type_id, 'mphb_gallery', true);
    $attachment_ids = !empty($gallery_meta) ? array_map('intval', explode(',', $gallery_meta)) : [];

    if ($with_featured) {
        $thumbnail_id = get_post_thumbnail_id($room_type_id);
        if ($thumbnail_id && !in_array((int) $thumbnail_id, $attachment_ids, true)) {
            array_unshift($attachment_ids, (int) $thumbnail_id);
        }
    }

    return $attachment_ids;
}

/**
 * Get gallery image data for a room type (IDs + URLs)
 *
 * @param int    $room_type_id Room type post ID
 * @param string $size         Image size (default 'large')
 * @return array[] Array of ['id' => int, 'url' => string, 'alt' => string]
 */
function shaped_get_room_gallery(int $room_type_id, string $size = 'large'): array {
    $ids    = shaped_get_room_gallery_ids($room_type_id);
    $images = [];

    foreach ($ids as $id) {
        $url = wp_get_attachment_image_url($id, $size);
        if ($url) {
            $images[] = [
                'id'  => $id,
                'url' => $url,
                'alt' => get_post_meta($id, '_wp_attachment_image_alt', true) ?: get_the_title($room_type_id),
            ];
        }
    }

    return $images;
}
