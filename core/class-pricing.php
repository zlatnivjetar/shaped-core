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
    const OPT_DISCOUNT_RANGES               = 'shaped_discount_ranges';               // legacy, migrated to seasons
    const OPT_DISCOUNT_SEASONS              = 'shaped_discount_seasons';              // recurring + year-specific overrides
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
        add_action('admin_init', [__CLASS__, 'maybe_migrate_discount_ranges']);
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
     * Sanitize discount seasons input
     *
     * Handles both recurring (dd/mm → mm-dd) and overrides (dd/mm/yyyy → yyyy-mm-dd).
     * Validates formats, clamps percentages, and rejects overlapping ranges within each tier.
     *
     * @param mixed $input Raw form input with 'recurring' and 'overrides' keys
     * @return array Sanitized seasons array
     */
    public static function sanitize_discount_seasons($input): array {
        if (!is_array($input)) {
            return ['recurring' => [], 'overrides' => []];
        }

        $room_types = self::fetch_room_types();
        $output = ['recurring' => [], 'overrides' => []];

        // --- Recurring seasons (dd/mm text inputs → mm-dd storage) ---
        if (!empty($input['recurring']) && is_array($input['recurring'])) {
            $recurring = [];
            foreach ($input['recurring'] as $season) {
                $start_raw = isset($season['start_day']) ? sanitize_text_field($season['start_day']) : '';
                $end_raw   = isset($season['end_day'])   ? sanitize_text_field($season['end_day'])   : '';

                if (empty($start_raw) || empty($end_raw)) {
                    continue;
                }

                // Accept dd/mm display format and convert to mm-dd
                $start_md = self::ddmm_to_mmdd($start_raw);
                $end_md   = self::ddmm_to_mmdd($end_raw);

                // Also accept already-stored mm-dd format
                if (!$start_md && preg_match('/^\d{2}-\d{2}$/', $start_raw)) {
                    $start_md = $start_raw;
                }
                if (!$end_md && preg_match('/^\d{2}-\d{2}$/', $end_raw)) {
                    $end_md = $end_raw;
                }

                if (!$start_md || !$end_md) {
                    continue;
                }

                $label = isset($season['label']) ? sanitize_text_field($season['label']) : '';

                $discounts = [];
                foreach ($room_types as $slug => $title) {
                    $val = isset($season['discounts'][$slug]) ? intval($season['discounts'][$slug]) : 0;
                    $discounts[$slug] = max(0, min(100, $val));
                }

                $recurring[] = [
                    'start_day' => $start_md,
                    'end_day'   => $end_md,
                    'label'     => $label,
                    'discounts' => $discounts,
                ];
            }

            // Sort by start_day
            usort($recurring, function ($a, $b) {
                return strcmp($a['start_day'], $b['start_day']);
            });

            // Reject overlapping recurring seasons (keeping first)
            $cleaned = [];
            foreach ($recurring as $season) {
                $overlaps = false;
                foreach ($cleaned as $existing) {
                    if (self::recurring_ranges_overlap(
                        $existing['start_day'], $existing['end_day'],
                        $season['start_day'], $season['end_day']
                    )) {
                        $overlaps = true;
                        break;
                    }
                }
                if (!$overlaps) {
                    $cleaned[] = $season;
                }
            }
            $output['recurring'] = $cleaned;
        }

        // --- Year-specific overrides (dd/mm/yyyy text inputs → yyyy-mm-dd storage) ---
        if (!empty($input['overrides']) && is_array($input['overrides'])) {
            $overrides = [];
            foreach ($input['overrides'] as $override) {
                $start_raw = isset($override['start_date']) ? sanitize_text_field($override['start_date']) : '';
                $end_raw   = isset($override['end_date'])   ? sanitize_text_field($override['end_date'])   : '';

                if (empty($start_raw) || empty($end_raw)) {
                    continue;
                }

                // Accept dd/mm/yyyy display format and convert to yyyy-mm-dd
                $start_ymd = self::ddmmyyyy_to_ymd($start_raw);
                $end_ymd   = self::ddmmyyyy_to_ymd($end_raw);

                // Also accept already-stored yyyy-mm-dd format
                if (!$start_ymd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_raw)) {
                    $start_ymd = $start_raw;
                }
                if (!$end_ymd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_raw)) {
                    $end_ymd = $end_raw;
                }

                if (!$start_ymd || !$end_ymd) {
                    continue;
                }

                // Ensure start <= end
                if ($start_ymd > $end_ymd) {
                    continue;
                }

                $label = isset($override['label']) ? sanitize_text_field($override['label']) : '';

                $discounts = [];
                foreach ($room_types as $slug => $title) {
                    $val = isset($override['discounts'][$slug]) ? intval($override['discounts'][$slug]) : 0;
                    $discounts[$slug] = max(0, min(100, $val));
                }

                $overrides[] = [
                    'start_date' => $start_ymd,
                    'end_date'   => $end_ymd,
                    'label'      => $label,
                    'discounts'  => $discounts,
                ];
            }

            // Sort by start_date
            usort($overrides, function ($a, $b) {
                return strcmp($a['start_date'], $b['start_date']);
            });

            // Reject overlapping overrides (keeping first)
            $cleaned = [];
            foreach ($overrides as $override) {
                $overlaps = false;
                foreach ($cleaned as $existing) {
                    if ($override['start_date'] <= $existing['end_date'] && $override['end_date'] >= $existing['start_date']) {
                        $overlaps = true;
                        break;
                    }
                }
                if (!$overlaps) {
                    $cleaned[] = $override;
                }
            }
            $output['overrides'] = $cleaned;
        }

        return $output;
    }

    /**
     * Convert dd/mm display format to mm-dd storage format
     *
     * @param string $ddmm Date in dd/mm format
     * @return string|false mm-dd string or false on invalid input
     */
    private static function ddmm_to_mmdd(string $ddmm) {
        if (!preg_match('/^(\d{2})\/(\d{2})$/', $ddmm, $m)) {
            return false;
        }
        $day   = $m[1];
        $month = $m[2];
        if ((int) $month < 1 || (int) $month > 12 || (int) $day < 1 || (int) $day > 31) {
            return false;
        }
        return $month . '-' . $day;
    }

    /**
     * Convert mm-dd storage format to dd/mm display format
     *
     * @param string $mmdd Date in mm-dd format
     * @return string dd/mm string
     */
    private static function mmdd_to_ddmm(string $mmdd): string {
        $parts = explode('-', $mmdd);
        if (count($parts) !== 2) {
            return $mmdd;
        }
        return $parts[1] . '/' . $parts[0];
    }

    /**
     * Convert dd/mm/yyyy display format to yyyy-mm-dd storage format
     *
     * @param string $ddmmyyyy Date in dd/mm/yyyy format
     * @return string|false yyyy-mm-dd string or false on invalid input
     */
    private static function ddmmyyyy_to_ymd(string $ddmmyyyy) {
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $ddmmyyyy, $m)) {
            return false;
        }
        $day   = $m[1];
        $month = $m[2];
        $year  = $m[3];
        if ((int) $month < 1 || (int) $month > 12 || (int) $day < 1 || (int) $day > 31) {
            return false;
        }
        return $year . '-' . $month . '-' . $day;
    }

    /**
     * Convert yyyy-mm-dd storage format to dd/mm/yyyy display format
     *
     * @param string $ymd Date in yyyy-mm-dd format
     * @return string dd/mm/yyyy string
     */
    private static function ymd_to_ddmmyyyy(string $ymd): string {
        $parts = explode('-', $ymd);
        if (count($parts) !== 3) {
            return $ymd;
        }
        return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
    }

    /**
     * Check if two recurring mm-dd ranges overlap (handles year-wrap)
     *
     * Uses boundary-point checks instead of day-set expansion to avoid
     * DateTime overflow issues with dates like 09-31 (Sep only has 30 days).
     *
     * @param string $a_start mm-dd
     * @param string $a_end   mm-dd
     * @param string $b_start mm-dd
     * @param string $b_end   mm-dd
     * @return bool
     */
    private static function recurring_ranges_overlap(string $a_start, string $a_end, string $b_start, string $b_end): bool {
        $in_range = function (string $point, string $start, string $end): bool {
            if ($start <= $end) {
                // Normal range (e.g. 01-01 to 05-31)
                return $point >= $start && $point <= $end;
            }
            // Wrapping range (e.g. 11-01 to 02-28)
            return $point >= $start || $point <= $end;
        };

        return $in_range($b_start, $a_start, $a_end)
            || $in_range($b_end, $a_start, $a_end)
            || $in_range($a_start, $b_start, $b_end)
            || $in_range($a_end, $b_start, $b_end);
    }

    /**
     * Migrate old shaped_discount_ranges to new shaped_discount_seasons format
     *
     * Converts existing date-range configs into the 'overrides' array.
     * Runs once on admin_init, then deletes the old option.
     */
    public static function maybe_migrate_discount_ranges(): void {
        $old = get_option(self::OPT_DISCOUNT_RANGES);
        $new = get_option(self::OPT_DISCOUNT_SEASONS);

        // Only migrate if old exists and new does not
        if (empty($old) || !is_array($old) || $new !== false) {
            return;
        }

        $overrides = [];
        foreach ($old as $range) {
            if (empty($range['start_date']) || empty($range['end_date'])) {
                continue;
            }
            $overrides[] = [
                'start_date' => $range['start_date'],
                'end_date'   => $range['end_date'],
                'label'      => '',
                'discounts'  => isset($range['discounts']) ? $range['discounts'] : [],
            ];
        }

        $seasons = [
            'recurring'  => [],
            'overrides'  => $overrides,
        ];

        update_option(self::OPT_DISCOUNT_SEASONS, $seasons);
        delete_option(self::OPT_DISCOUNT_RANGES);
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

        register_setting('shaped_pricing_group', self::OPT_DISCOUNT_SEASONS, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_discount_seasons'],
            'default'           => ['recurring' => [], 'overrides' => []],
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

        // Load design tokens so CSS custom properties (brand colors, etc.) are available
        if (file_exists(SHAPED_DIR . 'assets/css/design-tokens.css')) {
            wp_enqueue_style(
                'shaped-design-tokens',
                SHAPED_URL . 'assets/css/design-tokens.css',
                [],
                SHAPED_VERSION
            );

            if (class_exists('Shaped_Design_Tokens_Generator')) {
                $tokens_css = Shaped_Design_Tokens_Generator::generate_tokens_css();
                wp_add_inline_style('shaped-design-tokens', $tokens_css);
            }
        }

        wp_enqueue_style(
            'shaped-pricing-admin',
            SHAPED_URL . 'assets/css/admin-pricing.css',
            ['shaped-design-tokens'],
            SHAPED_VERSION
        );

        wp_enqueue_script(
            'shaped-discount-seasons',
            SHAPED_URL . 'assets/js/admin-discount-seasons.js',
            [],
            SHAPED_VERSION,
            true
        );
    }

    /**
     * Render admin page
     */
    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $discounts           = get_option(self::OPT_DISCOUNTS, self::discount_defaults());
        $seasons             = get_option(self::OPT_DISCOUNT_SEASONS, ['recurring' => [], 'overrides' => []]);
        $recurring_seasons   = isset($seasons['recurring']) ? $seasons['recurring'] : [];
        $override_seasons    = isset($seasons['overrides']) ? $seasons['overrides'] : [];
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

                    <!-- Default Discounts -->
                    <h3 class="shaped-subsection-title">Default Discounts (when no seasonal range matches)</h3>

                    <table class="wp-list-table widefat fixed striped shaped-pricing-table">
                        <thead>
                            <tr>
                                <th>Room Type</th>
                                <th>Default Discount (%)</th>
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

                    <!-- ── Recurring Seasonal Discounts ── -->
                    <h3 class="shaped-subsection-title">Recurring Seasonal Discounts</h3>
                    <p class="description">
                        Repeat every year. Use dd/mm format.
                    </p>

                    <div id="shaped-recurring-seasons">
                        <?php if (!empty($recurring_seasons)): ?>
                            <?php foreach ($recurring_seasons as $index => $season): ?>
                                <div class="shaped-date-range-card shaped-season-card--recurring" data-range-index="<?php echo esc_attr($index); ?>">
                                    <div class="shaped-date-range-header">
                                        <div class="shaped-date-range-dates">
                                            <label>
                                                From
                                                <input type="text"
                                                       name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[recurring][<?php echo esc_attr($index); ?>][start_day]"
                                                       value="<?php echo esc_attr(self::mmdd_to_ddmm($season['start_day'])); ?>"
                                                       placeholder="dd/mm"
                                                       pattern="\d{2}/\d{2}"
                                                       maxlength="5"
                                                       class="shaped-date-text shaped-date-ddmm"
                                                       required>
                                            </label>
                                            <label>
                                                To
                                                <input type="text"
                                                       name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[recurring][<?php echo esc_attr($index); ?>][end_day]"
                                                       value="<?php echo esc_attr(self::mmdd_to_ddmm($season['end_day'])); ?>"
                                                       placeholder="dd/mm"
                                                       pattern="\d{2}/\d{2}"
                                                       maxlength="5"
                                                       class="shaped-date-text shaped-date-ddmm"
                                                       required>
                                            </label>
                                            <label>
                                                Label
                                                <input type="text"
                                                       name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[recurring][<?php echo esc_attr($index); ?>][label]"
                                                       value="<?php echo esc_attr($season['label']); ?>"
                                                       placeholder="e.g. Low Season"
                                                       class="shaped-season-label">
                                            </label>
                                        </div>
                                        <button type="button" class="shaped-remove-range" title="Remove this season">&times;</button>
                                    </div>
                                    <table class="wp-list-table widefat fixed striped shaped-pricing-table shaped-range-table">
                                        <thead>
                                            <tr>
                                                <th>Room Type</th>
                                                <th>Discount (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($room_types as $slug => $title):
                                                $season_discount = isset($season['discounts'][$slug]) ? intval($season['discounts'][$slug]) : 0;
                                            ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($title); ?></strong></td>
                                                <td>
                                                    <input type="number"
                                                           name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[recurring][<?php echo esc_attr($index); ?>][discounts][<?php echo esc_attr($slug); ?>]"
                                                           value="<?php echo esc_attr($season_discount); ?>"
                                                           min="0"
                                                           max="100"
                                                           step="1"
                                                           class="small-text" />
                                                    <span class="description">%</span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="shaped-date-range-actions">
                        <button type="button" id="shaped-add-recurring" class="button button-secondary">+ Add Season</button>
                        <span id="shaped-recurring-overlap-warning" class="shaped-overlap-warning" style="display:none;">Recurring seasons must not overlap.</span>
                    </div>

                    <!-- ── Year-Specific Promos ── -->
                    <h3 class="shaped-subsection-title">Year-Specific Promos</h3>
                    <p class="description">
                        Promotional discounts for a specific year. Use dd/mm/yyyy format. Takes priority over seasons above.
                    </p>

                    <div id="shaped-override-seasons">
                        <?php if (!empty($override_seasons)): ?>
                            <?php foreach ($override_seasons as $index => $override): ?>
                                <div class="shaped-date-range-card shaped-season-card--override" data-range-index="<?php echo esc_attr($index); ?>">
                                    <div class="shaped-date-range-header">
                                        <div class="shaped-date-range-dates">
                                            <label>
                                                From
                                                <input type="text"
                                                       name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[overrides][<?php echo esc_attr($index); ?>][start_date]"
                                                       value="<?php echo esc_attr(self::ymd_to_ddmmyyyy($override['start_date'])); ?>"
                                                       placeholder="dd/mm/yyyy"
                                                       pattern="\d{2}/\d{2}/\d{4}"
                                                       maxlength="10"
                                                       class="shaped-date-text shaped-date-ddmmyyyy"
                                                       required>
                                            </label>
                                            <label>
                                                To
                                                <input type="text"
                                                       name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[overrides][<?php echo esc_attr($index); ?>][end_date]"
                                                       value="<?php echo esc_attr(self::ymd_to_ddmmyyyy($override['end_date'])); ?>"
                                                       placeholder="dd/mm/yyyy"
                                                       pattern="\d{2}/\d{2}/\d{4}"
                                                       maxlength="10"
                                                       class="shaped-date-text shaped-date-ddmmyyyy"
                                                       required>
                                            </label>
                                            <label>
                                                Label
                                                <input type="text"
                                                       name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[overrides][<?php echo esc_attr($index); ?>][label]"
                                                       value="<?php echo esc_attr($override['label']); ?>"
                                                       placeholder="e.g. Summer 2026"
                                                       class="shaped-season-label">
                                            </label>
                                        </div>
                                        <button type="button" class="shaped-remove-range" title="Remove this promo">&times;</button>
                                    </div>
                                    <table class="wp-list-table widefat fixed striped shaped-pricing-table shaped-range-table">
                                        <thead>
                                            <tr>
                                                <th>Room Type</th>
                                                <th>Discount (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($room_types as $slug => $title):
                                                $override_discount = isset($override['discounts'][$slug]) ? intval($override['discounts'][$slug]) : 0;
                                            ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($title); ?></strong></td>
                                                <td>
                                                    <input type="number"
                                                           name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[overrides][<?php echo esc_attr($index); ?>][discounts][<?php echo esc_attr($slug); ?>]"
                                                           value="<?php echo esc_attr($override_discount); ?>"
                                                           min="0"
                                                           max="100"
                                                           step="1"
                                                           class="small-text" />
                                                    <span class="description">%</span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="shaped-date-range-actions">
                        <button type="button" id="shaped-add-override" class="button button-secondary">+ Add Promo</button>
                        <span id="shaped-override-overlap-warning" class="shaped-overlap-warning" style="display:none;">Year-specific promos must not overlap.</span>
                    </div>

                    <!-- Hidden template for new recurring season cards -->
                    <template id="shaped-recurring-template">
                        <div class="shaped-date-range-card shaped-season-card--recurring" data-range-index="__INDEX__">
                            <div class="shaped-date-range-header">
                                <div class="shaped-date-range-dates">
                                    <label>
                                        From
                                        <input type="text"
                                               name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[recurring][__INDEX__][start_day]"
                                               value=""
                                               placeholder="dd/mm"
                                               pattern="\d{2}/\d{2}"
                                               maxlength="5"
                                               class="shaped-date-text shaped-date-ddmm"
                                               required>
                                    </label>
                                    <label>
                                        To
                                        <input type="text"
                                               name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[recurring][__INDEX__][end_day]"
                                               value=""
                                               placeholder="dd/mm"
                                               pattern="\d{2}/\d{2}"
                                               maxlength="5"
                                               class="shaped-date-text shaped-date-ddmm"
                                               required>
                                    </label>
                                    <label>
                                        Label
                                        <input type="text"
                                               name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[recurring][__INDEX__][label]"
                                               value=""
                                               placeholder="e.g. Low Season"
                                               class="shaped-season-label">
                                    </label>
                                </div>
                                <button type="button" class="shaped-remove-range" title="Remove this season">&times;</button>
                            </div>
                            <table class="wp-list-table widefat fixed striped shaped-pricing-table shaped-range-table">
                                <thead>
                                    <tr>
                                        <th>Room Type</th>
                                        <th>Discount (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($room_types as $slug => $title): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($title); ?></strong></td>
                                        <td>
                                            <input type="number"
                                                   name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[recurring][__INDEX__][discounts][<?php echo esc_attr($slug); ?>]"
                                                   value="0"
                                                   min="0"
                                                   max="100"
                                                   step="1"
                                                   class="small-text" />
                                            <span class="description">%</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <!-- Hidden template for new promo cards -->
                    <template id="shaped-override-template">
                        <div class="shaped-date-range-card shaped-season-card--override" data-range-index="__INDEX__">
                            <div class="shaped-date-range-header">
                                <div class="shaped-date-range-dates">
                                    <label>
                                        From
                                        <input type="text"
                                               name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[overrides][__INDEX__][start_date]"
                                               value=""
                                               placeholder="dd/mm/yyyy"
                                               pattern="\d{2}/\d{2}/\d{4}"
                                               maxlength="10"
                                               class="shaped-date-text shaped-date-ddmmyyyy"
                                               required>
                                    </label>
                                    <label>
                                        To
                                        <input type="text"
                                               name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[overrides][__INDEX__][end_date]"
                                               value=""
                                               placeholder="dd/mm/yyyy"
                                               pattern="\d{2}/\d{2}/\d{4}"
                                               maxlength="10"
                                               class="shaped-date-text shaped-date-ddmmyyyy"
                                               required>
                                    </label>
                                    <label>
                                        Label
                                        <input type="text"
                                               name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[overrides][__INDEX__][label]"
                                               value=""
                                               placeholder="e.g. Summer 2026"
                                               class="shaped-season-label">
                                    </label>
                                </div>
                                <button type="button" class="shaped-remove-range" title="Remove this promo">&times;</button>
                            </div>
                            <table class="wp-list-table widefat fixed striped shaped-pricing-table shaped-range-table">
                                <thead>
                                    <tr>
                                        <th>Room Type</th>
                                        <th>Discount (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($room_types as $slug => $title): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($title); ?></strong></td>
                                        <td>
                                            <input type="number"
                                                   name="<?php echo esc_attr(self::OPT_DISCOUNT_SEASONS); ?>[overrides][__INDEX__][discounts][<?php echo esc_attr($slug); ?>]"
                                                   value="0"
                                                   min="0"
                                                   max="100"
                                                   step="1"
                                                   class="small-text" />
                                            <span class="description">%</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </template>
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
     * Get discount seasons configuration
     *
     * @return array Array with 'recurring' and 'overrides' keys
     */
    public static function get_discount_seasons(): array {
        $saved = get_option(self::OPT_DISCOUNT_SEASONS, ['recurring' => [], 'overrides' => []]);
        if (!is_array($saved)) {
            return ['recurring' => [], 'overrides' => []];
        }
        return wp_parse_args($saved, ['recurring' => [], 'overrides' => []]);
    }

    /**
     * Get discount date ranges configuration (legacy wrapper)
     *
     * @return array Array of date-range discount configs
     * @deprecated Use get_discount_seasons() instead
     */
    public static function get_discount_ranges(): array {
        $seasons = self::get_discount_seasons();
        return isset($seasons['overrides']) ? $seasons['overrides'] : [];
    }

    /**
     * Get discount for a specific room type using 3-tier priority:
     * 1. Year-specific overrides (yyyy-mm-dd comparison)
     * 2. Recurring seasons (mm-dd comparison, year-agnostic)
     * 3. Default flat discount
     *
     * @param string      $room_slug      Room type slug
     * @param string|null $check_in_date  Check-in date in Y-m-d format (optional)
     * @return int Discount percentage (0-100)
     */
    public static function get_room_discount_for_date(string $room_slug, ?string $check_in_date = null): int {
        $slug = sanitize_title($room_slug);

        // If no date, return default flat discount
        if (empty($check_in_date)) {
            $discounts = self::get_discounts();
            return isset($discounts[$slug]) ? intval($discounts[$slug]) : 0;
        }

        $seasons = self::get_discount_seasons();

        // Priority 1: Year-specific overrides (yyyy-mm-dd comparison)
        if (!empty($seasons['overrides'])) {
            foreach ($seasons['overrides'] as $override) {
                if (empty($override['start_date']) || empty($override['end_date'])) {
                    continue;
                }
                if ($check_in_date >= $override['start_date'] && $check_in_date <= $override['end_date']) {
                    return isset($override['discounts'][$slug]) ? intval($override['discounts'][$slug]) : 0;
                }
            }
        }

        // Priority 2: Recurring seasons (mm-dd comparison, year-agnostic)
        if (!empty($seasons['recurring'])) {
            $check_md = substr($check_in_date, 5); // "mm-dd"
            foreach ($seasons['recurring'] as $season) {
                if (empty($season['start_day']) || empty($season['end_day'])) {
                    continue;
                }
                if ($season['start_day'] <= $season['end_day']) {
                    // Normal range (e.g. Jan-Apr)
                    if ($check_md >= $season['start_day'] && $check_md <= $season['end_day']) {
                        return isset($season['discounts'][$slug]) ? intval($season['discounts'][$slug]) : 0;
                    }
                } else {
                    // Wrapping range (e.g. Nov-Feb, crosses year boundary)
                    if ($check_md >= $season['start_day'] || $check_md <= $season['end_day']) {
                        return isset($season['discounts'][$slug]) ? intval($season['discounts'][$slug]) : 0;
                    }
                }
            }
        }

        // Priority 3: Default flat discount
        $discounts = self::get_discounts();
        return isset($discounts[$slug]) ? intval($discounts[$slug]) : 0;
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
            'discountSeasons'         => self::get_discount_seasons(),
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

            // Use check-in date for date-range-aware discount lookup
            $check_in_date = null;
            if (method_exists($booking, 'getCheckInDate') && $booking->getCheckInDate()) {
                $check_in_date = $booking->getCheckInDate()->format('Y-m-d');
            }

            $discount_percent = self::get_room_discount_for_date($room_slug, $check_in_date);
            $discount_multiplier = (100 - $discount_percent) / 100;
            return round($base_amount * $discount_multiplier, 2);
        }

        // New API: explicit base amount and room slug
        $base_amount = (float) $base_amount_or_booking;
        $slug = sanitize_title($room_slug);
        $discount_percent = self::get_room_discount_for_date($slug);
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
     * @param string      $room_slug      Room type slug
     * @param string|null $check_in_date  Check-in date in Y-m-d format (optional, enables date-range lookup)
     * @return int Discount percentage (0-100)
     */
    public static function get_room_discount(string $room_slug, ?string $check_in_date = null): int {
        return self::get_room_discount_for_date($room_slug, $check_in_date);
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