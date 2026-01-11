<?php
/**
 * Reviews Admin Enhancements
 * 
 * Admin columns, filters, meta boxes, bulk actions
 * 
 * @package Shaped_Core
 * @subpackage Reviews
 */

namespace Shaped\Modules\Reviews;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {

    /**
     * Initialize admin hooks
     */
    public static function init(): void {
        // Columns
        add_filter('manage_' . CPT::POST_TYPE . '_posts_columns', [__CLASS__, 'add_columns']);
        add_action('manage_' . CPT::POST_TYPE . '_posts_custom_column', [__CLASS__, 'render_column'], 10, 2);
        add_filter('manage_edit-' . CPT::POST_TYPE . '_sortable_columns', [__CLASS__, 'sortable_columns']);
        add_action('pre_get_posts', [__CLASS__, 'handle_column_sorting']);

        // Filters
        add_action('restrict_manage_posts', [__CLASS__, 'add_filters']);
        add_filter('parse_query', [__CLASS__, 'apply_filters']);

        // Meta boxes
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_' . CPT::POST_TYPE, [__CLASS__, 'save_meta_box'], 10, 3);
        add_action('save_post_' . CPT::POST_TYPE, [__CLASS__, 'auto_lock_on_content_edit'], 5, 3);

        // Quick edit
        add_action('quick_edit_custom_box', [__CLASS__, 'quick_edit_box'], 10, 2);
        add_action('admin_footer-edit.php', [__CLASS__, 'quick_edit_js']);
        add_action('wp_ajax_save_review_quick_edit', [__CLASS__, 'ajax_save_quick_edit']);

        // Bulk actions
        add_filter('bulk_actions-edit-' . CPT::POST_TYPE, [__CLASS__, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-' . CPT::POST_TYPE, [__CLASS__, 'handle_bulk_actions'], 10, 3);

        // Admin styles
        add_action('admin_head', [__CLASS__, 'admin_styles']);

        // Admin notices
        add_action('admin_notices', [__CLASS__, 'show_auto_lock_notice']);

        // Elementor query (specific query ID support)
        add_action('elementor/query/featured_reviews_query', [__CLASS__, 'elementor_featured_query']);
    }

    /**
     * Initialize frontend hooks
     */
    public static function init_frontend(): void {
        add_filter('posts_orderby', [__CLASS__, 'force_review_sorting'], 999, 2);
        add_filter('posts_join', [__CLASS__, 'join_meta_for_sorting'], 999, 2);
    }

    /**
     * Add custom columns
     */
    public static function add_columns(array $columns): array {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['rating']   = 'Rating';
                $new_columns['provider'] = 'Provider';
                $new_columns['featured'] = 'Featured';
                $new_columns['priority'] = 'Priority';
            }
        }
        
        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public static function render_column(string $column, int $post_id): void {
        switch ($column) {
            case 'rating':
                self::render_rating_column($post_id);
                break;
            case 'provider':
                self::render_provider_column($post_id);
                break;
            case 'featured':
                self::render_featured_column($post_id);
                break;
            case 'priority':
                self::render_priority_column($post_id);
                break;
        }
    }

    /**
     * Render rating column
     */
    private static function render_rating_column(int $post_id): void {
        $rating = get_post_meta($post_id, 'review_rating', true);
        $provider = get_post_meta($post_id, 'provider', true);

        if (!$rating) {
            echo '—';
            return;
        }

        $is_five_scale = in_array(strtolower($provider), ['google', 'tripadvisor']);

        if ($is_five_scale) {
            $star_rating = floatval($rating);
            $display = $rating . '/5';
        } else {
            $star_rating = floatval($rating) / 2;
            $display = $rating . '/10';
        }

        $full_stars = floor($star_rating);
        $has_half = ($star_rating - $full_stars) >= 0.5;

        echo '<div style="white-space:nowrap;">';
        echo '<span style="color:#D1AF5D; font-size:16px;">';

        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full_stars) {
                echo '★';
            } elseif ($i == $full_stars + 1 && $has_half) {
                echo '☆';
            } else {
                echo '<span style="opacity:0.3;">★</span>';
            }
        }

        echo '</span>';
        echo ' <small style="color:#666;">(' . esc_html($display) . ')</small>';
        echo '</div>';
    }

    /**
     * Render provider column
     */
    private static function render_provider_column(int $post_id): void {
        $provider = get_post_meta($post_id, 'provider', true);

        if (!$provider) {
            echo '—';
            return;
        }

        $configs = [
            'booking'     => ['name' => 'Booking', 'color' => '#003580'],
            'google'      => ['name' => 'Google', 'color' => '#4285f4'],
            'tripadvisor' => ['name' => 'TripAdvisor', 'color' => '#00af87'],
            'expedia'     => ['name' => 'Expedia', 'color' => '#ffda00', 'text' => '#000']
        ];

        $provider_lower = strtolower($provider);
        $config = $configs[$provider_lower] ?? ['name' => ucfirst($provider), 'color' => '#666'];
        $text_color = $config['text'] ?? '#fff';

        printf(
            '<span style="display:inline-block; padding:2px 8px; border-radius:3px; background:%s; color:%s; font-size:11px; font-weight:600;">%s</span>',
            esc_attr($config['color']),
            esc_attr($text_color),
            esc_html($config['name'])
        );
    }

    /**
     * Render featured column
     */
    private static function render_featured_column(int $post_id): void {
        $is_featured = get_post_meta($post_id, 'is_featured', true);
        
        if ($is_featured) {
            echo '<span style="color:#D1AF5D; font-size:18px;" title="Featured">⭐</span>';
        } else {
            echo '<span style="color:#ddd;">—</span>';
        }
    }

    /**
     * Render priority column
     */
    private static function render_priority_column(int $post_id): void {
        $priority = get_post_meta($post_id, 'priority', true);
        $is_featured = get_post_meta($post_id, 'is_featured', true);

        if ($is_featured && $priority > 0) {
            echo '<strong style="color:#D1AF5D;">' . intval($priority) . '</strong>';
        } elseif ($priority > 0) {
            echo intval($priority);
        } else {
            echo '<span style="color:#ddd;">0</span>';
        }
    }

    /**
     * Define sortable columns
     */
    public static function sortable_columns(array $columns): array {
        $columns['rating']   = 'rating';
        $columns['provider'] = 'provider';
        $columns['featured'] = 'featured';
        $columns['priority'] = 'priority';
        return $columns;
    }

    /**
     * Handle column sorting (admin only)
     */
    public static function handle_column_sorting(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== CPT::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');

        $meta_keys = [
            'rating'   => ['key' => 'review_rating', 'type' => 'meta_value_num'],
            'provider' => ['key' => 'provider', 'type' => 'meta_value'],
            'featured' => ['key' => 'is_featured', 'type' => 'meta_value'],
            'priority' => ['key' => 'priority', 'type' => 'meta_value_num'],
        ];

        if (isset($meta_keys[$orderby])) {
            $query->set('meta_key', $meta_keys[$orderby]['key']);
            $query->set('orderby', $meta_keys[$orderby]['type']);
        }
    }

    /**
     * Join meta tables for sorting
     */
    public static function join_meta_for_sorting(string $join, \WP_Query $query): string {
        global $wpdb;

        if (!self::is_frontend_review_query($query)) {
            return $join;
        }

        // Check if joins already exist to prevent duplicates
        if (strpos($join, 'shaped_feat_meta') !== false) {
            return $join;
        }

        $join .= " LEFT JOIN {$wpdb->postmeta} AS shaped_feat_meta ON ({$wpdb->posts}.ID = shaped_feat_meta.post_id AND shaped_feat_meta.meta_key = 'is_featured')";
        $join .= " LEFT JOIN {$wpdb->postmeta} AS shaped_prio_meta ON ({$wpdb->posts}.ID = shaped_prio_meta.post_id AND shaped_prio_meta.meta_key = 'priority')";

        return $join;
    }

    /**
     * Force review sorting order
     */
    public static function force_review_sorting(string $orderby, \WP_Query $query): string {
        global $wpdb;

        if (!self::is_frontend_review_query($query)) {
            return $orderby;
        }

        return "CAST(COALESCE(shaped_feat_meta.meta_value, '0') AS UNSIGNED) DESC,
                CAST(COALESCE(shaped_prio_meta.meta_value, '0') AS UNSIGNED) DESC,
                {$wpdb->posts}.post_date DESC";
    }

    /**
     * Check if this is a frontend review query
     */
    private static function is_frontend_review_query(\WP_Query $query): bool {
        if (is_admin() && !wp_doing_ajax()) {
            return false;
        }

        $post_type = $query->get('post_type');

        if (is_array($post_type)) {
            return in_array(CPT::POST_TYPE, $post_type, true);
        }

        return $post_type === CPT::POST_TYPE;
    }

    /**
     * Add filter dropdowns
     */
    public static function add_filters(string $post_type): void {
        if ($post_type !== CPT::POST_TYPE) {
            return;
        }

        global $wpdb;

        $providers = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'provider' 
            AND meta_value != ''
            ORDER BY meta_value
        ");

        $current_provider = $_GET['provider_filter'] ?? '';
        ?>
        <select name="provider_filter">
            <option value="">All Providers</option>
            <?php foreach ($providers as $provider): ?>
                <option value="<?php echo esc_attr($provider); ?>" <?php selected($current_provider, $provider); ?>>
                    <?php echo esc_html(ucfirst($provider)); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="featured_filter">
            <option value="">All Reviews</option>
            <option value="featured" <?php selected(($_GET['featured_filter'] ?? ''), 'featured'); ?>>Featured Only</option>
            <option value="not_featured" <?php selected(($_GET['featured_filter'] ?? ''), 'not_featured'); ?>>Not Featured</option>
        </select>
        <?php
    }

    /**
     * Apply filters to query
     */
    public static function apply_filters(\WP_Query $query): void {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'edit.php' || 
            !isset($_GET['post_type']) || $_GET['post_type'] !== CPT::POST_TYPE) {
            return;
        }

        $meta_query = [];

        if (!empty($_GET['provider_filter'])) {
            $meta_query[] = [
                'key'     => 'provider',
                'value'   => sanitize_text_field($_GET['provider_filter']),
                'compare' => '='
            ];
        }

        if (!empty($_GET['featured_filter'])) {
            if ($_GET['featured_filter'] === 'featured') {
                $meta_query[] = [
                    'key'     => 'is_featured',
                    'value'   => '1',
                    'compare' => '='
                ];
            } elseif ($_GET['featured_filter'] === 'not_featured') {
                $meta_query[] = [
                    'relation' => 'OR',
                    ['key' => 'is_featured', 'value' => '0', 'compare' => '='],
                    ['key' => 'is_featured', 'compare' => 'NOT EXISTS']
                ];
            }
        }

        if (!empty($meta_query)) {
            $query->query_vars['meta_query'] = $meta_query;
        }
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes(): void {
        add_meta_box(
            'shaped_featured_settings',
            'Featured Review Settings',
            [__CLASS__, 'render_meta_box'],
            CPT::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render meta box
     */
    public static function render_meta_box(\WP_Post $post): void {
        $is_featured = get_post_meta($post->ID, 'is_featured', true);
        $priority = get_post_meta($post->ID, 'priority', true);
        $is_locked = get_post_meta($post->ID, 'featured_locked', true);
        $is_content_locked = get_post_meta($post->ID, 'content_locked', true);

        wp_nonce_field('shaped_featured_nonce', 'shaped_featured_nonce');
        ?>
        <p>
            <label>
                <input type="checkbox" name="shaped_is_featured" value="1" <?php checked($is_featured, '1'); ?>>
                <strong>Feature this review</strong>
            </label>
        </p>
        <p>
            <label for="shaped_priority">Priority (0-100):</label><br>
            <input type="number" id="shaped_priority" name="shaped_priority"
                   value="<?php echo esc_attr($priority ?: '0'); ?>"
                   min="0" max="100" style="width:100%">
            <small>Higher numbers appear first among featured reviews</small>
        </p>
        <p>
            <label>
                <input type="checkbox" name="shaped_featured_locked" value="1" <?php checked($is_locked, '1'); ?>>
                <strong>Lock featured settings</strong>
            </label>
            <br><small>Prevents sync from overwriting these values</small>
        </p>
        <p>
            <label>
                <input type="checkbox" name="shaped_content_locked" value="1" <?php checked($is_content_locked, '1'); ?>>
                <strong>Lock review text</strong>
            </label>
            <br><small>Prevents sync from overwriting manual text edits</small>
        </p>
        <?php
    }

    /**
     * Auto-lock content when manually edited
     * Runs before save_meta_box to detect content changes
     */
    public static function auto_lock_on_content_edit(int $post_id, \WP_Post $post, bool $update): void {
        // Skip if this is a new post
        if (!$update) {
            return;
        }

        // Skip if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip if sync is running (check for sync flag)
        if (defined('SHAPED_REVIEWS_SYNC_RUNNING') && SHAPED_REVIEWS_SYNC_RUNNING) {
            return;
        }

        // Skip if user can't edit
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Skip if content is already locked
        $is_locked = get_post_meta($post_id, 'content_locked', true);
        if ($is_locked === '1') {
            return;
        }

        // Get the post from database (before this save)
        $old_post = get_post($post_id);
        if (!$old_post) {
            return;
        }

        // Compare content - if changed, auto-lock
        if ($post->post_content !== $old_post->post_content) {
            update_post_meta($post_id, 'content_locked', '1');

            // Add admin notice (shown on next page load)
            add_option('shaped_review_auto_locked_' . $post_id, '1');
        }
    }

    /**
     * Save meta box
     */
    public static function save_meta_box(int $post_id, \WP_Post $post, bool $update): void {
        if (!isset($_POST['shaped_featured_nonce']) || 
            !wp_verify_nonce($_POST['shaped_featured_nonce'], 'shaped_featured_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta($post_id, 'is_featured', isset($_POST['shaped_is_featured']) ? '1' : '0');
        update_post_meta($post_id, 'priority', intval($_POST['shaped_priority'] ?? 0));
        update_post_meta($post_id, 'featured_locked', isset($_POST['shaped_featured_locked']) ? '1' : '0');
        update_post_meta($post_id, 'content_locked', isset($_POST['shaped_content_locked']) ? '1' : '0');
    }

    /**
     * Quick edit box
     */
    public static function quick_edit_box(string $column_name, string $post_type): void {
        if ($post_type !== CPT::POST_TYPE || $column_name !== 'featured') {
            return;
        }
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <label>
                    <input type="checkbox" name="is_featured" value="1">
                    <span class="checkbox-title">Featured Review</span>
                </label>
                <label>
                    <span class="title">Priority</span>
                    <input type="number" name="priority" min="0" max="100" style="width:50px">
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Quick edit JavaScript
     */
    public static function quick_edit_js(): void {
        if (get_current_screen()->post_type !== CPT::POST_TYPE) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            var wp_inline_edit = inlineEditPost.edit;
            inlineEditPost.edit = function(id) {
                wp_inline_edit.apply(this, arguments);
                
                var post_id = typeof(id) == 'object' ? parseInt(this.getId(id)) : 0;
                
                if (post_id > 0) {
                    var edit_row = $('#edit-' + post_id);
                    var post_row = $('#post-' + post_id);
                    
                    var featured = post_row.find('.column-featured span[title="Featured"]').length > 0;
                    var priority = post_row.find('.column-priority').text().trim();
                    
                    edit_row.find('input[name="is_featured"]').prop('checked', featured);
                    edit_row.find('input[name="priority"]').val(priority || '0');
                }
            };
            
            $(document).on('click', '.inline-edit-save .save', function() {
                var row = $(this).closest('tr');
                var post_id = row.attr('id').replace('edit-', '');
                var featured = row.find('input[name="is_featured"]').is(':checked') ? '1' : '0';
                var priority = row.find('input[name="priority"]').val() || '0';
                
                $.post(ajaxurl, {
                    action: 'save_review_quick_edit',
                    post_id: post_id,
                    is_featured: featured,
                    priority: priority,
                    _ajax_nonce: '<?php echo wp_create_nonce("review_quick_edit"); ?>'
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX save quick edit
     */
    public static function ajax_save_quick_edit(): void {
        check_ajax_referer('review_quick_edit');

        $post_id = intval($_POST['post_id']);
        if (!current_user_can('edit_post', $post_id)) {
            wp_die();
        }

        update_post_meta($post_id, 'is_featured', sanitize_text_field($_POST['is_featured']));
        update_post_meta($post_id, 'priority', intval($_POST['priority']));

        wp_die();
    }

    /**
     * Add bulk actions
     */
    public static function add_bulk_actions(array $actions): array {
        $actions['make_featured']   = 'Make Featured';
        $actions['remove_featured'] = 'Remove Featured';
        return $actions;
    }

    /**
     * Handle bulk actions
     */
    public static function handle_bulk_actions(string $redirect, string $action, array $post_ids): string {
        if ($action === 'make_featured') {
            foreach ($post_ids as $post_id) {
                update_post_meta($post_id, 'is_featured', '1');
            }
        } elseif ($action === 'remove_featured') {
            foreach ($post_ids as $post_id) {
                update_post_meta($post_id, 'is_featured', '0');
            }
        }
        return $redirect;
    }

    /**
     * Admin styles
     */
    public static function admin_styles(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== CPT::POST_TYPE) {
            return;
        }
        ?>
        <style>
            .column-rating { width: 140px; }
            .column-provider { width: 120px; }
            .column-featured { width: 80px; text-align: center; }
            .column-priority { width: 70px; text-align: center; }
            
            .wp-list-table .column-featured,
            .wp-list-table .column-priority { text-align: center; }
            
            .wp-list-table tr:has(td.column-featured span[title="Featured"]) {
                background-color: #fffef5;
            }
            
            .wp-list-table tr:hover:has(td.column-featured span[title="Featured"]) {
                background-color: #fffdf0;
            }
        </style>
        <?php
    }

    /**
     * Show admin notice when content is auto-locked
     */
    public static function show_auto_lock_notice(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== CPT::POST_TYPE) {
            return;
        }

        // Check for auto-lock flag
        $post_id = $_GET['post'] ?? 0;
        if ($post_id && get_option('shaped_review_auto_locked_' . $post_id)) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><strong>Review content locked:</strong> This review's text has been automatically locked because you edited it. Sync will no longer overwrite your changes. You can unlock it in the Featured Review Settings if needed.</p>
            </div>
            <?php
            delete_option('shaped_review_auto_locked_' . $post_id);
        }
    }

    /**
     * Elementor featured reviews query
     * Handles the featured_reviews_query ID for Elementor Loop Grid
     */
    public static function elementor_featured_query(\WP_Query $query): void {
        $query->set('post_type', CPT::POST_TYPE);
        $query->set('post_status', 'publish');

        // Set up meta query for featured and priority
        $query->set('meta_query', [
            'relation' => 'AND',
            'featured_clause' => [
                'key'     => 'is_featured',
                'compare' => 'EXISTS',
                'type'    => 'NUMERIC'
            ],
            'priority_clause' => [
                'key'     => 'priority',
                'compare' => 'EXISTS',
                'type'    => 'NUMERIC'
            ]
        ]);

        // Force sorting: featured DESC, priority DESC, date DESC
        $query->set('orderby', [
            'featured_clause' => 'DESC',
            'priority_clause' => 'DESC',
            'date'            => 'DESC'
        ]);
    }

    /**
     * Migrate providers from meta to taxonomy (one-time)
     */
    public static function migrate_providers_to_taxonomy(): void {
        $reviews = get_posts([
            'post_type'      => CPT::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'any'
        ]);

        foreach ($reviews as $review) {
            $provider = get_post_meta($review->ID, 'provider', true);
            if ($provider) {
                $provider_slug = strtolower(str_replace(' ', '-', $provider));
                wp_set_object_terms($review->ID, $provider_slug, 'review_provider');
            }
        }
    }

    /**
     * Clean up old provider taxonomy terms (one-time migration)
     * Removes 'booking.com' and 'google-maps' terms, migrating reviews to 'booking' and 'google'
     */
    public static function cleanup_old_provider_terms(): void {
        $old_to_new_mapping = [
            'booking.com' => 'booking',
            'google-maps' => 'google',
        ];

        foreach ($old_to_new_mapping as $old_slug => $new_slug) {
            // Check if old term exists
            $old_term = get_term_by('slug', $old_slug, 'review_provider');
            if (!$old_term) {
                continue;
            }

            // Get all reviews with the old term
            $reviews = get_posts([
                'post_type'      => CPT::POST_TYPE,
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'tax_query'      => [
                    [
                        'taxonomy' => 'review_provider',
                        'field'    => 'slug',
                        'terms'    => $old_slug,
                    ],
                ],
            ]);

            // Migrate reviews to new term
            foreach ($reviews as $review) {
                // Remove old term
                wp_remove_object_terms($review->ID, $old_term->term_id, 'review_provider');

                // Add new term
                wp_set_object_terms($review->ID, $new_slug, 'review_provider', false);
            }

            // Delete the old term
            wp_delete_term($old_term->term_id, 'review_provider');
        }
    }

    /**
     * Find and remove duplicate reviews based on external_key
     * Keeps the oldest review and removes newer duplicates
     *
     * @return array Stats about duplicates removed
     */
    public static function cleanup_duplicate_reviews(): array {
        global $wpdb;

        $stats = [
            'duplicates_found' => 0,
            'reviews_removed'  => 0,
            'errors'           => 0
        ];

        // Find all reviews with external_key
        $reviews = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm.meta_value as external_key, p.post_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key = 'external_key'
            AND pm.meta_value != ''
            ORDER BY pm.meta_value, p.post_date ASC
        ", CPT::POST_TYPE));

        if (empty($reviews)) {
            return $stats;
        }

        // Group by external_key
        $grouped = [];
        foreach ($reviews as $review) {
            $key = $review->external_key;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $review;
        }

        // Find and remove duplicates (keep oldest)
        foreach ($grouped as $external_key => $review_group) {
            if (count($review_group) > 1) {
                $stats['duplicates_found']++;

                // Keep the first (oldest) review, remove the rest
                for ($i = 1; $i < count($review_group); $i++) {
                    $result = wp_delete_post($review_group[$i]->ID, true);
                    if ($result) {
                        $stats['reviews_removed']++;
                    } else {
                        $stats['errors']++;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Find duplicate reviews without removing them (for reporting)
     *
     * @return array List of duplicate groups
     */
    public static function find_duplicate_reviews(): array {
        global $wpdb;

        $duplicates = [];

        // Find all reviews with external_key
        $reviews = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, pm.meta_value as external_key, p.post_date,
                   pm2.meta_value as provider, pm3.meta_value as author_name
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'provider'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'author_name'
            WHERE p.post_type = %s
            AND pm.meta_key = 'external_key'
            AND pm.meta_value != ''
            ORDER BY pm.meta_value, p.post_date ASC
        ", CPT::POST_TYPE));

        if (empty($reviews)) {
            return $duplicates;
        }

        // Group by external_key
        $grouped = [];
        foreach ($reviews as $review) {
            $key = $review->external_key;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $review;
        }

        // Find duplicates
        foreach ($grouped as $external_key => $review_group) {
            if (count($review_group) > 1) {
                $duplicates[] = [
                    'external_key' => $external_key,
                    'count'        => count($review_group),
                    'reviews'      => $review_group
                ];
            }
        }

        return $duplicates;
    }
}
