<?php
/**
 * Configuration Health Page
 * Overview of Shaped Core configuration status
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

/**
 * Get all health check results
 */
function shaped_get_all_health_checks(): array {
    $checks = [];

    // MotoPress Hotel Booking
    $checks[] = [
        'label'        => 'MotoPress Hotel Booking',
        'status'       => function_exists('MPHB'),
        'details'      => function_exists('MPHB') ? 'Active and running' : 'Plugin not active',
        'action_url'   => admin_url('plugins.php'),
        'action_label' => 'Manage Plugins',
    ];

    // Stripe checks (from Shaped_Stripe_Config)
    if (class_exists('Shaped_Stripe_Config')) {
        $stripe_checks = Shaped_Stripe_Config::get_health_checks();
        $checks = array_merge($checks, $stripe_checks);
    }

    // Payment Mode
    $payment_mode = get_option(Shaped_Pricing::OPT_PAYMENT_MODE, '');
    $checks[] = [
        'label'        => 'Payment Mode',
        'status'       => !empty($payment_mode),
        'details'      => !empty($payment_mode)
            ? ucfirst($payment_mode) . ' mode'
            : 'Not configured',
        'action_url'   => admin_url('admin.php?page=shaped-pricing'),
        'action_label' => 'Configure',
    ];

    // Room Types
    $room_types = Shaped_Pricing::fetch_room_types();
    $checks[] = [
        'label'        => 'Room Types',
        'status'       => !empty($room_types),
        'details'      => !empty($room_types)
            ? count($room_types) . ' room types found'
            : 'No room types in MotoPress',
        'action_url'   => admin_url('edit.php?post_type=mphb_room_type'),
        'action_label' => 'Manage Rooms',
    ];

    // Modal Pages
    $modal_pages = get_option(Shaped_Admin::OPT_MODAL_PAGES, []);
    $configured_modals = array_filter($modal_pages);
    $checks[] = [
        'label'        => 'Modal Pages',
        'status'       => !empty($configured_modals),
        'details'      => !empty($configured_modals)
            ? count($configured_modals) . ' modal pages configured'
            : 'No modal pages assigned',
        'action_url'   => admin_url('admin.php?page=shaped-system-settings'),
        'action_label' => 'Configure',
    ];

    // Dashboard API key
    $dashboard_api_key_configured = class_exists('Shaped_Dashboard_Api')
        && Shaped_Dashboard_Api::has_configured_api_key();
    $checks[] = [
        'label'        => 'Dashboard API Key',
        'status'       => $dashboard_api_key_configured,
        'details'      => $dashboard_api_key_configured
            ? 'Configured in wp-config.php'
            : 'Not configured in wp-config.php',
        'action_url'   => admin_url('admin.php?page=shaped-system-settings'),
        'action_label' => 'Open Settings',
    ];

    return $checks;
}

$checks = shaped_get_all_health_checks();
$all_pass = !in_array(false, array_column($checks, 'status'));
?>

<div class="wrap shaped-admin-wrap">
    <h1>Configuration Health</h1>
    <p class="description">Overview of your Shaped Core configuration status.</p>

    <div class="shaped-health-summary <?php echo $all_pass ? 'is-healthy' : 'has-issues'; ?>">
        <span class="dashicons <?php echo $all_pass ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
        <span class="summary-text">
            <?php echo $all_pass ? 'All systems configured correctly!' : 'Some configuration items need attention.'; ?>
        </span>
    </div>

    <table class="wp-list-table widefat fixed striped shaped-health-table">
        <thead>
            <tr>
                <th style="width: 50px;">Status</th>
                <th>Configuration Item</th>
                <th>Details</th>
                <th style="width: 150px;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($checks as $check): ?>
            <tr class="<?php echo $check['status'] ? 'status-ok' : 'status-warning'; ?>">
                <td>
                    <span class="dashicons <?php echo $check['status'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"
                          style="color: <?php echo $check['status'] ? '#4C9155' : '#dba617'; ?>;"></span>
                </td>
                <td><strong><?php echo esc_html($check['label']); ?></strong></td>
                <td><?php echo esc_html($check['details']); ?></td>
                <td>
                    <?php if (!empty($check['action_url'])): ?>
                    <a href="<?php echo esc_url($check['action_url']); ?>" class="button button-small">
                        <?php echo esc_html($check['action_label'] ?? 'Configure'); ?>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="shaped-admin-info" style="margin-top: 24px;">
        <h3>Environment Information</h3>
        <table class="form-table">
            <tr>
                <th>PHP Version</th>
                <td><?php echo esc_html(PHP_VERSION); ?></td>
            </tr>
            <tr>
                <th>WordPress Version</th>
                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
            </tr>
            <tr>
                <th>Shaped Core Version</th>
                <td><?php echo esc_html(SHAPED_VERSION); ?></td>
            </tr>
            <?php if (class_exists('Shaped_Stripe_Config')): ?>
            <tr>
                <th>Stripe Keys Source</th>
                <td><?php echo Shaped_Stripe_Config::stripe_uses_constants() ? 'wp-config.php constants' : 'Database'; ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<style>
.shaped-health-summary {
    background: #fff;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.shaped-health-summary.is-healthy {
    border-color: #00a32a;
    background: #f0f6f0;
}

.shaped-health-summary.has-issues {
    border-color: #dba617;
    background: #fef8ee;
}

.shaped-health-summary .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
}

.shaped-health-summary.is-healthy .dashicons {
    color: #00a32a;
}

.shaped-health-summary.has-issues .dashicons {
    color: #dba617;
}

.shaped-health-summary .summary-text {
    font-size: 16px;
    font-weight: 500;
}

.shaped-health-table {
    margin-top: 20px;
}

.shaped-health-table tr.status-ok {
    background: #f6f7f7;
}

.shaped-health-table tr.status-warning {
    background: #fef8ee;
}

.shaped-admin-info {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.shaped-admin-info h3 {
    margin-top: 0;
}
</style>
