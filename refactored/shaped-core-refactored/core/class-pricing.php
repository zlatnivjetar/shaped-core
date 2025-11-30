<?php
/**
 * Shaped Pricing Module
 *
 * Handles discount logic only. Base prices and seasonal rates come from MotoPress Rates.
 *
 * @package Shaped_Core
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Pricing {

    /**
     * Option keys
     */
    const OPT_DISCOUNTS = 'shaped_discounts';

    /**
     * Room type slugs (customize per property)
     */
    private static array $room_slugs = [
        'suite',
        'apartment',
        'triple-room',
        'double-room'
    ];

    /**
     * Initialize pricing system
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        // Make config available to JS
        add_action('wp_enqueue_scripts', [__CLASS__, 'localize_config'], 20);
    }

    /**
     * Get room slugs (filterable per property)
     */
    public static function get_room_slugs(): array {
        return apply_filters('shaped/pricing/room_slugs', self::$room_slugs);
    }

    /**
     * Default discounts per room type (percentage)
     */
    public static function discount_defaults(): array {
        return apply_filters('shaped/pricing/discount_defaults', [
            'suite'        => 10,
            'apartment'    => 10,
            'triple-room'  => 10,
            'double-room'  => 10,
        ]);
    }

    /**
     * Sanitize discount input
     */
    public static function sanitize_discounts($input): array {
        $output = [];
        $slugs = self::get_room_slugs();
        
        foreach ($slugs as $slug) {
            $val = isset($input[$slug]) ? intval($input[$slug]) : 0;
            $output[$slug] = max(0, min(100, $val)); // Clamp 0-100
        }
        
        return $output;
    }

    /**
     * Register settings
     */
    public static function register_settings(): void {
        register_setting('shaped_pricing_group', self::OPT_DISCOUNTS, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_discounts'],
            'default'           => self::discount_defaults(),
        ]);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu(): void {
        add_menu_page(
            'Shaped Pricing',
            'Shaped Pricing',
            'manage_options',
            'shaped-pricing',
            [__CLASS__, 'render_admin_page'],
            'dashicons-money-alt',
            30
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook): void {
        if ($hook !== 'toplevel_page_shaped-pricing') {
            return;
        }

        wp_enqueue_style(
            'shaped-pricing-admin',
            SHAPED_URL . 'assets/css/admin-pricing.css',
            [],
            SHAPED_VERSION
        );
    }

    /**
     * Render admin page
     */
    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $discounts = get_option(self::OPT_DISCOUNTS, self::discount_defaults());
        $slugs = self::get_room_slugs();
        ?>
        <div class="wrap shaped-pricing-wrap">
            <h1>Shaped Direct Booking Discounts</h1>
            <p class="description">
                Set the discount percentage guests receive when booking directly vs OTAs.
                Base prices and seasonal rates are managed in <strong>MotoPress → Rates</strong>.
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('shaped_pricing_group'); ?>

                <table class="wp-list-table widefat fixed striped shaped-pricing-table">
                    <thead>
                        <tr>
                            <th>Room Type</th>
                            <th>Direct Booking Discount (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slugs as $slug): 
                            $label = ucwords(str_replace('-', ' ', $slug));
                            $discount = isset($discounts[$slug]) ? intval($discounts[$slug]) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($label); ?></strong></td>
                            <td>
                                <input 
                                    type="number" 
                                    name="<?php echo esc_attr(self::OPT_DISCOUNTS); ?>[<?php echo esc_attr($slug); ?>]" 
                                    value="<?php echo esc_attr($discount); ?>" 
                                    min="0" 
                                    max="100" 
                                    step="1"
                                    class="small-text"
                                />
                                <span class="description">%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button('Save Discounts'); ?>
            </form>

            <div class="shaped-pricing-info">
                <h3>How it works</h3>
                <ol>
                    <li>MotoPress calculates the base price based on your Rates configuration</li>
                    <li>The discount percentage you set here is applied to direct bookings</li>
                    <li>Guests see "Save X%" messaging compared to OTA prices</li>
                </ol>
                
                <h3>Example</h3>
                <p>If MotoPress returns €100/night and you set a 10% discount:</p>
                <ul>
                    <li>Direct booking price: €90/night</li>
                    <li>Guest sees: "Save 10% by booking direct"</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Get discounts configuration
     */
    public static function get_discounts(): array {
        $saved = get_option(self::OPT_DISCOUNTS);
        return wp_parse_args(is_array($saved) ? $saved : [], self::discount_defaults());
    }

    /**
     * Alias for backward compatibility
     */
    public static function get_discounts_config(): array {
        return self::get_discounts();
    }

    /**
     * Localize config for JavaScript
     */
    public static function localize_config(): void {
        $config = [
            'discounts' => self::get_discounts(),
        ];

        // Define global constant for PHP access
        if (!defined('SHAPED_DISCOUNTS')) {
            define('SHAPED_DISCOUNTS', self::get_discounts());
        }

        // Add to ShapedConfig if checkout script is enqueued
        if (wp_script_is('shaped-checkout', 'enqueued')) {
            wp_localize_script('shaped-checkout', 'ShapedPricing', $config);
        } else {
            // Fallback: add to jQuery
            wp_localize_script('jquery', 'ShapedPricing', $config);
        }
    }

    /**
     * Calculate final amount with discount applied
     *
     * Overloaded method that accepts either:
     * - A booking object (backward compatibility)
     * - float $base_amount and string $room_slug (new API)
     *
     * @param mixed $base_amount_or_booking  Either float (base price) or booking object
     * @param string|null $room_slug         Room type slug (optional if booking object provided)
     * @return array|float If booking object: returns float. If base_amount: returns array with details
     */
    public static function calculate_final_amount($base_amount_or_booking, ?string $room_slug = null) {
        // Backward compatibility: if first arg is a booking object, extract data from it
        if (is_object($base_amount_or_booking) && method_exists($base_amount_or_booking, 'getTotalPrice')) {
            $booking = $base_amount_or_booking;
            $base_amount = (float) $booking->getTotalPrice();

            // Get room type slug from booking
            $reserved_rooms = $booking->getReservedRooms();
            if (empty($reserved_rooms)) {
                return $base_amount; // No discount if no room found
            }

            $room = reset($reserved_rooms);
            $room_type_id = $room->getRoomTypeId();

            // Fix: MotoPress API sometimes returns 0, fallback to direct meta read
            if (!$room_type_id || $room_type_id === 0) {
                $room_type_id = get_post_meta($room->getId(), 'mphb_room_type_id', true);
            }

            if (!$room_type_id) {
                return $base_amount; // No discount if room type not found
            }

            $room_type = MPHB()->getRoomTypeRepository()->findById($room_type_id);
            if (!$room_type) {
                return $base_amount; // No discount if room type not found
            }

            $room_slug = sanitize_title($room_type->getTitle());

            // Calculate and return just the final amount (backward compat)
            $discounts = self::get_discounts();
            $discount_percent = isset($discounts[$room_slug]) ? intval($discounts[$room_slug]) : 0;
            $discount_multiplier = (100 - $discount_percent) / 100;
            return round($base_amount * $discount_multiplier, 2);
        }

        // New API: calculate with explicit base amount and room slug
        $base_amount = (float) $base_amount_or_booking;
        $discounts = self::get_discounts();

        // Normalize slug
        $slug = sanitize_title($room_slug);

        // Get discount for this room type (default 0 if not found)
        $discount_percent = isset($discounts[$slug]) ? intval($discounts[$slug]) : 0;

        // Calculate
        $discount_multiplier = (100 - $discount_percent) / 100;
        $final = round($base_amount * $discount_multiplier, 2);
        $saved = round($base_amount - $final, 2);

        return [
            'final'            => $final,
            'discount_percent' => $discount_percent,
            'saved'            => $saved,
            'base'             => $base_amount,
        ];
    }

    /**
     * Get discount for specific room type
     * 
     * @param string $room_slug
     * @return int Discount percentage
     */
    public static function get_room_discount(string $room_slug): int {
        $discounts = self::get_discounts();
        $slug = sanitize_title($room_slug);
        
        return isset($discounts[$slug]) ? intval($discounts[$slug]) : 0;
    }

    /**
     * Format price for display
     * 
     * @param float  $amount
     * @param string $currency
     * @return string
     */
    public static function format_price(float $amount, string $currency = 'EUR'): string {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'HRK' => 'kn',
        ];
        
        $symbol = $symbols[$currency] ?? $currency . ' ';
        
        return $symbol . number_format($amount, 2, ',', '.');
    }
}
