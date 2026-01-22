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

// Module toggles - configurable via admin UI or wp-config.php
// Priority: 1) wp-config.php constants (backward compatibility)  2) Database options  3) Defaults
if (!defined('SHAPED_ENABLE_ROOMCLOUD')) {
    $roomcloud_option = get_option('shaped_enable_roomcloud', null);
    $roomcloud_enabled = $roomcloud_option !== null ? (bool) $roomcloud_option : false;
    define('SHAPED_ENABLE_ROOMCLOUD', $roomcloud_enabled);
}
if (!defined('SHAPED_ENABLE_REVIEWS')) {
    $reviews_option = get_option('shaped_enable_reviews', null);
    $reviews_enabled = $reviews_option !== null ? (bool) $reviews_option : true;
    define('SHAPED_ENABLE_REVIEWS', $reviews_enabled);
}
if (!defined('SHAPED_ENABLE_REVIEW_EMAIL')) {
    $review_email_option = get_option('shaped_enable_review_email', null);
    $review_email_enabled = $review_email_option !== null ? (bool) $review_email_option : true;
    define('SHAPED_ENABLE_REVIEW_EMAIL', $review_email_enabled);
}

// Stripe credentials - can be set in wp-config.php or via Setup Wizard
// Priority: 1) Constants  2) Environment variables  3) Database (Setup Wizard)
if (!defined('SHAPED_STRIPE_SECRET')) {
    $stripe_secret = getenv('STRIPE_SECRET_KEY') ?: '';
    if (empty($stripe_secret)) {
        // Defer to database lookup via Shaped_Setup_Wizard::get_stripe_secret()
        // We define empty here; actual value retrieved at runtime
        $stripe_secret = '';
    }
    define('SHAPED_STRIPE_SECRET', $stripe_secret);
}
if (!defined('SHAPED_STRIPE_WEBHOOK')) {
    $stripe_webhook = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
    if (empty($stripe_webhook)) {
        // Defer to database lookup via Shaped_Setup_Wizard::get_stripe_webhook()
        $stripe_webhook = '';
    }
    define('SHAPED_STRIPE_WEBHOOK', $stripe_webhook);
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
    require_once SHAPED_DIR . 'includes/pricing-helpers.php';
    require_once SHAPED_DIR . 'includes/checkout-helpers.php';

    // Load brand configuration system
    require_once SHAPED_DIR . 'includes/class-brand-config.php';
    require_once SHAPED_DIR . 'config/brand-helpers.php';

    // Load font loader (generates CSS from brand.json)
    require_once SHAPED_DIR . 'includes/class-font-loader.php';

    // ─── Core Classes (always load) ───
    new Shaped_Assets();
    new Shaped_Amenity_Mapper();
    new Shaped_Payment_Processor();
    new Shaped_Booking_Manager();
    Shaped_Pricing::init();
    Shaped_Admin::init();

    // ─── Admin View System ───
    Shaped_Role_Manager::init();
    Shaped_Menu_Controller::init();
    Shaped_Noise_Control::init();
    Shaped_Reviews_Dashboard::init();

    // ─── Setup Wizard ───
    require_once SHAPED_DIR . 'includes/class-setup-wizard.php';
    Shaped_Setup_Wizard::init();

    // ─── Pricing Service (unified pricing API) ───
    require_once SHAPED_DIR . 'includes/pricing/init.php';
    shaped_init_pricing_service();

    // Load email templates system (consolidated)
    require_once SHAPED_DIR . 'includes/email/email-templates.php';

    // Load email handlers (procedural)
    require_once SHAPED_DIR . 'core/email-handler.php';
    require_once SHAPED_DIR . 'core/email-handler-admin.php';
    
    // Compatibility wrappers
    require_once SHAPED_DIR . 'includes/compat-functions.php';
    
    // ─── Shortcodes ───
    require_once SHAPED_DIR . 'shortcodes/room-details.php';
    require_once SHAPED_DIR . 'shortcodes/room-meta.php';
    require_once SHAPED_DIR . 'shortcodes/room-cards.php';
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

    // Elementor Sync (always enabled if Elementor is active)
    if (file_exists(SHAPED_DIR . 'modules/elementor-sync/module.php')) {
        require_once SHAPED_DIR . 'modules/elementor-sync/module.php';
    }

    // Review Email System (automated review request emails)
    if (SHAPED_ENABLE_REVIEW_EMAIL && file_exists(SHAPED_DIR . 'modules/review-email/module.php')) {
        require_once SHAPED_DIR . 'modules/review-email/module.php';
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

    // Localize brand colors for JavaScript
    if (function_exists('shaped_brand_colors_for_js')) {
        wp_localize_script($handle, 'ShapedBrand', shaped_brand_colors_for_js());
    }
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

    // Use Setup Wizard's credential check (supports both constants and database)
    if (class_exists('Shaped_Setup_Wizard')) {
        $has_secret = Shaped_Setup_Wizard::get_stripe_secret() !== '';
        $has_webhook = Shaped_Setup_Wizard::get_stripe_webhook() !== '';

        if (!$has_secret || !$has_webhook) {
            $wizard_url = admin_url('admin.php?page=shaped-setup-wizard');
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Shaped Core:</strong> Stripe keys not configured. ';
            echo '<a href="' . esc_url($wizard_url) . '">Run the Setup Wizard</a> or add constants to <code>wp-config.php</code>';
            echo '</p></div>';
        }
    }
});

/* =========================================================================
 * ACTIVATION / DEACTIVATION
 * ========================================================================= */

register_activation_hook(__FILE__, 'shaped_activate');
register_deactivation_hook(__FILE__, 'shaped_deactivate');

function shaped_activate() {
    // Set default discounts if not exists
    // Fetch room types dynamically from MotoPress (if available)
    // Otherwise start with empty array - admin can configure later
    if (!get_option('shaped_discounts')) {
        $discounts = [];

        // Try to fetch room types from MotoPress
        $room_types = get_posts([
            'post_type'      => 'mphb_room_type',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        if (!empty($room_types)) {
            foreach ($room_types as $post_id) {
                $slug = sanitize_title(get_the_title($post_id));
                $discounts[$slug] = 0; // Default 0% discount, admin configures via UI
            }
        }

        update_option('shaped_discounts', $discounts);
    }

    // Create Official Prices page
    require_once SHAPED_DIR . 'includes/pricing/class-official-prices-page.php';
    Shaped_Official_Prices_Page::create_page();

    // Create custom roles for admin view system
    require_once SHAPED_DIR . 'admin/class-role-manager.php';
    Shaped_Role_Manager::create_roles();

    // Grant shaped_view_ops capability to administrators
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('shaped_view_ops');
    }

    // Trigger module activation hooks
    if (SHAPED_ENABLE_ROOMCLOUD) {
        do_action('shaped_activate_module_roomcloud');
    }
    if (SHAPED_ENABLE_REVIEWS) {
        do_action('shaped_activate_module_reviews');
    }

    // Trigger Elementor color sync (if Elementor is active)
    do_action('shaped/elementor/trigger_sync');

    // Set transient to redirect to setup wizard
    set_transient('shaped_activation_redirect', true, 30);

    flush_rewrite_rules();
}

function shaped_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('shaped_check_abandoned_bookings');
    wp_clear_scheduled_hook('shaped_daily_charge_fallback');
    wp_clear_scheduled_hook('shaped_charge_single_booking');
    wp_clear_scheduled_hook('shaped_elementor_daily_sync');
    wp_clear_scheduled_hook('shaped_process_review_emails');

    flush_rewrite_rules();
}
