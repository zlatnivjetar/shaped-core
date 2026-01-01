<?php
/**
 * Booking Rules Admin Settings
 * Configure min/max nights and other booking constraints
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Booking_Rules {

    /**
     * Option keys
     */
    const OPT_MIN_NIGHTS = 'shaped_booking_min_nights';
    const OPT_MAX_NIGHTS = 'shaped_booking_max_nights';

    /**
     * Default values
     */
    const DEFAULT_MIN_NIGHTS = 1;
    const DEFAULT_MAX_NIGHTS = 30;

    /**
     * Initialize booking rules functionality
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu(): void {
        add_submenu_page(
            'shaped-settings',
            'Booking Rules',
            'Booking Rules',
            'manage_options',
            'shaped-booking-rules',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings(): void {
        register_setting('shaped_booking_rules_group', self::OPT_MIN_NIGHTS, [
            'type'              => 'integer',
            'sanitize_callback' => [__CLASS__, 'sanitize_min_nights'],
            'default'           => self::DEFAULT_MIN_NIGHTS,
        ]);

        register_setting('shaped_booking_rules_group', self::OPT_MAX_NIGHTS, [
            'type'              => 'integer',
            'sanitize_callback' => [__CLASS__, 'sanitize_max_nights'],
            'default'           => self::DEFAULT_MAX_NIGHTS,
        ]);
    }

    /**
     * Sanitize min nights input
     */
    public static function sanitize_min_nights($input): int {
        $value = absint($input);
        return max(1, min(30, $value)); // Clamp between 1 and 30
    }

    /**
     * Sanitize max nights input
     */
    public static function sanitize_max_nights($input): int {
        $value = absint($input);
        $min_nights = self::get_min_nights();
        return max($min_nights, min(365, $value)); // Clamp between min_nights and 365
    }

    /**
     * Get minimum nights
     */
    public static function get_min_nights(): int {
        return (int) get_option(self::OPT_MIN_NIGHTS, self::DEFAULT_MIN_NIGHTS);
    }

    /**
     * Get maximum nights
     */
    public static function get_max_nights(): int {
        return (int) get_option(self::OPT_MAX_NIGHTS, self::DEFAULT_MAX_NIGHTS);
    }

    /**
     * Get all booking rules as array (for frontend)
     */
    public static function get_rules(): array {
        return [
            'minNights' => self::get_min_nights(),
            'maxNights' => self::get_max_nights(),
        ];
    }

    /**
     * Render settings page
     */
    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings were updated
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            add_settings_error(
                'shaped_booking_rules_messages',
                'shaped_booking_rules_message',
                'Booking rules saved successfully.',
                'updated'
            );
        }

        $min_nights = self::get_min_nights();
        $max_nights = self::get_max_nights();

        ?>
        <div class="wrap shaped-booking-rules">
            <h1>Booking Rules</h1>
            <p class="description">Configure minimum and maximum stay requirements for bookings.</p>

            <?php settings_errors('shaped_booking_rules_messages'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('shaped_booking_rules_group'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="shaped_booking_min_nights">Minimum Nights</label>
                            </th>
                            <td>
                                <input type="number"
                                       id="shaped_booking_min_nights"
                                       name="<?php echo esc_attr(self::OPT_MIN_NIGHTS); ?>"
                                       value="<?php echo esc_attr($min_nights); ?>"
                                       min="1"
                                       max="30"
                                       step="1"
                                       class="small-text">
                                <p class="description">
                                    Minimum number of nights required for a booking. Default: 1
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="shaped_booking_max_nights">Maximum Nights</label>
                            </th>
                            <td>
                                <input type="number"
                                       id="shaped_booking_max_nights"
                                       name="<?php echo esc_attr(self::OPT_MAX_NIGHTS); ?>"
                                       value="<?php echo esc_attr($max_nights); ?>"
                                       min="1"
                                       max="365"
                                       step="1"
                                       class="small-text">
                                <p class="description">
                                    Maximum number of nights allowed for a booking. Default: 30
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Save Booking Rules'); ?>
            </form>

            <div class="shaped-info-box">
                <h3>How Booking Rules Work</h3>
                <ul>
                    <li><strong>Minimum Nights:</strong> Guests must book at least this many nights. A warning will appear if they select fewer.</li>
                    <li><strong>Maximum Nights:</strong> Guests will see a warning if they try to book more than this many nights.</li>
                </ul>
                <p><em>Note: These rules apply globally to all room types.</em></p>
            </div>
        </div>

        <style>
            .shaped-booking-rules .form-table th {
                width: 200px;
            }
            .shaped-booking-rules .small-text {
                width: 80px;
            }
            .shaped-info-box {
                background: #f0f6fc;
                border-left: 4px solid #0073aa;
                padding: 15px 20px;
                margin-top: 30px;
                max-width: 600px;
            }
            .shaped-info-box h3 {
                margin-top: 0;
                margin-bottom: 10px;
            }
            .shaped-info-box ul {
                margin: 10px 0;
            }
            .shaped-info-box li {
                margin-bottom: 8px;
            }
        </style>
        <?php
    }
}
