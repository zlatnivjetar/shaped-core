<?php
/**
 * Shaped System Dashboard
 * Overview page for system administration (admins only)
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// System status checks
$stripe_configured = class_exists('Shaped_Setup_Wizard')
    && Shaped_Setup_Wizard::get_stripe_secret() !== '';

$roomcloud_enabled = defined('SHAPED_ENABLE_ROOMCLOUD') && SHAPED_ENABLE_ROOMCLOUD;
$reviews_enabled = defined('SHAPED_ENABLE_REVIEWS') && SHAPED_ENABLE_REVIEWS;

// Feature flags - check if defined in wp-config (for backward compatibility warning)
$roomcloud_in_config = defined('SHAPED_ENABLE_ROOMCLOUD') && !get_option('shaped_enable_roomcloud', false);
$reviews_in_config = defined('SHAPED_ENABLE_REVIEWS') && get_option('shaped_enable_reviews', null) === null;

// Get current option values
$roomcloud_option = get_option('shaped_enable_roomcloud', false);
$reviews_option = get_option('shaped_enable_reviews', true);

// WordPress status
$wp_version = get_bloginfo('version');
$php_version = phpversion();
$plugins_need_update = get_plugin_updates();
$themes_need_update = get_theme_updates();
$core_update = get_core_updates();
$has_core_update = !empty($core_update) && isset($core_update[0]->response) && $core_update[0]->response === 'upgrade';

// User counts
$operators = get_users(['role' => 'shaped_operator']);
$admins = get_users(['role' => 'administrator']);
?>

<div class="wrap shaped-system-dashboard">
    <h1>System Administration</h1>
    <p class="description">Manage integrations, tools, and system settings.</p>

    <!-- Feature Toggles -->
    <div class="shaped-feature-toggles-card">
        <h2>Feature Modules</h2>
        <p class="description">Enable or disable optional feature modules for your booking system.</p>

        <div class="feature-toggle-grid">
            <div class="feature-toggle-item">
                <div class="feature-toggle-header">
                    <div class="feature-toggle-info">
                        <strong>Reviews System</strong>
                        <span class="feature-toggle-desc">Guest review management and display</span>
                    </div>
                    <label class="shaped-toggle-switch">
                        <input type="checkbox"
                               id="shaped-toggle-reviews"
                               data-feature="reviews"
                               <?php checked($reviews_option); ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="feature-toggle-item">
                <div class="feature-toggle-header">
                    <div class="feature-toggle-info">
                        <strong>RoomCloud Integration</strong>
                        <span class="feature-toggle-desc">Channel manager synchronization</span>
                    </div>
                    <label class="shaped-toggle-switch">
                        <input type="checkbox"
                               id="shaped-toggle-roomcloud"
                               data-feature="roomcloud"
                               <?php checked($roomcloud_option); ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="feature-toggle-actions">
            <button type="button" id="shaped-save-features" class="button button-primary">
                Save Feature Settings
            </button>
            <span class="shaped-save-status"></span>
        </div>
    </div>

    <!-- System Status -->
    <div class="shaped-status-grid">
        <div class="shaped-status-card">
            <h3>Shaped Core</h3>
            <ul class="status-list">
                <li>
                    <span class="status-indicator <?php echo $stripe_configured ? 'status-ok' : 'status-warning'; ?>"></span>
                    Stripe: <?php echo $stripe_configured ? 'Configured' : 'Not configured'; ?>
                </li>
                <li>
                    <span class="status-indicator status-ok"></span>
                    Reviews: <?php echo $reviews_enabled ? 'Enabled' : 'Disabled'; ?>
                </li>
                <li>
                    <span class="status-indicator <?php echo $roomcloud_enabled ? 'status-ok' : 'status-info'; ?>"></span>
                    RoomCloud: <?php echo $roomcloud_enabled ? 'Enabled' : 'Disabled'; ?>
                </li>
            </ul>
            <p class="card-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-config-health')); ?>">Config Health</a> |
                <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-setup-wizard')); ?>">Setup Wizard</a>
            </p>
        </div>

        <div class="shaped-status-card">
            <h3>Environment</h3>
            <ul class="status-list">
                <li>
                    <span class="status-indicator <?php echo $has_core_update ? 'status-warning' : 'status-ok'; ?>"></span>
                    WordPress: <?php echo esc_html($wp_version); ?>
                    <?php if ($has_core_update): ?>
                        <em>(update available)</em>
                    <?php endif; ?>
                </li>
                <li>
                    <span class="status-indicator status-ok"></span>
                    PHP: <?php echo esc_html($php_version); ?>
                </li>
                <li>
                    <span class="status-indicator <?php echo count($plugins_need_update) > 0 ? 'status-warning' : 'status-ok'; ?>"></span>
                    Plugins: <?php echo count($plugins_need_update); ?> updates available
                </li>
            </ul>
            <p class="card-actions">
                <a href="<?php echo esc_url(admin_url('update-core.php')); ?>">Check Updates</a>
            </p>
        </div>

        <div class="shaped-status-card">
            <h3>Users</h3>
            <ul class="status-list">
                <li>
                    <span class="status-indicator status-info"></span>
                    Administrators: <?php echo count($admins); ?>
                </li>
                <li>
                    <span class="status-indicator status-info"></span>
                    Hotel Operators: <?php echo count($operators); ?>
                </li>
            </ul>
            <p class="card-actions">
                <a href="<?php echo esc_url(admin_url('users.php')); ?>">Manage Users</a> |
                <a href="<?php echo esc_url(admin_url('user-new.php')); ?>">Add New</a>
            </p>
        </div>
    </div>

    <!-- Quick Links Grid -->
    <div class="shaped-section">
        <h2>System Tools</h2>
        <div class="shaped-tools-grid">
            <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-settings')); ?>" class="tool-card">
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Settings</strong>
                <span>Modal pages configuration</span>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-setup-wizard')); ?>" class="tool-card">
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <strong>Setup Wizard</strong>
                <span>Stripe & payment setup</span>
            </a>

            <?php if ($roomcloud_enabled): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-roomcloud')); ?>" class="tool-card">
                <span class="dashicons dashicons-update"></span>
                <strong>RoomCloud</strong>
                <span>Channel manager sync</span>
            </a>
            <?php endif; ?>

            <a href="<?php echo esc_url(admin_url('admin.php?page=rank-math')); ?>" class="tool-card">
                <span class="dashicons dashicons-chart-area"></span>
                <strong>SEO</strong>
                <span>RankMath settings</span>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=complianz')); ?>" class="tool-card">
                <span class="dashicons dashicons-shield"></span>
                <strong>GDPR</strong>
                <span>Complianz privacy</span>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-mail-smtp')); ?>" class="tool-card">
                <span class="dashicons dashicons-email-alt"></span>
                <strong>Email</strong>
                <span>SMTP configuration</span>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=litespeed')); ?>" class="tool-card">
                <span class="dashicons dashicons-performance"></span>
                <strong>Cache</strong>
                <span>LiteSpeed settings</span>
            </a>

            <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="tool-card">
                <span class="dashicons dashicons-admin-plugins"></span>
                <strong>Plugins</strong>
                <span>Manage plugins</span>
            </a>

            <a href="<?php echo esc_url(admin_url('tools.php')); ?>" class="tool-card">
                <span class="dashicons dashicons-admin-tools"></span>
                <strong>Tools</strong>
                <span>WordPress tools</span>
            </a>

            <a href="<?php echo esc_url(admin_url('update-core.php')); ?>" class="tool-card">
                <span class="dashicons dashicons-update"></span>
                <strong>Updates</strong>
                <span>WordPress updates</span>
            </a>
        </div>
    </div>
</div>

<style>
.shaped-system-dashboard {
    max-width: 1200px;
}

/* Feature Toggles Card */
.shaped-feature-toggles-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px 25px;
    margin: 25px 0;
}

.shaped-feature-toggles-card h2 {
    margin: 0 0 10px;
    padding: 0;
    font-size: 16px;
    font-weight: 600;
}

.shaped-feature-toggles-card > .description {
    margin: 0 0 20px;
    color: #646970;
}

.feature-toggle-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.feature-toggle-item {
    background: #f6f7f7;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 15px;
}

.feature-toggle-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.feature-toggle-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.feature-toggle-info strong {
    font-size: 14px;
    color: #1d2327;
}

.feature-toggle-desc {
    font-size: 12px;
    color: #646970;
}

/* Toggle Switch */
.shaped-toggle-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 24px;
    flex-shrink: 0;
    cursor: pointer;
}

.shaped-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ddd;
    transition: 0.3s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.shaped-toggle-switch input:checked + .toggle-slider {
    background-color: #2271b1;
}

.shaped-toggle-switch input:focus + .toggle-slider {
    box-shadow: 0 0 1px #2271b1;
}

.shaped-toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

/* Feature Toggle Actions */
.feature-toggle-actions {
    padding-top: 15px;
    border-top: 1px solid #ddd;
    display: flex;
    align-items: center;
    gap: 15px;
}

.shaped-save-status {
    font-size: 13px;
    color: #646970;
}

.shaped-save-status.success {
    color: #00a32a;
    font-weight: 500;
}

.shaped-save-status.error {
    color: #d63638;
    font-weight: 500;
}

.shaped-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.shaped-status-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.shaped-status-card h3 {
    margin: 0 0 15px;
    padding: 0;
    font-size: 15px;
    font-weight: 600;
    color: #1d2327;
}

.status-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.status-list li {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
}

.status-list li:last-child {
    border-bottom: none;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-indicator.status-ok { background: #00a32a; }
.status-indicator.status-warning { background: #dba617; }
.status-indicator.status-error { background: #d63638; }
.status-indicator.status-info { background: #72aee6; }

.card-actions {
    margin: 15px 0 0;
    padding-top: 10px;
    border-top: 1px solid #f0f0f1;
    font-size: 12px;
}

.card-actions a {
    text-decoration: none;
}

.shaped-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px 25px;
    margin: 25px 0;
}

.shaped-section h2 {
    margin: 0 0 20px;
    padding: 0;
    font-size: 16px;
    font-weight: 600;
}

.shaped-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
}

.tool-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 20px 15px;
    background: #f6f7f7;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #1d2327;
    transition: all 0.2s ease;
}

.tool-card:hover {
    background: #fff;
    border-color: #2271b1;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.tool-card .dashicons {
    font-size: 28px;
    width: 28px;
    height: 28px;
    margin-bottom: 10px;
    color: #50575e;
}

.tool-card:hover .dashicons {
    color: #2271b1;
}

.tool-card strong {
    font-size: 14px;
    margin-bottom: 4px;
}

.tool-card span:last-child {
    font-size: 11px;
    color: #50575e;
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';

    // Handle feature flag save
    $('#shaped-save-features').on('click', function(e) {
        e.preventDefault();

        const $button = $(this);
        const $status = $('.shaped-save-status');
        const reviewsEnabled = $('#shaped-toggle-reviews').is(':checked');
        const roomcloudEnabled = $('#shaped-toggle-roomcloud').is(':checked');

        // Disable button and show loading state
        $button.prop('disabled', true).text('Saving...');
        $status.removeClass('success error').text('');

        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shaped_save_feature_flags',
                nonce: '<?php echo wp_create_nonce('shaped_feature_flags'); ?>',
                reviews: reviewsEnabled ? '1' : '0',
                roomcloud: roomcloudEnabled ? '1' : '0'
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').text(response.data.message);

                    // Show reload prompt after 2 seconds
                    setTimeout(function() {
                        if (confirm('Feature settings have been saved. The page needs to be refreshed for changes to take effect. Refresh now?')) {
                            location.reload();
                        }
                    }, 2000);
                } else {
                    $status.addClass('error').text(response.data.message || 'Failed to save settings');
                }
            },
            error: function(xhr, status, error) {
                $status.addClass('error').text('Error: ' + error);
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false).text('Save Feature Settings');
            }
        });
    });
});
</script>
