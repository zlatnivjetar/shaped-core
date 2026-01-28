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
$stripe_configured = class_exists('Shaped_Stripe_Config')
    && Shaped_Stripe_Config::get_stripe_secret() !== '';

$roomcloud_enabled = defined('SHAPED_ENABLE_ROOMCLOUD') && SHAPED_ENABLE_ROOMCLOUD;

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

    <!-- System Status -->
    <div class="shaped-status-grid">
        <div class="shaped-status-card">
            <h3>Shaped Core</h3>
            <ul class="status-list">
                <li>
                    <span class="status-indicator <?php echo $stripe_configured ? 'status-ok' : 'status-warning'; ?>"></span>
                    Stripe: <?php echo $stripe_configured ? 'Configured' : 'Not configured'; ?>
                </li>
            </ul>
            <p class="card-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-system-health')); ?>">Config Health</a> |
                <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-pricing')); ?>">Pricing Settings</a>
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
