<?php
/**
 * Shaped Shortcodes Reference Page
 * Lists all available shortcodes with descriptions and examples
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

// Define all shortcodes with their info
$shortcodes = [
    'Booking System' => [
        [
            'name'        => 'shaped_manage_booking',
            'description' => 'Displays the booking management interface for guests to view and manage their reservations.',
            'example'     => '[shaped_manage_booking]',
            'attributes'  => [],
        ],
        [
            'name'        => 'shaped_booking_cancelled',
            'description' => 'Shows confirmation content when a booking has been cancelled.',
            'example'     => '[shaped_booking_cancelled]',
            'attributes'  => [],
        ],
        [
            'name'        => 'shaped_thank_you',
            'description' => 'Displays the thank you message after a successful booking.',
            'example'     => '[shaped_thank_you]',
            'attributes'  => [],
        ],
    ],
    'Room Display' => [
        [
            'name'        => 'shaped_room_cards',
            'description' => 'Displays room cards with customizable templates for showcasing accommodation options.',
            'example'     => '[shaped_room_cards template="grid"]',
            'attributes'  => ['template'],
        ],
        [
            'name'        => 'shaped_room_details',
            'description' => 'Shows the full room description and details for a specific room type.',
            'example'     => '[shaped_room_details]',
            'attributes'  => [],
        ],
        [
            'name'        => 'shaped_meta',
            'description' => 'Displays a specific metadata value for a room (e.g., amenities, capacity, size).',
            'example'     => '[shaped_meta key="capacity"]',
            'attributes'  => ['key'],
        ],
    ],
    'Modal Links' => [
        [
            'name'        => 'shaped_modal',
            'description' => 'Creates a link that opens a page in a modal dialog (e.g., terms and conditions).',
            'example'     => '[shaped_modal page="booking-terms" label="Booking Terms"]',
            'attributes'  => ['page', 'label'],
        ],
    ],
    'Reviews & Ratings' => [
        [
            'name'        => 'shaped_unified_rating',
            'description' => 'Displays a star rating visualization for reviews.',
            'example'     => '[shaped_unified_rating]',
            'attributes'  => [],
        ],
        [
            'name'        => 'shaped_review_author',
            'description' => 'Shows the author name for a review.',
            'example'     => '[shaped_review_author]',
            'attributes'  => [],
        ],
        [
            'name'        => 'shaped_review_date',
            'description' => 'Displays the formatted date when a review was submitted.',
            'example'     => '[shaped_review_date format="F j, Y"]',
            'attributes'  => ['format'],
        ],
        [
            'name'        => 'shaped_review_content',
            'description' => 'Shows the review text content with optional read more button for long reviews.',
            'example'     => '[shaped_review_content desktop_limit="200" mobile_limit="100"]',
            'attributes'  => ['desktop_limit', 'mobile_limit'],
        ],
        [
            'name'        => 'shaped_provider_badge',
            'description' => 'Displays a badge with the review provider logo and rating link.',
            'example'     => '[shaped_provider_badge]',
            'attributes'  => [],
        ],
    ],
    'Pricing' => [
        [
            'name'        => 'shaped_official_prices',
            'description' => 'Displays the official direct booking prices for all room types.',
            'example'     => '[shaped_official_prices]',
            'attributes'  => [],
        ],
    ],
];
?>

<div class="wrap shaped-shortcodes-page">
    <h1>Shortcodes Reference</h1>
    <p class="description">Complete list of all available Shaped Core shortcodes for your booking system.</p>

    <div class="shaped-shortcodes-intro">
        <h3>How to Use Shortcodes</h3>
        <p>Shortcodes are special tags you can add to pages, posts, or widgets to display dynamic content. Copy the shortcode and paste it into your content editor where you want the content to appear.</p>
    </div>

    <?php foreach ($shortcodes as $category => $items): ?>
    <div class="shaped-shortcode-section">
        <h2><?php echo esc_html($category); ?></h2>
        <div class="shortcode-cards">
            <?php foreach ($items as $shortcode): ?>
            <div class="shortcode-card">
                <div class="shortcode-header">
                    <code class="shortcode-name">[<?php echo esc_html($shortcode['name']); ?>]</code>
                    <button type="button" class="button button-small copy-shortcode" data-shortcode="<?php echo esc_attr($shortcode['example']); ?>">
                        <span class="dashicons dashicons-clipboard"></span> Copy
                    </button>
                </div>
                <p class="shortcode-description"><?php echo esc_html($shortcode['description']); ?></p>
                <?php if (!empty($shortcode['attributes'])): ?>
                <div class="shortcode-attributes">
                    <strong>Attributes:</strong>
                    <?php echo esc_html(implode(', ', $shortcode['attributes'])); ?>
                </div>
                <?php endif; ?>
                <div class="shortcode-example">
                    <strong>Example:</strong>
                    <code><?php echo esc_html($shortcode['example']); ?></code>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="shaped-shortcode-section shaped-modal-info">
        <h2>Modal Page Types</h2>
        <p>The <code>[shaped_modal]</code> shortcode supports these page types:</p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Page Type</th>
                    <th>Page Title (Hardcoded)</th>
                    <th>Example Usage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>booking-terms</code></td>
                    <td>Terms and Conditions</td>
                    <td><code>[shaped_modal page="booking-terms" label="Booking Terms"]</code></td>
                </tr>
                <tr>
                    <td><code>privacy</code></td>
                    <td>Privacy Policy</td>
                    <td><code>[shaped_modal page="privacy" label="Privacy Policy"]</code></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
.shaped-shortcodes-page {
    max-width: 1200px;
}

.shaped-shortcodes-intro {
    background: #f0f6fc;
    border-left: 4px solid #2271b1;
    padding: 15px 20px;
    margin: 20px 0;
}

.shaped-shortcodes-intro h3 {
    margin: 0 0 8px;
    font-size: 14px;
}

.shaped-shortcodes-intro p {
    margin: 0;
    color: #50575e;
}

.shaped-shortcode-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px 25px;
    margin: 25px 0;
}

.shaped-shortcode-section h2 {
    margin: 0 0 20px;
    padding: 0 0 10px;
    border-bottom: 1px solid #f0f0f1;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.shortcode-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.shortcode-card {
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    padding: 15px 18px;
}

.shortcode-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.shortcode-name {
    background: #2271b1;
    color: #fff;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
}

.copy-shortcode {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px !important;
    padding: 2px 8px !important;
}

.copy-shortcode .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.copy-shortcode.copied {
    background: #00a32a !important;
    border-color: #00a32a !important;
    color: #fff !important;
}

.shortcode-description {
    margin: 0 0 10px;
    font-size: 13px;
    color: #50575e;
    line-height: 1.5;
}

.shortcode-attributes {
    font-size: 12px;
    color: #646970;
    margin-bottom: 8px;
}

.shortcode-example {
    font-size: 12px;
    background: #fff;
    padding: 8px 10px;
    border-radius: 4px;
    border: 1px solid #e5e5e5;
}

.shortcode-example code {
    background: none;
    padding: 0;
    color: #1e1e1e;
    font-size: 12px;
}

.shaped-modal-info table {
    margin-top: 15px;
}

.shaped-modal-info td code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.copy-shortcode').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var shortcode = this.getAttribute('data-shortcode');
            navigator.clipboard.writeText(shortcode).then(function() {
                btn.classList.add('copied');
                btn.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<span class="dashicons dashicons-clipboard"></span> Copy';
                }, 2000);
            });
        });
    });
});
</script>
