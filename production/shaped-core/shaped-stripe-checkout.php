<?php
/**
 * Plugin Name: Shaped Stripe Checkout Bridge
 * Description: Orchestrator for pricing, payments, and booking management (Stripe + MPHB)
 * Version: 3.1
 * Author: Shaped Systems
 * Text Domain: shaped
 */

if (!defined('ABSPATH')) {
    exit;
}

/* =====================================================================
[00] Plugin Bootstrap
===================================================================== */

define('SHAPED_PLUGIN_FILE', __FILE__);
define('SHAPED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SHAPED_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Email handlers (kept as-is)
 */
require_once __DIR__ . '/shaped-email-handler.php';
require_once __DIR__ . '/shaped-admin-email-handler.php';
require_once __DIR__ . '/_scratch/load.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';

/**
 * Class includes
 *  - Pricing & Admin
 *  - Core Payment Logic (webhooks, sessions, charges)
 *  - Booking Management & UI (shortcodes, cancellations)
 */
require_once SHAPED_PLUGIN_DIR . 'includes/class-shaped-pricing.php';
require_once SHAPED_PLUGIN_DIR . 'includes/class-shaped-payment-processor.php';
require_once SHAPED_PLUGIN_DIR . 'includes/class-shaped-booking-manager.php';

/**
 * Initialize modules once plugins are loaded.
 */
add_action('plugins_loaded', function () {
    // Load in dependency order: Pricing → Payment → Booking
    new Shaped_Pricing();
    new Shaped_Payment_Processor();
    new Shaped_Booking_Manager();
    
    // Ensure procedural wrappers are available
    require_once SHAPED_PLUGIN_DIR . 'includes/compat-functions.php';
}, 20);


/* =====================================================================
[01] Core Constants & URLs
   - Keys should live in wp-config.php. These fallbacks read env or stay blank.
===================================================================== */

/**
 * Stripe secrets:
 *   In wp-config.php set:
 *     define('SHAPED_STRIPE_SECRET',  getenv('STRIPE_SECRET_KEY'));
 *     define('SHAPED_STRIPE_WEBHOOK', getenv('STRIPE_WEBHOOK_SECRET'));
 */
if (!defined('SHAPED_STRIPE_SECRET')) {
    define('SHAPED_STRIPE_SECRET', getenv('STRIPE_SECRET_KEY') ?: '');
}
if (!defined('SHAPED_STRIPE_WEBHOOK')) {
    define('SHAPED_STRIPE_WEBHOOK', getenv('STRIPE_WEBHOOK_SECRET') ?: '');
}

/**
 * Success/Cancel URLs used by Checkout Sessions.
 * {BOOKING_ID} will be replaced upstream before redirecting to Stripe.
 */
if (!defined('SHAPED_SUCCESS_URL')) {
    define(
        'SHAPED_SUCCESS_URL',
        apply_filters(
            'shaped/success_url',
            home_url('/thank-you/?booking_id={BOOKING_ID}')
        )
    );
}
if (!defined('SHAPED_CANCEL_URL')) {
    define(
        'SHAPED_CANCEL_URL',
        apply_filters(
            'shaped/cancel_url',
            home_url('/checkout')
        )
    );
}

/**
 * Optional: admin notice if keys are missing (debug safeguard).
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    if (!SHAPED_STRIPE_SECRET || !SHAPED_STRIPE_WEBHOOK) {
        echo '<div class="notice notice-warning"><p><strong>Shaped Stripe Checkout Bridge:</strong> Stripe keys are not set. Define <code>SHAPED_STRIPE_SECRET</code> and <code>SHAPED_STRIPE_WEBHOOK</code> in <code>wp-config.php</code>.</p></div>';
    }
});


/* =====================================================================
[11] Frontend Config & Redirect Hosts
   - Expose runtime pricing config to JS
   - Allow Stripe checkout host for safe redirects
===================================================================== */

/**
 * Localize pricing configs for front-end snippets that read from ShapedConfig.
 * Values are provided by Shaped_Pricing static accessors.
 */
add_action('wp_enqueue_scripts', function () {
    if (!class_exists('Shaped_Pricing')) {
        return;
    }

    $discounts    = method_exists('Shaped_Pricing', 'get_discounts_config')
        ? Shaped_Pricing::get_discounts_config()
        : [];
    $seasonPrices = method_exists('Shaped_Pricing', 'get_season_prices')
        ? Shaped_Pricing::get_season_prices()
        : [];

    // Bind to a script that is always present (jQuery) to ensure availability.
    wp_localize_script('jquery', 'ShapedConfig', [
        'discounts'    => $discounts,
        'seasonPrices' => $seasonPrices,
         'ajaxUrl' => admin_url('admin-ajax.php'),
    ]);
}, 20);

/**
 * Permit redirects to Stripe's hosted checkout.
 */
add_filter('allowed_redirect_hosts', function (array $hosts) {
    $hosts[] = 'checkout.stripe.com';
    return $hosts;
});


/* =====================================================================
[12] Stripe SDK Loader
   - Centralized loader so other modules can call shaped_load_stripe_sdk()
===================================================================== */

if (!function_exists('shaped_load_stripe_sdk')) {
    /**
     * Load Stripe PHP SDK (path is filterable).
     */
    function shaped_load_stripe_sdk(): void
    {
        $default_path = WP_CONTENT_DIR . '/mu-plugins/stripe-php/init.php';
        $sdk_path     = apply_filters('shaped/stripe_sdk_path', $default_path);

        if (file_exists($sdk_path)) {
            require_once $sdk_path;
        } else {
            // Soft warning for admins
            if (is_admin() && current_user_can('manage_options')) {
                add_action('admin_notices', function () use ($sdk_path) {
                    echo '<div class="notice notice-error"><p><strong>Shaped Stripe Checkout Bridge:</strong> Stripe SDK not found at <code>' . esc_html($sdk_path) . '</code>. Update the path via the <code>shaped/stripe_sdk_path</code> filter or install the SDK.</p></div>';
                });
            }
        }
    }
}