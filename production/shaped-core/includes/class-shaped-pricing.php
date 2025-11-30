<?php
/**
 * Pricing & Admin module
 * - Option keys & defaults
 * - Room type discovery
 * - Sanitizers & settings
 * - Admin UI (Pricing page)
 * - Runtime config resolvers
 * - Canonical amount calculator
 * - Admin columns: Payment Status
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Pricing
{
    /* ============================================================
     * [02] Pricing Option Keys (globals kept for BC)
     * ========================================================== */

    const OPT_DISCOUNTS      = 'shaped_discounts';
    const OPT_SEASON_PRICES  = 'shaped_season_prices';

    public function __construct()
    {
        // Ensure legacy constants exist for other modules / themes.
        if (!defined('SHAPED_OPT_DISCOUNTS')) {
            define('SHAPED_OPT_DISCOUNTS', self::OPT_DISCOUNTS);
        }
        if (!defined('SHAPED_OPT_SEASON_PRICES')) {
            define('SHAPED_OPT_SEASON_PRICES', self::OPT_SEASON_PRICES);
        }

        // Settings & Admin UI
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_menu_page']);

        // Runtime constants (back-compat usage in legacy code)
        add_action('plugins_loaded', [$this, 'define_runtime_constants'], 5);

        // Admin: Payment Status column (moved here per spec)
        add_filter('manage_edit-mphb_booking_columns', [$this, 'add_payment_status_column'], 25);
        add_action('manage_mphb_booking_posts_custom_column', [$this, 'render_payment_status_column'], 10, 2);
    }

    /* ============================================================
     * [03] Pricing Defaults
     * ========================================================== */

    public static function discount_defaults(): array
    {
        return [
            'deluxe-studio-apartment'   => 15,
            'superior-studio-apartment' => 15,
            'deluxe-double-room'        => 10,
            'studio-apartment'          => 20,
        ];
    }

    public static function season_price_defaults(): array
    {
        return [
            'studio-apartment' => ['high' => 220, 'mid' => 160, 'low' => 140],
            'deluxe-studio-apartment' => ['high' => 240, 'mid' => 180, 'low' => 160],
            'superior-studio-apartment' => ['high' => 240, 'mid' => 180, 'low' => 160],
            'deluxe-double-room' => ['high' => 180, 'mid' => 120, 'low' => 100],
        ];
    }

    /* ============================================================
     * [04] Room Type Discovery
     * ========================================================== */

    public static function fetch_room_types(): array
    {
        $out   = [];
        $posts = get_posts(['post_type' => 'mphb_room_type', 'posts_per_page' => -1, 'fields' => 'ids']);

        foreach ((array) $posts as $id) {
            $post = get_post($id);
            if ($post) {
                $out[$post->post_name] = $post->post_title;
            }
        }

        // Ensure defaults present even if MPHB hasn’t been populated yet.
        foreach (array_keys(self::discount_defaults()) as $slug) {
            if (!isset($out[$slug])) {
                $out[$slug] = ucwords(str_replace('-', ' ', $slug));
            }
        }

        return $out;
    }

    /* ============================================================
     * [05] Sanitizers
     * ========================================================== */

    public static function sanitize_discounts($input): array
    {
        $out = [];
        foreach (self::fetch_room_types() as $slug => $_label) {
            $val = isset($input[$slug]) ? floatval($input[$slug]) : 0;
            if ($val < 0)   { $val = 0; }
            if ($val > 100) { $val = 100; }
            $out[$slug] = $val;
        }
        return $out;
    }

    public static function sanitize_season_prices($input): array
    {
        $out = [];
        foreach (self::fetch_room_types() as $slug => $_label) {
            $out[$slug] = [
                'high' => isset($input[$slug]['high']) ? max(0, floatval($input[$slug]['high'])) : 0,
                'mid'  => isset($input[$slug]['mid'])  ? max(0, floatval($input[$slug]['mid']))  : 0,
                'low'  => isset($input[$slug]['low'])  ? max(0, floatval($input[$slug]['low']))  : 0,
            ];
        }
        return $out;
    }

    /* ============================================================
     * [06] Admin: Register Options
     * ========================================================== */

    public function register_settings(): void
    {
        register_setting('shaped_pricing', self::OPT_DISCOUNTS, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_discounts'],
            'default'           => self::discount_defaults(),
        ]);

        register_setting('shaped_pricing', self::OPT_SEASON_PRICES, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_season_prices'],
            'default'           => self::season_price_defaults(),
        ]);
    }

    /* ============================================================
     * [07] Admin: Pricing Page UI
     * ========================================================== */

    public function register_menu_page(): void
    {
        add_menu_page(
            __('Preelook Pricing', 'shaped'),
            __('Preelook Pricing', 'shaped'),
            'manage_options',
            'shaped-pricing',
            [$this, 'render_admin_page'],
            'dashicons-tickets-alt',
            58
        );
    }

    public function render_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $room_types = self::fetch_room_types();
        $discounts  = get_option(self::OPT_DISCOUNTS, self::discount_defaults());
        $seasons    = get_option(self::OPT_SEASON_PRICES, self::season_price_defaults());
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Preelook Pricing', 'shaped'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('shaped_pricing'); ?>

                <h2 style="margin-top:24px;"><?php esc_html_e('Discounts (%)', 'shaped'); ?></h2>
                <table class="widefat striped" style="max-width:800px;">
                    <thead><tr><th><?php esc_html_e('Room Type', 'shaped'); ?></th><th style="width:180px;"><?php esc_html_e('Discount %', 'shaped'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($room_types as $slug => $label): ?>
                        <tr>
                            <td><?php echo esc_html($label); ?><br><code><?php echo esc_html($slug); ?></code></td>
                            <td>
                                <input type="number" min="0" max="100" step="1"
                                       name="<?php echo esc_attr(self::OPT_DISCOUNTS . '['.$slug.']'); ?>"
                                       value="<?php echo esc_attr(isset($discounts[$slug]) ? $discounts[$slug] : 0); ?>"
                                       style="width:100%;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="margin-top:32px;"><?php esc_html_e('Season Prices (EUR / night)', 'shaped'); ?></h2>
                <table class="widefat striped" style="max-width:800px;">
                    <thead>
                    <tr><th><?php esc_html_e('Room Type', 'shaped'); ?></th><th><?php esc_html_e('High', 'shaped'); ?></th><th><?php esc_html_e('Mid', 'shaped'); ?></th><th><?php esc_html_e('Low', 'shaped'); ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($room_types as $slug => $label):
                        $row = isset($seasons[$slug]) && is_array($seasons[$slug]) ? $seasons[$slug] : ['high'=>0,'mid'=>0,'low'=>0];
                    ?>
                        <tr>
                            <td><?php echo esc_html($label); ?><br><code><?php echo esc_html($slug); ?></code></td>
                            <td><input type="number" step="1" min="0" name="<?php echo esc_attr(self::OPT_SEASON_PRICES . '['.$slug.'][high]'); ?>" value="<?php echo esc_attr($row['high']); ?>" style="width:100%;"></td>
                            <td><input type="number" step="1" min="0" name="<?php echo esc_attr(self::OPT_SEASON_PRICES . '['.$slug.'][mid]'); ?>"  value="<?php echo esc_attr($row['mid']); ?>"  style="width:100%;"></td>
                            <td><input type="number" step="1" min="0" name="<?php echo esc_attr(self::OPT_SEASON_PRICES . '['.$slug.'][low]'); ?>"  value="<?php echo esc_attr($row['low']); ?>"  style="width:100%;"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button(__('Save Pricing', 'shaped')); ?>
            </form>
        </div>
        <?php
    }

    /* ============================================================
     * [08] Pricing Resolvers (Runtime CFG)
     * ========================================================== */

    public static function get_discounts_config(): array
    {
        $saved = get_option(self::OPT_DISCOUNTS, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], self::discount_defaults());
        foreach ($cfg as $k => $v) {
            $cfg[$k] = (float) $v;
        }
        return $cfg;
    }

    public static function get_season_prices(): array
    {
        $saved = get_option(self::OPT_SEASON_PRICES, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], self::season_price_defaults());
        foreach ($cfg as $k => $row) {
            $cfg[$k] = [
                'high' => isset($row['high']) ? (float)$row['high'] : 0,
                'mid'  => isset($row['mid'])  ? (float)$row['mid']  : 0,
                'low'  => isset($row['low'])  ? (float)$row['low']  : 0,
            ];
        }
        return $cfg;
    }

    /**
     * Back-compat constants used elsewhere (defined once per request)
     */
    public function define_runtime_constants(): void
    {
        if (!defined('SHAPED_DISCOUNT_CONFIG')) {
            define('SHAPED_DISCOUNT_CONFIG', self::get_discounts_config());
        }
        if (!defined('SHAPED_SEASON_PRICES')) {
            define('SHAPED_SEASON_PRICES', self::get_season_prices());
        }
    }

    /* ============================================================
     * [10] Amount Calculator (Canonical)
     * ========================================================== */

    public static function calculate_final_amount($booking)
    {
        if (!$booking || !is_object($booking)) {
            return 0.0;
        }

        $total          = (float) $booking->getTotalPrice();
        $services_total = 0.0;

        // Extract services from price breakdown
        $price_breakdown = $booking->getPriceBreakdown();
        if (isset($price_breakdown['rooms']) && is_array($price_breakdown['rooms'])) {
            foreach ($price_breakdown['rooms'] as $room_data) {
                if (isset($room_data['services']['total'])) {
                    $services_total += (float) $room_data['services']['total'];
                }
            }
        }

        // Accommodation-only subtotal
        $accommodation_total = $total - $services_total;

        // Apply room-type discount to accommodation only (first room type)
        $discounted_total = $total;

        $reserved_rooms = $booking->getReservedRooms();
        if (!empty($reserved_rooms)) {
            $room = reset($reserved_rooms);
            // Get room type and a stable slug
            $room_type = MPHB()->getRoomTypeRepository()->findById($room->getRoomTypeId());
            if ($room_type) {
                $room_slug = sanitize_title($room_type->getTitle());
                $cfg = self::get_discounts_config();
                if (isset($cfg[$room_slug])) {
                    $discount_percent = (float) $cfg[$room_slug];
                    $discount_amount  = round($accommodation_total * ($discount_percent / 100));
                    $discounted_total = $total - $discount_amount;
                }
            }
        }

        return round($discounted_total, 2);
    }

    /* ============================================================
     * [25] Admin List Columns (Payment Status display)
     * ========================================================== */

    public function add_payment_status_column(array $cols): array
    {
        $out = [];
        foreach ($cols as $key => $label) {
            $out[$key] = $label;
            if ($key === 'shaped_paid') {
                $out['shaped_status'] = __('Payment Status', 'shaped');
            }
        }
        if (!isset($out['shaped_status'])) {
            $out['shaped_status'] = __('Payment Status', 'shaped');
        }
        return $out;
    }

    public function render_payment_status_column(string $column, int $post_id): void
    {
        if ($column !== 'shaped_status') {
            return;
        }

        // If booking post status is cancelled, show it regardless of meta.
        $booking_status = get_post_status($post_id);
        if ($booking_status === 'cancelled') {
            echo '<span style="color:#c00;font-weight:700;font-size:.9375rem;">✗ '
               . esc_html__('Cancelled', 'shaped')
               . '</span>';
            return;
        }

        $payment_status = get_post_meta($post_id, '_shaped_payment_status', true);

        switch ($payment_status) {
            case 'completed':
                echo '<span style="color:#d1af5d;font-weight:700;font-size:.9375rem;">✓ '
                   . esc_html__('Paid', 'shaped')
                   . '</span>';
                break;

            case 'authorized':
                if ($booking_status === 'cancelled') {
                    echo '<span style="color:#c00;font-weight:700;font-size:.9375rem;">✗ '
                       . esc_html__('Cancelled', 'shaped')
                       . '</span>';
                } else {
                    echo '<span style="color:#2e7d32;font-weight:700;font-size:.9375rem;">✓ '
                       . esc_html__('Authorized', 'shaped')
                       . '</span>';
                }
                break;

            case 'pending':
                $checkout_time = get_post_meta($post_id, '_shaped_checkout_started', true);
                $time_ago = $checkout_time ? human_time_diff(strtotime($checkout_time), current_time('timestamp')) : '';

                echo '<span style="color:#ffde00;font-weight:700;font-size:.9375rem;">⏱ '
                   . esc_html__('Pending', 'shaped')
                   . '</span>';

                if ($time_ago) {
                    echo '<br><span style="color:#999;font-size:12px;">(' . esc_html($time_ago) . ' ' . esc_html__('ago', 'shaped') . ')</span>';
                }
                break;

            case 'abandoned':
                echo '<span style="color:#c00;font-weight:700;font-size:.9375rem;">✗ '
                   . esc_html__('Abandoned', 'shaped')
                   . '</span>';
                break;

            default:
                echo '<span style="color:#999;font-weight:400;font-size:14px;">—</span>';
        }
    }
}