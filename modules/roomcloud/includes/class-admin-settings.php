<?php
/**
 * Admin Settings Page
 * Configuration UI for RoomCloud connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_Admin_Settings
{
    private static $instance = null;
    
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Handle AJAX actions
        add_action('wp_ajax_shaped_rc_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_shaped_rc_test_webhook', [$this, 'ajax_test_webhook']);
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu()
    {
        add_menu_page(
            'RoomCloud Sync',
            'RoomCloud',
            'manage_options',
            'shaped-roomcloud',
            [$this, 'render_settings_page'],
            'dashicons-update',
            59
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings()
    {
        // Configuration IDs (credentials and channel_id are now in wp-config.php)
        register_setting('shaped_rc_settings', 'shaped_rc_hotel_id');
        register_setting('shaped_rc_settings', 'shaped_rc_rate_id');
        register_setting('shaped_rc_settings', 'shaped_rc_availability_mode', [
            'type' => 'string',
            'default' => 'motopress',
            'sanitize_callback' => function ($value) {
                return $value === 'roomcloud_strict' ? 'roomcloud_strict' : 'motopress';
            },
        ]);

        // Room mapping (auto-populated, but editable)
        register_setting('shaped_rc_settings', 'shaped_rc_room_mapping', [
            'type' => 'array',
            'default' => [],
        ]);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get current settings (credentials and channel_id are in wp-config.php)
        $hotel_id = get_option('shaped_rc_hotel_id', '');
        $rate_id = get_option('shaped_rc_rate_id', '');
        $availability_mode = shaped_get_roomcloud_availability_mode();
        $room_mapping = Shaped_RC_Availability_Manager::get_room_mapping();
        $validation = Shaped_RC_Validation_Service::validate();

        // Check if credentials are configured in wp-config.php
        $credentials_configured = defined('SHAPED_RC_SERVICE_URL') &&
                                  defined('SHAPED_RC_USERNAME') &&
                                  defined('SHAPED_RC_PASSWORD');

        // Get MotoPress rooms
        $mphb_rooms = Shaped_Pricing::fetch_room_types();

        // Check configuration status
        $is_configured = Shaped_RC_API::is_configured();
        
        ?>
        <div class="wrap">
            <h1>RoomCloud Connector Settings</h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully!</p>
                </div>
            <?php endif; ?>
            
            <!-- Connection Status -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Connection Status</h2>
                <?php if ($is_configured): ?>
                    <p style="color: #46b450; font-weight: 600;">✓ Configured</p>

                    <table class="form-table" style="margin-top: 15px;">
                        <tr>
                            <th style="width: 200px;">Outbound (You → RoomCloud)</th>
                            <td>
                                <button type="button" class="button" id="test-connection">Test Outbound</button>
                                <span class="description" style="margin-left: 10px;">Tests if you can reach RoomCloud's endpoint</span>
                                <div id="test-connection-result" style="margin-top: 8px;"></div>
                            </td>
                        </tr>
                        <tr>
                            <th>Inbound (RoomCloud → You)</th>
                            <td>
                                <button type="button" class="button button-primary" id="test-webhook">Test Webhook</button>
                                <span class="description" style="margin-left: 10px;">Tests if your webhook responds correctly (critical for sync)</span>
                                <div id="test-webhook-result" style="margin-top: 8px;"></div>
                            </td>
                        </tr>
                    </table>
                <?php else: ?>
                    <p style="color: #dc3232; font-weight: 600;">✗ Not Configured</p>
                    <?php if (!$credentials_configured): ?>
                        <p>Please configure RoomCloud credentials in wp-config.php:</p>
                        <pre style="background: #f5f5f5; padding: 10px; margin-top: 10px;">define('SHAPED_RC_SERVICE_URL', 'https://xml.roomcloud.net/api/channel');
define('SHAPED_RC_USERNAME', 'your-username');
define('SHAPED_RC_PASSWORD', 'your-password');</pre>
                    <?php else: ?>
                        <p>Credentials configured. Please complete the configuration below.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('shaped_rc_settings'); ?>
                
                <!-- Settings -->
                <div class="card" style="max-width: 100%; margin-top: 20px;">
                    <h2>Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="shaped_rc_hotel_id">Hotel ID</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="shaped_rc_hotel_id"
                                       name="shaped_rc_hotel_id"
                                       value="<?php echo esc_attr($hotel_id); ?>"
                                       class="regular-text">
                                <p class="description">Your property ID in RoomCloud (e.g., 9335)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="shaped_rc_rate_id">Rate ID</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="shaped_rc_rate_id"
                                       name="shaped_rc_rate_id"
                                       value="<?php echo esc_attr($rate_id); ?>"
                                       class="regular-text"
                                       placeholder="e.g., 26939">
                                <p class="description">The RoomCloud rate plan ID to use for all bookings.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="shaped_rc_availability_mode">Availability Authority</label>
                            </th>
                            <td>
                                <select id="shaped_rc_availability_mode"
                                        name="shaped_rc_availability_mode">
                                    <option value="motopress" <?php selected($availability_mode, 'motopress'); ?>>MotoPress</option>
                                    <option value="roomcloud_strict" <?php selected($availability_mode, 'roomcloud_strict'); ?>>RoomCloud Strict</option>
                                </select>
                                <p class="description">
                                    <strong>MotoPress</strong> keeps availability decisions in MotoPress.
                                    <strong>RoomCloud Strict</strong> blocks mapped rooms unless RoomCloud has complete stay coverage and enough units.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Room Mapping -->
                <div class="card" style="max-width: 100%; margin-top: 20px;">
                    <h2>Room Type Mapping</h2>
                    <p>Map your MotoPress room types to RoomCloud room IDs.</p>
                    <table class="widefat striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th>MotoPress Room Type</th>
                                <th>RoomCloud Room ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mphb_rooms as $slug => $label): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($label); ?></strong><br>
                                        <code><?php echo esc_html($slug); ?></code>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="shaped_rc_room_mapping[<?php echo esc_attr($slug); ?>]" 
                                               value="<?php echo esc_attr(isset($room_mapping[$slug]) ? $room_mapping[$slug] : ''); ?>" 
                                               class="regular-text"
                                               placeholder="e.g., 42683">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php submit_button('Save Settings'); ?>
            </form>

            <!-- Validation -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Pre-Launch Validation</h2>
                <?php $this->render_validation_results($validation); ?>
            </div>
            
            <!-- Current Inventory -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Current Inventory</h2>
                <?php $this->render_inventory_display(); ?>
            </div>
            
            <!-- Webhook Info -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Webhook Configuration</h2>
                <p>Provide this URL to RoomCloud support for receiving OTA bookings:</p>
                <code style="background: #f5f5f5; padding: 10px; display: block; margin: 10px 0;">
                    <?php echo esc_url(rest_url('shaped/v1/roomcloud-webhook')); ?>
                </code>
            </div>
            
            <!-- Recent Logs -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Recent Sync Activity</h2>
                <?php $this->render_recent_logs(); ?>
            </div>
        </div>
        
        <style>
            .card { padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
            .notice-success { color: #46b450; }
            .notice-error { color: #dc3232; }
            .inventory-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 15px; }
            .inventory-room { border: 1px solid #ddd; padding: 10px; border-radius: 4px; }
            .inventory-room h4 { margin: 0 0 10px 0; font-size: 14px; }
            .inventory-date { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
            .inventory-date:last-child { border-bottom: none; }
            .inventory-quantity { font-weight: 600; }
            .inventory-quantity.zero { color: #dc3232; }
            .inventory-quantity.low { color: #f0b849; }
            .inventory-quantity.good { color: #46b450; }
            .rc-check-pass { color: #0a7f38; }
            .rc-check-warn { color: #b26a00; }
            .rc-check-fail { color: #b32d2e; }
            .rc-validation-list li { margin-bottom: 8px; }
            .rc-validation-coverage td { vertical-align: top; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Test Outbound Connection
            $('#test-connection').on('click', function() {
                var $btn = $(this);
                var $result = $('#test-connection-result');

                $btn.prop('disabled', true).text('Testing...');
                $result.html('<p style="color: #666;">Connecting to RoomCloud endpoint...</p>');

                $.post(ajaxurl, {
                    action: 'shaped_rc_test_connection'
                }, function(response) {
                    if (response.success) {
                        var msg = response.data.message;
                        if (response.data.note) {
                            msg += '<br><small style="color: #666;">' + response.data.note + '</small>';
                        }
                        $result.html('<div class="notice notice-success inline" style="margin: 0; padding: 8px 12px;"><p style="margin: 0;">' + msg + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline" style="margin: 0; padding: 8px 12px;"><p style="margin: 0;">Error: ' + response.data.error + '</p></div>');
                    }
                }).fail(function() {
                    $result.html('<div class="notice notice-error inline" style="margin: 0; padding: 8px 12px;"><p style="margin: 0;">Request failed</p></div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('Test Outbound');
                });
            });

            // Test Webhook (Inbound)
            $('#test-webhook').on('click', function() {
                var $btn = $(this);
                var $result = $('#test-webhook-result');

                $btn.prop('disabled', true).text('Testing...');
                $result.html('<p style="color: #666;">Sending test request to webhook...</p>');

                $.post(ajaxurl, {
                    action: 'shaped_rc_test_webhook'
                }, function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline" style="margin: 0; padding: 8px 12px;"><p style="margin: 0;">✓ ' + response.data.message + '</p></div>');
                    } else {
                        var errorMsg = 'Error: ' + response.data.error;
                        if (response.data.body_preview) {
                            errorMsg += '<br><small>Response preview: <code>' + $('<div/>').text(response.data.body_preview).html() + '</code></small>';
                        }
                        if (response.data.problematic_prefix) {
                            errorMsg += '<br><small style="color: #dc3232;">Content before XML: <code>' + $('<div/>').text(response.data.problematic_prefix).html() + '</code></small>';
                        }
                        $result.html('<div class="notice notice-error inline" style="margin: 0; padding: 8px 12px;"><p style="margin: 0;">' + errorMsg + '</p></div>');
                    }
                }).fail(function() {
                    $result.html('<div class="notice notice-error inline" style="margin: 0; padding: 8px 12px;"><p style="margin: 0;">Request failed - check server logs</p></div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('Test Webhook');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render validation output.
     */
    private function render_validation_results(array $validation)
    {
        $status = $validation['status'] ?? 'warn';
        $status_class = 'rc-check-' . $status;
        $status_label = strtoupper($status);
        $horizon_days = isset($validation['horizon_days']) ? (int) $validation['horizon_days'] : 0;
        $mode = $validation['mode'] ?? 'motopress';
        $last_inventory_update = $validation['last_inventory_update'] ?? null;

        echo '<p><strong>Status:</strong> <span class="' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span></p>';
        echo '<p><strong>Availability mode:</strong> ' . esc_html($mode) . '</p>';
        echo '<p><strong>Validation horizon:</strong> ' . esc_html($horizon_days) . ' day(s)</p>';
        echo '<p><strong>Last inventory update:</strong> ' . esc_html($last_inventory_update ?: 'Not recorded yet') . '</p>';

        if (!empty($validation['checks'])) {
            echo '<ul class="rc-validation-list">';
            foreach ($validation['checks'] as $check) {
                echo '<li>';
                echo '<strong class="' . esc_attr('rc-check-' . $check['status']) . '">' . esc_html(strtoupper($check['status'])) . '</strong> ';
                echo esc_html($check['label']) . ': ' . esc_html($check['message']);
                echo '</li>';
            }
            echo '</ul>';
        }

        if (!empty($validation['room_coverage'])) {
            echo '<h3>Room Coverage</h3>';
            echo '<table class="widefat striped rc-validation-coverage">';
            echo '<thead><tr><th>Room Type</th><th>RoomCloud ID</th><th>Status</th><th>Details</th></tr></thead><tbody>';

            foreach ($validation['room_coverage'] as $coverage) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($coverage['label']) . '</strong><br><code>' . esc_html($coverage['slug']) . '</code></td>';
                echo '<td>' . esc_html($coverage['roomcloud_id'] ?: 'Not mapped') . '</td>';
                echo '<td><span class="' . esc_attr('rc-check-' . $coverage['status']) . '">' . esc_html(strtoupper($coverage['status'])) . '</span></td>';
                echo '<td>' . esc_html($coverage['message']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '<p class="description">WP-CLI equivalent: <code>wp roomcloud validate</code></p>';
    }

    /**
     * Render inventory display
     */
    private function render_inventory_display()
    {
        $summary = Shaped_RC_Availability_Manager::get_inventory_summary(42); // Next 42 days
        
        if (empty($summary)) {
            echo '<p>No inventory data received yet. RoomCloud will send availability via modify requests.</p>';
            return;
        }
        
        echo '<div class="inventory-grid">';
        
        foreach ($summary as $slug => $data) {
            $room_post = class_exists('Shaped_RC_Availability_Manager')
                ? Shaped_RC_Availability_Manager::find_room_type_post($slug)
                : get_page_by_path($slug, OBJECT, 'mphb_room_type');
            $room_name = $room_post ? $room_post->post_title : ucwords(str_replace('-', ' ', $slug));
            
            echo '<div class="inventory-room">';
            echo '<h4>' . esc_html($room_name) . '</h4>';
            echo '<small>RC ID: ' . esc_html($data['roomcloud_id']) . '</small>';
            
            $has_data = false;
            foreach ($data['dates'] as $date => $quantity) {
                if ($quantity !== null) {
                    $has_data = true;
                    
                    $class = 'good';
                    if ($quantity === 0) {
                        $class = 'zero';
                    } elseif ($quantity === 1) {
                        $class = 'low';
                    }
                    
                    echo '<div class="inventory-date">';
                    echo '<span>' . date('M j', strtotime($date)) . '</span>';
                    echo '<span class="inventory-quantity ' . $class . '">' . $quantity . ' avail</span>';
                    echo '</div>';
                }
            }
            
            if (!$has_data) {
                echo '<p style="color: #999; font-size: 12px; margin: 10px 0 0 0;">No data</p>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render recent logs
     */
    private function render_recent_logs()
    {
        $log_file = SHAPED_RC_LOGS_DIR . 'sync-errors.log';
        
        if (!file_exists($log_file)) {
            echo '<p>No sync activity recorded yet.</p>';
            return;
        }
        
        // Read last 20 lines
        $lines = array_slice(file($log_file), -20);
        $lines = array_reverse($lines);
        
        if (empty($lines)) {
            echo '<p>No recent activity.</p>';
            return;
        }
        
        echo '<pre style="background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto; font-size: 12px;">';
        foreach ($lines as $line) {
            echo esc_html($line);
        }
        echo '</pre>';
        
        echo '<p><a href="' . esc_url(SHAPED_RC_URL . 'logs/sync-errors.log') . '" target="_blank" class="button">View Full Log</a></p>';
    }
    
    /**
     * AJAX: Test connection (outbound)
     */
    public function ajax_test_connection()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized']);
        }

        $result = Shaped_RC_API::test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Test webhook (inbound)
     */
    public function ajax_test_webhook()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized']);
        }

        $result = Shaped_RC_API::test_webhook();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
