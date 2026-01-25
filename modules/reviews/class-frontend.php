<?php
/**
 * Reviews Frontend Display
 *
 * Handles standalone review grid rendering without Elementor dependency.
 * Provides filter buttons, review cards grid, and Load More pagination.
 *
 * @package Shaped_Core
 * @subpackage Reviews
 */

namespace Shaped\Modules\Reviews;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {

    /**
     * Reviews per page (initial load and each Load More click)
     */
    const PER_PAGE = 3;

    /**
     * Provider display order (hardcoded per requirements)
     * Only providers with reviews will be shown
     */
    const PROVIDER_ORDER = ['direct', 'booking', 'expedia', 'airbnb', 'tripadvisor', 'google'];

    /**
     * Provider display names
     */
    const PROVIDER_NAMES = [
        'direct'      => 'Direct',
        'booking'     => 'Booking',
        'expedia'     => 'Expedia',
        'airbnb'      => 'Airbnb',
        'tripadvisor' => 'TripAdvisor',
        'google'      => 'Google',
    ];

    /**
     * Initialize frontend hooks
     */
    public static function init(): void {
        // AJAX handlers will be added in Phase 2
        add_action('wp_ajax_shaped_load_more_reviews', [__CLASS__, 'ajax_load_more']);
        add_action('wp_ajax_nopriv_shaped_load_more_reviews', [__CLASS__, 'ajax_load_more']);
    }

    /**
     * Render the complete reviews grid
     *
     * @param string $provider Filter by provider slug, or 'all' for all providers
     * @param int    $page     Page number (1-based)
     * @return string HTML output
     */
    public static function render_grid(string $provider = 'all', int $page = 1): string {
        $active_providers = self::get_active_providers();

        // If no reviews at all, show empty state
        if (empty($active_providers)) {
            return '<div class="shaped-reviews-container"><p class="shaped-reviews-empty">No reviews available.</p></div>';
        }

        $query_args = self::build_query_args($provider, $page);
        $query = new \WP_Query($query_args);

        $total_reviews = $query->found_posts;
        $has_more = ($page * self::PER_PAGE) < $total_reviews;

        ob_start();
        ?>
        <div class="shaped-reviews-container"
             data-per-page="<?php echo esc_attr(self::PER_PAGE); ?>"
             data-provider="<?php echo esc_attr($provider); ?>"
             data-page="<?php echo esc_attr($page); ?>"
             data-total="<?php echo esc_attr($total_reviews); ?>">

            <?php echo self::render_filters($active_providers, $provider); ?>

            <div class="shaped-reviews-grid">
                <?php
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        echo self::render_card(get_post());
                    }
                    wp_reset_postdata();
                } else {
                    echo '<p class="shaped-reviews-empty">No reviews found for this filter.</p>';
                }
                ?>
            </div>

            <div class="shaped-reviews-pagination"<?php echo $has_more ? '' : ' style="display:none;"'; ?>>
                <button type="button"
                        class="shaped-load-more-btn"
                        data-page="<?php echo esc_attr($page); ?>">
                    <span class="shaped-load-more-text">Load More</span>
                    <span class="shaped-load-more-spinner" style="display:none;"></span>
                </button>
            </div>

            <?php wp_nonce_field('shaped_reviews_nonce', 'shaped_reviews_nonce', false); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render filter buttons
     *
     * @param array  $active_providers Providers with reviews
     * @param string $current_provider Currently selected provider
     * @return string HTML output
     */
    public static function render_filters(array $active_providers, string $current_provider): string {
        if (empty($active_providers)) {
            return '';
        }

        ob_start();
        ?>
        <div class="shaped-reviews-filters" role="group" aria-label="Filter reviews by provider">
            <button type="button"
                    class="shaped-filter-btn <?php echo $current_provider === 'all' ? 'active' : ''; ?>"
                    data-provider="all"
                    aria-pressed="<?php echo $current_provider === 'all' ? 'true' : 'false'; ?>">
                All
            </button>
            <?php foreach ($active_providers as $slug => $count): ?>
            <button type="button"
                    class="shaped-filter-btn <?php echo $current_provider === $slug ? 'active' : ''; ?>"
                    data-provider="<?php echo esc_attr($slug); ?>"
                    aria-pressed="<?php echo $current_provider === $slug ? 'true' : 'false'; ?>">
                <?php echo esc_html(self::PROVIDER_NAMES[$slug] ?? ucfirst($slug)); ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single review card
     *
     * @param \WP_Post $post Review post object
     * @return string HTML output
     */
    public static function render_card(\WP_Post $post): string {
        $post_id = $post->ID;
        $provider = get_post_meta($post_id, 'provider', true);
        $provider_slug = strtolower(str_replace(' ', '-', $provider));

        // Get review data
        $author = get_post_meta($post_id, 'author_name', true) ?: 'Guest';
        $rating_html = self::render_rating($post_id);
        $date_html = self::render_date($post_id);
        $badge_html = self::render_badge($post_id);
        $content_html = self::render_content($post);

        ob_start();
        ?>
        <article class="shaped-review-card" data-provider="<?php echo esc_attr($provider_slug); ?>" data-id="<?php echo esc_attr($post_id); ?>">
            <header class="shaped-review-header">
                <div class="shaped-review-meta-left">
                    <h6 class="shaped-review-author"><?php echo esc_html($author); ?></h6>
                    <?php echo $rating_html; ?>
                </div>
                <div class="shaped-review-meta-right">
                    <?php echo $date_html; ?>
                    <?php echo $badge_html; ?>
                </div>
            </header>
            <div class="shaped-review-body">
                <?php echo $content_html; ?>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * Render star rating (reuses logic from shortcodes.php)
     */
    private static function render_rating(int $post_id): string {
        $rating = get_post_meta($post_id, 'review_rating', true);
        $provider = get_post_meta($post_id, 'provider', true);

        if (empty($rating) || empty($provider)) {
            return '<div class="shaped-rating-unified"><div class="shaped-rating-error">Rating unavailable</div></div>';
        }

        $rating = floatval($rating);
        if ($rating <= 0) {
            return '<div class="shaped-rating-unified"><div class="shaped-rating-error">No rating</div></div>';
        }

        $configs = get_provider_configs();
        $provider_key = strtolower(str_replace(['-', '_', ' '], '', $provider));

        // Map common variations
        $provider_map = [
            'bookingcom' => 'booking',
            'googlemaps' => 'google',
        ];
        $provider_key = $provider_map[$provider_key] ?? $provider_key;

        $config = $configs[$provider_key] ?? ['scale' => 10];
        $is_five_scale = $config['scale'] === 5;

        if ($is_five_scale) {
            $rating = min($rating, 5);
            $display_value = ($rating == intval($rating)) ? intval($rating) : number_format($rating, 1);
            $numeric_display = $display_value . '/5';
            $star_rating = round($rating * 2) / 2;
        } else {
            $rating = min($rating, 10);
            $numeric_display = intval(round($rating)) . '/10';
            $star_rating = round(($rating / 2) * 2) / 2;
        }

        // Generate stars
        $stars_html = '';
        $full_stars = floor($star_rating);
        $has_half = ($star_rating - $full_stars) >= 0.5;

        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full_stars) {
                $stars_html .= '<span class="shaped-star shaped-star-full">★</span>';
            } elseif ($i == $full_stars + 1 && $has_half) {
                $stars_html .= '<span class="shaped-star shaped-star-half">★</span>';
            } else {
                $stars_html .= '<span class="shaped-star shaped-star-empty">★</span>';
            }
        }

        return sprintf(
            '<div class="shaped-rating-unified">
                <div class="shaped-rating-stars">%s</div>
                <div class="shaped-rating-numeric">%s</div>
            </div>',
            $stars_html,
            esc_html($numeric_display)
        );
    }

    /**
     * Render review date
     */
    private static function render_date(int $post_id): string {
        $date = get_post_meta($post_id, 'review_date', true);

        if (empty($date)) {
            return '<span class="shaped-review-date">Date unavailable</span>';
        }

        $formatted = date('d.m.Y', strtotime($date));
        return '<span class="shaped-review-date">' . esc_html($formatted) . '</span>';
    }

    /**
     * Render provider badge
     */
    private static function render_badge(int $post_id): string {
        $provider = get_post_meta($post_id, 'provider', true);

        if (empty($provider)) {
            return '<span class="shaped-provider-badge" style="background-color: #666; color: #fff;">Unknown</span>';
        }

        $configs = get_provider_configs();
        $links = get_provider_links();

        $provider_key = strtolower(str_replace(['-', '_', ' '], '', $provider));
        $provider_map = [
            'bookingcom' => 'booking',
            'googlemaps' => 'google',
        ];
        $provider_key = $provider_map[$provider_key] ?? $provider_key;

        $config = $configs[$provider_key] ?? [
            'name' => ucfirst($provider),
            'bg'   => '#666',
            'text' => '#fff'
        ];

        $url = $links[$provider_key] ?? '#';

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener" class="shaped-provider-badge" data-provider="%s" style="background-color: %s; color: %s;">%s</a>',
            esc_url($url),
            esc_attr($provider_key),
            esc_attr($config['bg']),
            esc_attr($config['text']),
            esc_html($config['name'])
        );
    }

    /**
     * Render review content with Read More
     */
    private static function render_content(\WP_Post $post): string {
        $content = $post->post_content;
        $char_limit_desktop = 145;
        $char_limit_mobile = 135;

        if (empty($content)) {
            return '<div class="shaped-review-text">No review content available.</div>';
        }

        $content = wp_strip_all_tags($content);

        // Check if truncation needed
        if (strlen($content) <= $char_limit_mobile) {
            return '<div class="shaped-review-text">' . esc_html($content) . '</div>';
        }

        // Desktop truncated
        $truncated_desktop = substr($content, 0, $char_limit_desktop);
        $last_space = strrpos($truncated_desktop, ' ');
        if ($last_space !== false && $last_space > ($char_limit_desktop * 0.8)) {
            $truncated_desktop = substr($truncated_desktop, 0, $last_space);
        }

        // Mobile truncated
        $truncated_mobile = substr($content, 0, $char_limit_mobile);
        $last_space = strrpos($truncated_mobile, ' ');
        if ($last_space !== false && $last_space > ($char_limit_mobile * 0.8)) {
            $truncated_mobile = substr($truncated_mobile, 0, $last_space);
        }

        $unique_id = 'review-' . $post->ID;

        return sprintf(
            '<div class="shaped-review-content-wrapper" id="%s">
                <div class="shaped-review-text">
                    <span class="shaped-text-truncated shaped-desktop-truncated">%s...</span>
                    <span class="shaped-text-truncated shaped-mobile-truncated">%s...</span>
                    <span class="shaped-text-full" style="display:none;">%s</span>
                </div>
                <button type="button" class="shaped-read-more-btn" onclick="shapedToggleReadMore(\'%s\')">Read more</button>
            </div>',
            esc_attr($unique_id),
            esc_html($truncated_desktop),
            esc_html($truncated_mobile),
            esc_html($content),
            esc_attr($unique_id)
        );
    }

    /**
     * Get providers that have at least one review, ordered by PROVIDER_ORDER
     *
     * @return array Associative array of provider_slug => count
     */
    public static function get_active_providers(): array {
        global $wpdb;

        // Get all providers with review counts
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT pm.meta_value as provider, COUNT(*) as count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'provider'
            AND pm.meta_value != ''
            AND p.post_type = %s
            AND p.post_status = 'publish'
            GROUP BY pm.meta_value
        ", CPT::POST_TYPE));

        if (empty($results)) {
            return [];
        }

        // Normalize and map results
        $provider_counts = [];
        foreach ($results as $row) {
            $slug = strtolower(str_replace([' ', '-', '_'], '', $row->provider));

            // Map variations to canonical slugs
            $slug_map = [
                'bookingcom' => 'booking',
                'googlemaps' => 'google',
            ];
            $slug = $slug_map[$slug] ?? $slug;

            // Only include known providers
            if (in_array($slug, self::PROVIDER_ORDER, true)) {
                $provider_counts[$slug] = ($provider_counts[$slug] ?? 0) + intval($row->count);
            }
        }

        // Sort by PROVIDER_ORDER, keeping only providers with reviews
        $ordered = [];
        foreach (self::PROVIDER_ORDER as $slug) {
            if (isset($provider_counts[$slug]) && $provider_counts[$slug] > 0) {
                $ordered[$slug] = $provider_counts[$slug];
            }
        }

        return $ordered;
    }

    /**
     * Build WP_Query arguments
     *
     * @param string $provider Provider slug or 'all'
     * @param int    $page     Page number (1-based)
     * @return array WP_Query arguments
     */
    public static function build_query_args(string $provider = 'all', int $page = 1): array {
        $args = [
            'post_type'      => CPT::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => self::PER_PAGE,
            'paged'          => $page,
            // Sorting will be handled by the existing hooks in Admin class
            // which add posts_join and posts_orderby filters for shaped_review queries
        ];

        // Provider filter
        if ($provider !== 'all' && in_array($provider, self::PROVIDER_ORDER, true)) {
            // Map canonical slug back to possible meta values
            $meta_values = [$provider];
            if ($provider === 'booking') {
                $meta_values[] = 'booking.com';
                $meta_values[] = 'Booking';
            } elseif ($provider === 'google') {
                $meta_values[] = 'google-maps';
                $meta_values[] = 'Google';
            }

            $args['meta_query'] = [
                [
                    'key'     => 'provider',
                    'value'   => $meta_values,
                    'compare' => 'IN'
                ]
            ];
        }

        return $args;
    }

    /**
     * Render only the cards (for AJAX Load More)
     *
     * @param string $provider Provider slug or 'all'
     * @param int    $page     Page number
     * @return array Contains 'html', 'has_more', 'total'
     */
    public static function render_cards_only(string $provider = 'all', int $page = 1): array {
        $query_args = self::build_query_args($provider, $page);
        $query = new \WP_Query($query_args);

        $total = $query->found_posts;
        $has_more = ($page * self::PER_PAGE) < $total;

        ob_start();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                echo self::render_card(get_post());
            }
            wp_reset_postdata();
        }
        $html = ob_get_clean();

        return [
            'html'     => $html,
            'has_more' => $has_more,
            'total'    => $total,
            'page'     => $page,
        ];
    }

    /**
     * AJAX handler for Load More and filter changes
     */
    public static function ajax_load_more(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'shaped_reviews_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : 'all';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

        // Validate provider
        if ($provider !== 'all' && !in_array($provider, self::PROVIDER_ORDER, true)) {
            $provider = 'all';
        }

        $result = self::render_cards_only($provider, $page);

        wp_send_json_success($result);
    }
}
