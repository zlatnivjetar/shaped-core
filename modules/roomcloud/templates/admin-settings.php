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
        add_action('wp_ajax_shaped_rc_fetch_rates', [$this, 'ajax_fetch_rates']);
        add_action('wp_ajax_shaped_rc_manual_sync', [$this, 'ajax_manual_sync']);
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
        // Credentials
        register_setting('shaped_rc_settings', 'shaped_rc_service_url');
        register_setting('shaped_rc_settings', 'shaped_rc_username');
        register_setting('shaped_rc_settings', 'shaped_rc_password');
        register_setting('shaped_rc_settings', 'shaped_rc_hotel_id');
        
        // Rate ID
        register_setting('shaped_rc_settings', 'shaped_rc_rate_id');
        
        // Room mapping (auto-populated, but editable)
        register_setting('shaped_rc_settings', 'shaped_rc_room_mapping', [
            'type' => 'array',
            'default' => [
                'deluxe-studio-apartment' => '42683',
                'studio-apartment' => '42685',
                'superior-studio-apartment' => '42686',
                'deluxe-double-room' => '42684',
            ],
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
        
        // Get current settings
        $service_url = get_option('shaped_rc_service_url', '');
        $username = get_option('shaped_rc_username', '9335');
        $password = get_option('shaped_rc_password', '');
        $hotel_id = get_option('shaped_rc_hotel_id', '9335');
        $rate_id = get_option('shaped_rc_rate_id', '');
        $room_mapping = get_option('shaped_rc_room_mapping', [
            'deluxe-studio-apartment' => '42683',
            'studio-apartment' => '42685',
            'superior-studio-apartment' => '42686',
            'deluxe-double-room' => '42684',
        ]);
        
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
                    <button type="button" class="button" id="test-connection">Test Connection</button>
                    <div id="test-result" style="margin-top: 10px;"></div>
                <?php else: ?>
                    <p style="color: #dc3232; font-weight: 600;">✗ Not Configured</p>
                    <p>Please enter your RoomCloud credentials below.</p>
                <?php endif; ?>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('shaped_rc_settings'); ?>
                
                <!-- API Credentials -->
                <div class="card" style="max-width: 100%; margin-top: 20px;">
                    <h2>API Credentials</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="shaped_rc_service_url">Service URL</label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="shaped_rc_service_url" 
                                       name="shaped_rc_service_url" 
                                       value="<?php echo esc_attr($service_url); ?>" 
                                       class="regular-text"
                                       placeholder="https://xml.roomcloud.net/api/channel">
                                <p class="description">
                                    Production API endpoint URL from RoomCloud.<br>
                                    Test URL: <code>https://apitest.roomcloud.net/be/ota/testOtaApi.jsp</code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="shaped_rc_username">Username</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="shaped_rc_username" 
                                       name="shaped_rc_username" 
                                       value="<?php echo esc_attr($username); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="shaped_rc_password">Password</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="shaped_rc_password" 
                                       name="shaped_rc_password" 
                                       value="<?php echo esc_attr($password); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
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
                                <p class="description">Your property ID in RoomCloud (default: 9335)</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Rate Plan Configuration -->
                <div class="card" style="max-width: 100%; margin-top: 20px;">
                    <h2>Rate Plan</h2>
                    <table class="form-table">
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
                                       placeholder="e.g., 12345">
                                <button type="button" class="button" id="fetch-rates" style="margin-left: 10px;">Fetch from RoomCloud</button>
                                <p class="description">The RoomCloud rate plan ID to use for all bookings.</p>
                                <div id="rates-result" style="margin-top: 10px;"></div>
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
            
            <!-- Webhook Info -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Webhook Configuration</h2>
                <p>Provide this URL to RoomCloud support for receiving OTA bookings:</p>
                <code style="background: #f5f5f5; padding: 10px; display: block; margin: 10px 0;">
                    <?php echo esc_url(rest_url('preelook/v1/roomcloud-webhook')); ?>
                </code>
                <p class="description">
                    RoomCloud will POST reservation data to this endpoint.<br>
                    Security: Request validation via required fields (simple mode for testing).
                </p>
            </div>
            
            <!-- Manual Sync Tools -->
            <?php if ($is_configured): ?>
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Manual Sync</h2>
                <p>Manually sync a specific booking to RoomCloud.</p>
                <input type="number" 
                       id="manual-sync-booking-id" 
                       placeholder="Booking ID" 
                       class="regular-text">
                <button type="button" class="button button-primary" id="manual-sync">Sync Booking</button>
                <div id="manual-sync-result" style="margin-top: 10px;"></div>
            </div>
            <?php endif; ?>
            
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
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Test Connection
            $('#test-connection').on('click', function() {
                var $btn = $(this);
                var $result = $('#test-result');
                
                $btn.prop('disabled', true).text('Testing...');
                $result.html('<p>Connecting to RoomCloud...</p>');
                
                $.post(ajaxurl, {
                    action: 'shaped_rc_test_connection'
                }, function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>Error: ' + response.data.error + '</p></div>');
                    }
                }).fail(function() {
                    $result.html('<div class="notice notice-error inline"><p>Request failed</p></div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('Test Connection');
                });
            });
            
            // Fetch Rates
            $('#fetch-rates').on('click', function() {
                var $btn = $(this);
                var $result = $('#rates-result');
                
                $btn.prop('disabled', true).text('Fetching...');
                $result.html('<p>Fetching rates from RoomCloud...</p>');
                
                $.post(ajaxurl, {
                    action: 'shaped_rc_fetch_rates'
                }, function(response) {
                    if (response.success && response.data.rates) {
                        var html = '<div class="notice notice-success inline"><p>Available rates:</p><ul>';
                        response.data.rates.forEach(function(rate) {
                            html += '<li><strong>' + rate.id + '</strong> - ' + rate.description + '</li>';
                        });
                        html += '</ul></div>';
                        $result.html(html);
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>Error: ' + (response.data.error || 'Unknown error') + '</p></div>');
                    }
                }).fail(function() {
                    $result.html('<div class="notice notice-error inline"><p>Request failed</p></div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('Fetch from RoomCloud');
                });
            });
            
            // Manual Sync
            $('#manual-sync').on('click', function() {
                var $btn = $(this);
                var $result = $('#manual-sync-result');
                var bookingId = $('#manual-sync-booking-id').val();
                
                if (!bookingId) {
                    alert('Please enter a booking ID');
                    return;
                }
                
                $btn.prop('disabled', true).text('Syncing...');
                $result.html('<p>Syncing booking #' + bookingId + '...</p>');
                
                $.post(ajaxurl, {
                    action: 'shaped_rc_manual_sync',
                    booking_id: bookingId
                }, function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>Error: ' + response.data.error + '</p></div>');
                    }
                }).fail(function() {
                    $result.html('<div class="notice notice-error inline"><p>Request failed</p></div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('Sync Booking');
                });
            });
        });
        </script>
        <?php
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
     * AJAX: Test connection
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
     * AJAX: Fetch rates
     */
    public function ajax_fetch_rates()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized']);
        }
        
        $result = Shaped_RC_API::get_rates();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Manual sync
     */
    public function ajax_manual_sync()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized']);
        }
        
        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        
        if (!$booking_id) {
            wp_send_json_error(['error' => 'Invalid booking ID']);
        }
        
        $result = Shaped_RC_Sync_Manager::manual_sync_booking($booking_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}