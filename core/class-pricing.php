<?php
/**
 * Shaped Pricing Module
 *
 * Handles discount logic and payment mode configuration.
 * Base prices and seasonal rates come from MotoPress Rates.
 *
 * @package Shaped_Core
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Pricing {

    /**
     * Option keys
     */
    const OPT_DISCOUNTS                     = 'shaped_discounts';
    const OPT_PAYMENT_MODE                  = 'shaped_payment_mode';                  // 'scheduled' | 'deposit'
    const OPT_DEPOSIT_PERCENT               = 'shaped_deposit_percent';               // int 1-100
    const OPT_SCHEDULED_CHARGE_THRESHOLD    = 'shaped_scheduled_charge_threshold';    // int 0-60 days

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
     * Fetch room types from MotoPress
     */
    public static function fetch_room_types(): array {
        if (!function_exists('MPHB')) {
            return [];
        }

        $room_types = [];
        $posts = get_posts([
            'post_type'      => 'mphb_room_type',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        foreach ($posts as $post) {
            $slug = sanitize_title($post->post_title);
            $room_types[$slug] = $post->post_title;
        }

        return $room_types;
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
        $room_types = self::fetch_room_types();

        foreach ($room_types as $slug => $title) {
            $val = isset($input[$slug]) ? intval($input[$slug]) : 0;
            $output[$slug] = max(0, min(100, $val));
        }

        return $output;
    }

    /**
     * Sanitize payment mode
     */
    public static function sanitize_payment_mode($input): string {
        return in_array($input, ['scheduled', 'deposit'], true) ? $input : 'scheduled';
    }

    /**
     * Sanitize deposit percentage
     */
    public static function sanitize_deposit_percent($input): int {
        $val = intval($input);
        return max(1, min(100, $val));
    }

    /**
     * Sanitize scheduled charge threshold days
     */
    public static function sanitize_scheduled_threshold($input): int {
        $val = intval($input);
        return max(0, min(60, $val));
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

        register_setting('shaped_pricing_group', self::OPT_PAYMENT_MODE, [
            'type'              => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_payment_mode'],
            'default'           => 'scheduled',
        ]);

        register_setting('shaped_pricing_group', self::OPT_DEPOSIT_PERCENT, [
            'type'              => 'integer',
            'sanitize_callback' => [__CLASS__, 'sanitize_deposit_percent'],
            'default'           => 30,
        ]);

        register_setting('shaped_pricing_group', self::OPT_SCHEDULED_CHARGE_THRESHOLD, [
            'type'              => 'integer',
            'sanitize_callback' => [__CLASS__, 'sanitize_scheduled_threshold'],
            'default'           => 7,
        ]);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu(): void {
        add_menu_page(
            'Shaped Pricing',
            'Shaped Pricing',
            'shaped_view_ops',
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
        if (!current_user_can('shaped_view_ops')) {
            return;
        }

        $discounts           = get_option(self::OPT_DISCOUNTS, self::discount_defaults());
        $payment_mode        = get_option(self::OPT_PAYMENT_MODE, 'scheduled');
        $deposit_percent     = get_option(self::OPT_DEPOSIT_PERCENT, 30);
        $scheduled_threshold = get_option(self::OPT_SCHEDULED_CHARGE_THRESHOLD, 7);
        $room_types          = self::fetch_room_types();

        if (empty($room_types)) {
            ?>
            <div class="wrap">
                <h1>Shaped Direct Booking Settings</h1>
                <div class="notice notice-warning">
                    <p><strong>No room types found.</strong> Please create room types in MotoPress Hotel Booking first.</p>
                </div>
            </div>
            <?php
            return;
        }
        ?>
        <div class="wrap shaped-admin-wrap shaped-pricing-wrap">
            <h1>Shaped Direct Booking Settings</h1>

            <form method="post" action="options.php">
                <?php settings_fields('shaped_pricing_group'); ?>

                <!-- Payment Mode Section -->
                <div class="shaped-admin-section">
                    <h2>Payment Mode</h2>
                    <p class="description">
                        Choose how guests pay when booking directly.
                    </p>

                    <fieldset class="shaped-option-group">
                        <!-- Scheduled Charge Option -->
                        <div class="shaped-option-item<?php echo $payment_mode === 'scheduled' ? ' is-selected' : ''; ?>" data-mode="scheduled">
                            <label class="shaped-option-label<?php echo $payment_mode === 'scheduled' ? ' is-selected' : ''; ?>">
                                <input type="radio"
                                       name="<?php echo esc_attr(self::OPT_PAYMENT_MODE); ?>"
                                       value="scheduled"
                                       <?php checked($payment_mode, 'scheduled'); ?>>
                                <strong>Scheduled Charge</strong>
                                <span id="scheduled-mode-description" class="option-description">
                                    Bookings &lt;<span class="threshold-days"><?php echo esc_html($scheduled_threshold); ?></span> days out: charge full amount immediately<br>
                                    Bookings ≥<span class="threshold-days"><?php echo esc_html($scheduled_threshold); ?></span> days out: save card, charge automatically <span class="threshold-days"><?php echo esc_html($scheduled_threshold); ?></span> days before check-in
                                </span>
                            </label>

                            <!-- Scheduled Charge Threshold Days (nested under Scheduled option) -->
                            <div id="scheduled-settings" class="shaped-settings-panel shaped-settings-panel--success<?php echo $payment_mode === 'scheduled' ? ' is-visible' : ''; ?>">
                                <label for="scheduled-threshold">
                                    Charge later if check-in is at least (days) away
                                </label>
                                <input type="number"
                                       id="scheduled-threshold"
                                       name="<?php echo esc_attr(self::OPT_SCHEDULED_CHARGE_THRESHOLD); ?>"
                                       value="<?php echo esc_attr($scheduled_threshold); ?>"
                                       min="0"
                                       max="60"
                                       step="1">
                                <span>days</span>
                                <p class="description">
                                    If check-in is sooner than this, charge 100% immediately. If equal or greater, save card and charge at T-<span class="threshold-days-help"><?php echo esc_html($scheduled_threshold); ?></span> days.<br>
                                    <em>Example: <span class="threshold-days-example"><?php echo esc_html($scheduled_threshold); ?></span> days threshold = charge on booking if &lt;<span class="threshold-days-example"><?php echo esc_html($scheduled_threshold); ?></span> days out, otherwise charge <span class="threshold-days-example"><?php echo esc_html($scheduled_threshold); ?></span> days before arrival.</em>
                                </p>
                            </div>
                        </div>

                        <!-- Deposit Option -->
                        <div class="shaped-option-item<?php echo $payment_mode === 'deposit' ? ' is-selected' : ''; ?>" data-mode="deposit">
                            <label class="shaped-option-label<?php echo $payment_mode === 'deposit' ? ' is-selected' : ''; ?>">
                                <input type="radio"
                                       name="<?php echo esc_attr(self::OPT_PAYMENT_MODE); ?>"
                                       value="deposit"
                                       <?php checked($payment_mode, 'deposit'); ?>>
                                <strong>Deposit</strong>
                                <span class="option-description">
                                    All bookings: charge deposit percentage immediately, guest pays balance on arrival
                                </span>
                            </label>

                            <!-- Deposit Percentage (nested under Deposit option) -->
                            <div id="deposit-settings" class="shaped-settings-panel shaped-settings-panel--primary<?php echo $payment_mode === 'deposit' ? ' is-visible' : ''; ?>">
                                <label for="deposit-percent">
                                    Deposit Percentage
                                </label>
                                <input type="number"
                                       id="deposit-percent"
                                       name="<?php echo esc_attr(self::OPT_DEPOSIT_PERCENT); ?>"
                                       value="<?php echo esc_attr($deposit_percent); ?>"
                                       min="1"
                                       max="100"
                                       step="1">
                                <span>%</span>
                                <p class="description">
                                    Example: 30% deposit on €200 booking = €60 charged now, €140 due on arrival
                                </p>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <!-- Discounts Section -->
                <div class="shaped-admin-section">
                    <h2>Direct Booking Discounts</h2>
                    <p class="description">
                        Set the discount percentage guests receive when booking directly vs OTAs.
                    </p>

                    <table class="wp-list-table widefat fixed striped shaped-pricing-table">
                        <thead>
                            <tr>
                                <th>Room Type</th>
                                <th>Direct Booking Discount (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($room_types as $slug => $title):
                                $discount = isset($discounts[$slug]) ? intval($discounts[$slug]) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($title); ?></strong></td>
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
                </div>

                <?php submit_button('Save Settings'); ?>
            </form>

            <script>
            (function() {
                const modeInputs = document.querySelectorAll('input[name="<?php echo esc_js(self::OPT_PAYMENT_MODE); ?>"]');
                const optionItems = document.querySelectorAll('.shaped-option-item');
                const depositSettings = document.getElementById('deposit-settings');
                const scheduledSettings = document.getElementById('scheduled-settings');
                const thresholdInput = document.getElementById('scheduled-threshold');
                const thresholdSpans = document.querySelectorAll('.threshold-days');
                const thresholdHelpSpans = document.querySelectorAll('.threshold-days-help, .threshold-days-example');

                // Update threshold display when input changes
                if (thresholdInput) {
                    thresholdInput.addEventListener('input', function() {
                        const val = this.value;
                        // Update all threshold displays
                        thresholdSpans.forEach(span => {
                            span.textContent = val;
                        });
                        thresholdHelpSpans.forEach(span => {
                            span.textContent = val;
                        });
                    });
                }

                modeInputs.forEach(input => {
                    input.addEventListener('change', function() {
                        const selectedMode = this.value;

                        // Update option item and label states
                        optionItems.forEach(item => {
                            const itemMode = item.dataset.mode;
                            const label = item.querySelector('.shaped-option-label');
                            const panel = item.querySelector('.shaped-settings-panel');

                            if (itemMode === selectedMode) {
                                item.classList.add('is-selected');
                                label.classList.add('is-selected');
                                if (panel) panel.classList.add('is-visible');
                            } else {
                                item.classList.remove('is-selected');
                                label.classList.remove('is-selected');
                                if (panel) panel.classList.remove('is-visible');
                            }
                        });
                    });
                });
            })();
            </script>

            <div class="shaped-admin-info">
                <h3>How it works</h3>

                <h4>Scheduled Charge Mode</h4>
                <ol>
                    <li>Guest books ≥<?php echo esc_html($scheduled_threshold); ?> days before check-in → saves card, charged <?php echo esc_html($scheduled_threshold); ?> days before arrival</li>
                    <li>Guest books &lt;<?php echo esc_html($scheduled_threshold); ?> days before check-in → charged full amount immediately</li>
                    <li>Free cancellation until <?php echo esc_html($scheduled_threshold); ?> days before check-in</li>
                </ol>

                <h4>Deposit Mode</h4>
                <ol>
                    <li>Guest pays <?php echo esc_html($deposit_percent); ?>% deposit immediately for ALL bookings</li>
                    <li>Remaining balance is paid on arrival at the property</li>
                    <li>Deposit is non-refundable (configurable per your policy)</li>
                </ol>
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
     * Get payment mode setting
     * 
     * @return string 'scheduled' | 'deposit'
     */
    public static function get_payment_mode(): string {
        return get_option(self::OPT_PAYMENT_MODE, 'scheduled');
    }

    /**
     * Check if deposit mode is enabled
     */
    public static function is_deposit_mode(): bool {
        return self::get_payment_mode() === 'deposit';
    }

    /**
     * Get deposit percentage
     *
     * @return int 1-100
     */
    public static function get_deposit_percent(): int {
        return (int) get_option(self::OPT_DEPOSIT_PERCENT, 30);
    }

    /**
     * Get scheduled charge threshold days
     *
     * For Scheduled-Charge mode:
     * - If check-in is < threshold days away → charge 100% immediately
     * - If check-in is >= threshold days away → save card, charge at T-threshold days
     *
     * @return int 0-60, default 7
     */
    public static function get_scheduled_threshold_days(): int {
        $val = get_option(self::OPT_SCHEDULED_CHARGE_THRESHOLD, 7);
        $val = intval($val);
        // Clamp to valid range for safety
        return max(0, min(60, $val));
    }

    /**
     * Calculate deposit and balance amounts
     * 
     * @param float $total Discounted total amount
     * @return array ['deposit' => float, 'balance' => float, 'percent' => int, 'total' => float]
     */
    public static function calculate_deposit(float $total): array {
        $percent = self::get_deposit_percent();
        $deposit = round($total * ($percent / 100), 2);
        $balance = round($total - $deposit, 2);

        return [
            'deposit' => $deposit,
            'balance' => $balance,
            'percent' => $percent,
            'total'   => $total,
        ];
    }

    /**
     * Localize config for JavaScript
     */
    public static function localize_config(): void {
        $config = [
            'discounts'               => self::get_discounts(),
            'paymentMode'             => self::get_payment_mode(),
            'depositPercent'          => self::get_deposit_percent(),
            'scheduledThresholdDays'  => self::get_scheduled_threshold_days(),
        ];

        if (!defined('SHAPED_DISCOUNTS')) {
            define('SHAPED_DISCOUNTS', self::get_discounts());
        }

        if (wp_script_is('shaped-checkout', 'enqueued')) {
            wp_localize_script('shaped-checkout', 'ShapedPricing', $config);
        } else {
            wp_localize_script('jquery', 'ShapedPricing', $config);
        }
    }

    /**
     * Calculate final amount with discount applied
     *
     * @param mixed $base_amount_or_booking  Either float (base price) or booking object
     * @param string|null $room_slug         Room type slug (optional if booking object provided)
     * @return array|float If booking object: returns float. If base_amount: returns array with details
     */
    public static function calculate_final_amount($base_amount_or_booking, ?string $room_slug = null) {
        // Backward compatibility: booking object
        if (is_object($base_amount_or_booking) && method_exists($base_amount_or_booking, 'getTotalPrice')) {
            $booking = $base_amount_or_booking;
            $base_amount = (float) $booking->getTotalPrice();

            $reserved_rooms = $booking->getReservedRooms();
            if (empty($reserved_rooms)) {
                return $base_amount;
            }

            $room = reset($reserved_rooms);
            $room_type_id = $room->getRoomTypeId();

            if (!$room_type_id || $room_type_id === 0) {
                $room_type_id = get_post_meta($room->getId(), 'mphb_room_type_id', true);
            }

            if (!$room_type_id) {
                return $base_amount;
            }

            $room_type = MPHB()->getRoomTypeRepository()->findById($room_type_id);
            if (!$room_type) {
                return $base_amount;
            }

            $room_slug = sanitize_title($room_type->getTitle());

            $discounts = self::get_discounts();
            $discount_percent = isset($discounts[$room_slug]) ? intval($discounts[$room_slug]) : 0;
            $discount_multiplier = (100 - $discount_percent) / 100;
            return round($base_amount * $discount_multiplier, 2);
        }

        // New API: explicit base amount and room slug
        $base_amount = (float) $base_amount_or_booking;
        $discounts = self::get_discounts();
        $slug = sanitize_title($room_slug);
        $discount_percent = isset($discounts[$slug]) ? intval($discounts[$slug]) : 0;
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
     */
    public static function get_room_discount(string $room_slug): int {
        $discounts = self::get_discounts();
        $slug = sanitize_title($room_slug);
        return isset($discounts[$slug]) ? intval($discounts[$slug]) : 0;
    }

    /**
     * Format price for display
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