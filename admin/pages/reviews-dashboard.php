<?php
/**
 * Reviews Dashboard
 * Overview page for guest reviews management
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

use Shaped\Modules\Reviews\CPT;

$post_type = defined('Shaped\Modules\Reviews\CPT::POST_TYPE')
    ? CPT::POST_TYPE
    : 'shaped_review';

// Get review counts
$counts = wp_count_posts($post_type);
$total_reviews = ($counts->publish ?? 0) + ($counts->draft ?? 0) + ($counts->pending ?? 0);
$published_reviews = $counts->publish ?? 0;
$draft_reviews = $counts->draft ?? 0;

// Featured reviews count
global $wpdb;
$featured_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = %s AND p.post_status = 'publish'
    AND pm.meta_key = 'is_featured' AND pm.meta_value = '1'",
    $post_type
));

// Average rating (normalize all to 5-star scale)
$ratings = $wpdb->get_results($wpdb->prepare(
    "SELECT pm1.meta_value as rating, pm2.meta_value as provider
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'review_rating'
    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'provider'
    WHERE p.post_type = %s AND p.post_status = 'publish'",
    $post_type
));

$total_normalized = 0;
$rating_count = 0;
foreach ($ratings as $r) {
    $rating = floatval($r->rating);
    $provider = strtolower($r->provider ?? '');

    // Normalize Booking.com (1-10) to 5-star scale
    if (strpos($provider, 'booking') !== false && $rating > 5) {
        $rating = $rating / 2;
    }

    if ($rating > 0) {
        $total_normalized += $rating;
        $rating_count++;
    }
}

$average_rating = $rating_count > 0 ? round($total_normalized / $rating_count, 1) : 0;

// Reviews by provider
$providers = $wpdb->get_results($wpdb->prepare(
    "SELECT pm.meta_value as provider, COUNT(*) as count
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = %s AND p.post_status = 'publish'
    AND pm.meta_key = 'provider' AND pm.meta_value != ''
    GROUP BY pm.meta_value
    ORDER BY count DESC",
    $post_type
));

// Recent reviews
$recent_reviews = new WP_Query([
    'post_type'      => $post_type,
    'posts_per_page' => 5,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
]);

// Check if sync is available
$sync_available = class_exists('Shaped\Modules\Reviews\Sync');
?>

<div class="wrap shaped-reviews-dashboard">
    <h1>Guest Reviews</h1>
    <p class="description">Manage and monitor guest reviews from all sources.</p>

    <!-- Stats Cards -->
    <div class="shaped-stats-grid">
        <div class="shaped-stat-card">
            <div class="stat-icon dashicons dashicons-star-filled"></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html($average_rating); ?></span>
                <span class="stat-label">Average Rating</span>
            </div>
        </div>

        <div class="shaped-stat-card">
            <div class="stat-icon dashicons dashicons-testimonial"></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html($published_reviews); ?></span>
                <span class="stat-label">Published Reviews</span>
            </div>
        </div>

        <div class="shaped-stat-card">
            <div class="stat-icon dashicons dashicons-heart"></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html($featured_count); ?></span>
                <span class="stat-label">Featured</span>
            </div>
        </div>

        <div class="shaped-stat-card">
            <div class="stat-icon dashicons dashicons-edit"></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html($draft_reviews); ?></span>
                <span class="stat-label">Drafts</span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="shaped-section">
        <h2>Quick Actions</h2>
        <div class="shaped-quick-actions">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $post_type)); ?>" class="button button-primary button-hero">
                <span class="dashicons dashicons-list-view"></span>
                All Reviews
            </a>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $post_type)); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-plus-alt"></span>
                Add Review
            </a>
            <?php if ($sync_available): ?>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $post_type . '&page=shaped-reviews-sync')); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-update"></span>
                Sync Settings
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="shaped-columns">
        <!-- Reviews by Provider -->
        <?php if (!empty($providers)): ?>
        <div class="shaped-section shaped-column">
            <h2>Reviews by Source</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th style="width: 80px; text-align: right;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($providers as $provider):
                        $provider_name = ucfirst(str_replace(['-', '_'], ' ', $provider->provider));
                        $filter_url = admin_url('edit.php?post_type=' . $post_type . '&provider=' . urlencode($provider->provider));
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($filter_url); ?>"><?php echo esc_html($provider_name); ?></a>
                        </td>
                        <td style="text-align: right;"><?php echo esc_html($provider->count); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Recent Reviews -->
        <?php if ($recent_reviews->have_posts()): ?>
        <div class="shaped-section shaped-column">
            <h2>Recent Reviews</h2>
            <div class="recent-reviews-list">
                <?php while ($recent_reviews->have_posts()): $recent_reviews->the_post();
                    $review_id = get_the_ID();
                    $author = get_post_meta($review_id, 'author_name', true) ?: 'Guest';
                    $rating = get_post_meta($review_id, 'review_rating', true);
                    $provider = get_post_meta($review_id, 'provider', true);
                    $date = get_post_meta($review_id, 'review_date', true);

                    // Normalize rating display
                    if (strpos(strtolower($provider), 'booking') !== false && $rating > 5) {
                        $display_rating = $rating . '/10';
                    } else {
                        $display_rating = $rating . '/5';
                    }
                ?>
                <div class="review-item">
                    <div class="review-header">
                        <strong class="review-author"><?php echo esc_html($author); ?></strong>
                        <span class="review-rating"><?php echo esc_html($display_rating); ?></span>
                    </div>
                    <div class="review-excerpt">
                        <?php echo wp_trim_words(get_the_content(), 20, '...'); ?>
                    </div>
                    <div class="review-meta">
                        <span class="review-provider"><?php echo esc_html(ucfirst($provider)); ?></span>
                        <?php if ($date): ?>
                            <span class="review-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($date))); ?></span>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(get_edit_post_link($review_id)); ?>" class="review-edit">Edit</a>
                    </div>
                </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            <p style="margin-top: 15px;">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $post_type)); ?>">View all reviews &rarr;</a>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.shaped-reviews-dashboard {
    max-width: 1200px;
}

.shaped-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.shaped-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.shaped-stat-card .stat-icon {
    font-size: 28px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fef8e7;
    border-radius: 50%;
    color: #dba617;
}

.shaped-stat-card .stat-content {
    flex: 1;
}

.shaped-stat-card .stat-number {
    display: block;
    font-size: 24px;
    font-weight: 600;
    line-height: 1.2;
    color: #1d2327;
}

.shaped-stat-card .stat-label {
    display: block;
    font-size: 12px;
    color: #50575e;
    margin-top: 2px;
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

.shaped-quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.shaped-quick-actions .button-hero {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    height: auto;
    font-size: 14px;
}

.shaped-quick-actions .button-hero .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.shaped-columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
}

.shaped-columns .shaped-section {
    margin: 0;
}

.recent-reviews-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.review-item {
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
    border-left: 3px solid #dba617;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.review-author {
    font-size: 14px;
    color: #1d2327;
}

.review-rating {
    font-size: 13px;
    font-weight: 600;
    color: #dba617;
}

.review-excerpt {
    font-size: 13px;
    color: #50575e;
    line-height: 1.5;
    margin-bottom: 10px;
}

.review-meta {
    display: flex;
    gap: 15px;
    font-size: 11px;
    color: #787c82;
}

.review-meta a {
    text-decoration: none;
}
</style>
