<?php
/**
 * Plugin Name: Shaped Core
 * Description: Direct booking system for boutique hospitality - Stripe payments, booking management, and modular integrations
 * Version: 2.0.0
 * Author: Shaped Systems
 * Text Domain: shaped
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/* =========================================================================
 * CONSTANTS
 * ========================================================================= */

define('SHAPED_VERSION', '2.0.0');
define('SHAPED_FILE', __FILE__);
define('SHAPED_DIR', plugin_dir_path(__FILE__));
define('SHAPED_URL', plugin_dir_url(__FILE__));

// Module toggles - set in wp-config.php to enable
if (!defined('SHAPED_ENABLE_ROOMCLOUD')) {
    define('SHAPED_ENABLE_ROOMCLOUD', false);
}
if (!defined('SHAPED_ENABLE_REVIEWS')) {
    define('SHAPED_ENABLE_REVIEWS', true);
}

// Stripe credentials - MUST be set in wp-config.php
if (!defined('SHAPED_STRIPE_SECRET')) {
    define('SHAPED_STRIPE_SECRET', getenv('STRIPE_SECRET_KEY') ?: '');
}
if (!defined('SHAPED_STRIPE_WEBHOOK')) {
    define('SHAPED_STRIPE_WEBHOOK', getenv('STRIPE_WEBHOOK_SECRET') ?: '');
}

// Redirect URLs (filterable)
if (!defined('SHAPED_SUCCESS_URL')) {
    define('SHAPED_SUCCESS_URL', home_url('/thank-you/?booking_id={BOOKING_ID}'));
}
if (!defined('SHAPED_CANCEL_URL')) {
    define('SHAPED_CANCEL_URL', home_url('/checkout'));
}

// Legacy constant aliases (for backward compatibility with existing code)
if (!defined('SHAPED_PLUGIN_FILE')) {
    define('SHAPED_PLUGIN_FILE', SHAPED_FILE);
}
if (!defined('SHAPED_PLUGIN_DIR')) {
    define('SHAPED_PLUGIN_DIR', SHAPED_DIR);
}
if (!defined('SHAPED_PLUGIN_URL')) {
    define('SHAPED_PLUGIN_URL', SHAPED_URL);
}

/* =========================================================================
 * AUTOLOADER
 * ========================================================================= */

require_once SHAPED_DIR . 'includes/class-loader.php';
Shaped_Loader::register();

/* =========================================================================
 * STRIPE SDK LOADER
 * ========================================================================= */

if (!function_exists('shaped_load_stripe_sdk')) {
    function shaped_load_stripe_sdk(): void {
        static $loaded = false;
        if ($loaded) return;

        $plugin_path = SHAPED_DIR . 'vendor/stripe-php/init.php';
        $mu_path     = WP_CONTENT_DIR . '/mu-plugins/stripe-php/init.php';

        $sdk_path = apply_filters(
            'shaped/stripe_sdk_path',
            file_exists($plugin_path) ? $plugin_path : $mu_path
        );

        if (file_exists($sdk_path)) {
            require_once $sdk_path;

            \Stripe\Stripe::setAppInfo(
                "ShapedSystems",
                SHAPED_VERSION,
                "https://shapedsystems.com"
            );
            // (Optional but recommended: pin API version)
            // \Stripe\Stripe::setApiVersion('2024-06-20');

            $loaded = true;
        } else {
            if (is_admin() && current_user_can('manage_options')) {
                add_action('admin_notices', function() use ($sdk_path) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>Shaped Core:</strong> Stripe SDK not found. ';
                    echo 'Place the Stripe PHP library in <code>' . esc_html(SHAPED_DIR) . 'vendor/stripe-php/</code>';
                    echo '</p></div>';
                });
            }
        }
    }
}


/* =========================================================================
 * INITIALIZATION
 * ========================================================================= */

add_action('plugins_loaded', function() {
    
    // Dependency check: MotoPress Hotel Booking required
    if (!function_exists('MPHB')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Shaped Core:</strong> MotoPress Hotel Booking plugin is required.';
            echo '</p></div>';
        });
        return;
    }
    
    // Load helper functions
    require_once SHAPED_DIR . 'includes/helpers.php';
    
    // ─── Core Classes (always load) ───
    new Shaped_Assets();
    new Shaped_Amenity_Mapper();
    new Shaped_Payment_Processor();
    new Shaped_Booking_Manager();
    Shaped_Pricing::init();
    Shaped_Admin::init();
    
    // Load email handlers (procedural)
    require_once SHAPED_DIR . 'core/email-handler.php';
    require_once SHAPED_DIR . 'core/email-handler-admin.php';
    
    // Compatibility wrappers
    require_once SHAPED_DIR . 'includes/compat-functions.php';
    
    // ─── Shortcodes ───
    require_once SHAPED_DIR . 'shortcodes/room-details.php';
    require_once SHAPED_DIR . 'shortcodes/room-meta.php';
    require_once SHAPED_DIR . 'shortcodes/class-provider-badge.php';
    require_once SHAPED_DIR . 'shortcodes/class-modal-link.php';
    
    // ─── Schema Markup ───
    if (file_exists(SHAPED_DIR . 'schema/markup.php')) {
        require_once SHAPED_DIR . 'schema/markup.php';
    }
    
    // ─── Modules ───
    
    // RoomCloud Integration
    if (SHAPED_ENABLE_ROOMCLOUD && file_exists(SHAPED_DIR . 'modules/roomcloud/module.php')) {
        require_once SHAPED_DIR . 'modules/roomcloud/module.php';
    }
    
    // Reviews System
    if (SHAPED_ENABLE_REVIEWS && file_exists(SHAPED_DIR . 'modules/reviews/module.php')) {
        require_once SHAPED_DIR . 'modules/reviews/module.php';
    }
    
}, 20); // Priority 20 to ensure MPHB is loaded first

/* =========================================================================
 * FRONTEND CONFIG
 * ========================================================================= */

/**
 * Localize pricing configuration for frontend JavaScript
 */
add_action('wp_enqueue_scripts', function() {
    if (!class_exists('Shaped_Pricing')) {
        return;
    }
    
    $config = [
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('shaped_ajax'),
        'discounts'    => Shaped_Pricing::get_discounts_config(),
    ];
    
    // Attach to jQuery (always available) or shaped-checkout if enqueued
    $handle = wp_script_is('shaped-checkout', 'enqueued') ? 'shaped-checkout' : 'jquery';
    wp_localize_script($handle, 'ShapedConfig', $config);
}, 25);

/**
 * Allow redirects to Stripe hosted checkout
 */
add_filter('allowed_redirect_hosts', function(array $hosts) {
    $hosts[] = 'checkout.stripe.com';
    return $hosts;
});

/**
 * Load checkout modals template in footer
 */
add_action('wp_footer', function() {
    // Only on checkout pages
    if (is_page(['checkout', 'book', 'booking'])) {
        require_once SHAPED_DIR . 'templates/checkout-modals.php';
        return;
    }

    // Or pages with checkout shortcode
    global $post;
    if ($post && has_shortcode($post->post_content, 'mphb_checkout')) {
        require_once SHAPED_DIR . 'templates/checkout-modals.php';
    }
}, 999);

/* =========================================================================
 * ADMIN NOTICES
 * ========================================================================= */

add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Warn if Stripe keys are missing
    if (!SHAPED_STRIPE_SECRET || !SHAPED_STRIPE_WEBHOOK) {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>Shaped Core:</strong> Stripe keys not configured. ';
        echo 'Add <code>SHAPED_STRIPE_SECRET</code> and <code>SHAPED_STRIPE_WEBHOOK</code> to <code>wp-config.php</code>';
        echo '</p></div>';
    }
});

/* =========================================================================
 * ACTIVATION / DEACTIVATION
 * ========================================================================= */

register_activation_hook(__FILE__, 'shaped_activate');
register_deactivation_hook(__FILE__, 'shaped_deactivate');

function shaped_activate() {
    // Set default options if not exists
    if (!get_option('shaped_discounts')) {
        update_option('shaped_discounts', [
            'deluxe-studio-apartment'   => 15,
            'superior-studio-apartment' => 15,
            'deluxe-double-room'        => 10,
            'studio-apartment'          => 20,
        ]);
    }
    
    // Trigger module activation hooks
    if (SHAPED_ENABLE_ROOMCLOUD) {
        do_action('shaped_activate_module_roomcloud');
    }
    if (SHAPED_ENABLE_REVIEWS) {
        do_action('shaped_activate_module_reviews');
    }
    
    flush_rewrite_rules();
}

function shaped_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('shaped_check_abandoned_bookings');
    wp_clear_scheduled_hook('shaped_daily_charge_fallback');
    wp_clear_scheduled_hook('shaped_charge_single_booking');
    
    flush_rewrite_rules();
}
