<?php
/**
 * Shaped Review Email Module
 *
 * Sends automated review request emails to guests 4 hours after checkout.
 * Creates a landing page shortcode for guests to leave ratings and feedback.
 *
 * Activation: Define SHAPED_ENABLE_REVIEW_EMAIL in wp-config.php
 *
 * @package Shaped_Core
 * @subpackage ReviewEmail
 * @since 2.1.0
 */

namespace Shaped\Modules\ReviewEmail;

if (!defined('ABSPATH')) {
    exit;
}

// Check if module is enabled
if (!defined('SHAPED_ENABLE_REVIEW_EMAIL') || !SHAPED_ENABLE_REVIEW_EMAIL) {
    return;
}

/**
 * Module constants
 */
define('SHAPED_REVIEW_EMAIL_VERSION', '1.0.0');
define('SHAPED_REVIEW_EMAIL_DIR', __DIR__ . '/');

/**
 * Load module components
 */
require_once SHAPED_REVIEW_EMAIL_DIR . 'class-scheduler.php';
require_once SHAPED_REVIEW_EMAIL_DIR . 'class-email.php';
require_once SHAPED_REVIEW_EMAIL_DIR . 'class-shortcode.php';

/**
 * Initialize module
 */
add_action('init', function() {
    // Initialize scheduler (cron jobs for review emails)
    new Scheduler();

    // Initialize shortcode
    new Shortcode();
}, 10);

/**
 * Register custom cron interval for checkout monitoring
 */
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['every_hour'])) {
        $schedules['every_hour'] = [
            'interval' => 3600,
            'display'  => __('Every Hour', 'shaped'),
        ];
    }
    return $schedules;
});

/**
 * Ensure Direct provider term exists in reviews taxonomy
 */
add_action('init', function() {
    if (!term_exists('direct', 'review_provider')) {
        wp_insert_term('Direct', 'review_provider', ['slug' => 'direct']);
    }
}, 15);
