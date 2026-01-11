<?php
/**
 * Supabase Review Sync
 * 
 * @package Shaped_Core
 * @subpackage Reviews
 */

namespace Shaped\Modules\Reviews;

if (!defined('ABSPATH')) {
    exit;
}

class Sync {

    private string $supabase_url;
    private string $supabase_key;
    private string $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        $this->supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : '';
        $this->supabase_key = defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : '';

        // Use table directly (not view) - configurable via constant or brand config
        $default_table = 'preelook_reviews_all';

        if (defined('SHAPED_REVIEWS_TABLE')) {
            $default_table = SHAPED_REVIEWS_TABLE;
        } elseif (function_exists('shaped_brand')) {
            $brand_table = shaped_brand('integrations.supabase.reviewsTable');
            if ($brand_table) {
                $default_table = $brand_table;
            }
        }

        $this->table_name = apply_filters('shaped/reviews/table_name', $default_table);

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_manual_sync']);
        add_action('admin_init', [$this, 'handle_duplicate_cleanup']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Check if credentials are configured
     */
    public function has_credentials(): bool {
        return !empty($this->supabase_url) && !empty($this->supabase_key);
    }

    /**
     * Fetch reviews from Supabase
     */
    private function fetch_from_supabase(int $offset = 0, int $limit = 100): array|false {
        if (!$this->has_credentials()) {
            $this->log_error('Missing Supabase credentials');
            return false;
        }

        $endpoint = $this->supabase_url . '/rest/v1/' . $this->table_name;

        

        // Build query with filters (PostgREST syntax)
        $query_params = [
            'select'     => '*',
            'status'     => 'eq.approved',
            'reviewText' => 'not.is.null',
            'provider'   => 'in.(booking,google,tripadvisor,expedia)',
            'or'         => '(and(provider.in.(google,tripadvisor),reviewRating.gte.4),and(provider.in.(booking,expedia),reviewRating.gte.8))',
            'order'      => 'is_featured.desc,priority.desc,reviewDate.desc',
            'offset'     => $offset,
            'limit'      => $limit,
        ];

        $url = $endpoint . '?' . http_build_query($query_params);

        // Fix the in.() syntax that http_build_query mangles
        $url = str_replace('in.%28', 'in.(', $url);
        $url = str_replace('%29', ')', $url);
        $url = str_replace('%2C', ',', $url);

        $response = wp_remote_get($url, [
            'headers' => [
                'apikey'        => $this->supabase_key,
                'Authorization' => 'Bearer ' . $this->supabase_key,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'count=exact'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $this->log_error('API request failed: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200 && $status_code !== 206) {
            $this->log_error('API returned status ' . $status_code . ': ' . wp_remote_retrieve_body($response));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('Invalid JSON response: ' . json_last_error_msg());
            return false;
        }

        return $data;
    }

    /**
     * Generate unique key for deduplication
     */
    private function generate_external_key(array $review): string {
        $components = [
            $review['provider'] ?? '',
            $review['authorName'] ?? '',
            $review['reviewDate'] ?? '',
            substr($review['reviewText'] ?? '', 0, 50)
        ];

        return md5(implode('|', $components));
    }

    /**
     * Generate review title from author and date
     */
    private function generate_review_title(array $review): string {
        $author = !empty($review['authorName']) ? $review['authorName'] : 'Guest';
        $date = !empty($review['reviewDate']) ? date('M Y', strtotime($review['reviewDate'])) : '';

        return trim($author . ($date ? ' - ' . $date : ''));
    }

    /**
     * Upsert review to WordPress
     */
    private function upsert_review(array $review): bool {
        // Skip if not approved
        if (empty($review['status']) || $review['status'] !== 'approved') {
            return false;
        }

        $external_key = $this->generate_external_key($review);

        // Check if review exists
        $existing = get_posts([
            'post_type'      => CPT::POST_TYPE,
            'meta_key'       => 'external_key',
            'meta_value'     => $external_key,
            'posts_per_page' => 1,
            'post_status'    => 'any'
        ]);

        // Store existing values before update
        $existing_featured = null;
        $existing_priority = null;
        $is_locked = false;
        $is_content_locked = false;

        if (!empty($existing)) {
            $post_id = $existing[0]->ID;
            $existing_featured = get_post_meta($post_id, 'is_featured', true);
            $existing_priority = get_post_meta($post_id, 'priority', true);
            $is_locked = get_post_meta($post_id, 'featured_locked', true) === '1';
            $is_content_locked = get_post_meta($post_id, 'content_locked', true) === '1';

            $post_data = [
                'ID'           => $post_id,
                'post_type'    => CPT::POST_TYPE,
                'post_title'   => $this->generate_review_title($review),
                'post_status'  => 'publish',
                'post_date'    => $this->format_date($review['reviewDate'])
            ];

            // Only update content if not locked
            if (!$is_content_locked) {
                $post_data['post_content'] = $review['reviewText'] ?? '';
            }

            $post_id = wp_update_post($post_data);
        } else {
            // New post - always set content
            $post_data = [
                'post_type'    => CPT::POST_TYPE,
                'post_title'   => $this->generate_review_title($review),
                'post_content' => $review['reviewText'] ?? '',
                'post_status'  => 'publish',
                'post_date'    => $this->format_date($review['reviewDate'])
            ];

            $post_id = wp_insert_post($post_data);
        }

        if (!$post_id || is_wp_error($post_id)) {
            return false;
        }

        // Update meta fields
        update_post_meta($post_id, 'external_key', $external_key);
        update_post_meta($post_id, 'provider', $review['provider'] ?? '');
        update_post_meta($post_id, 'review_date', $review['reviewDate'] ?? '');
        update_post_meta($post_id, 'review_rating', intval($review['reviewRating'] ?? 0));
        update_post_meta($post_id, 'author_name', $review['authorName'] ?? 'Guest');
        update_post_meta($post_id, 'status', $review['status'] ?? '');

        // Handle featured/priority - respect locks
        if (!$is_locked) {
            if (isset($review['is_featured'])) {
                update_post_meta($post_id, 'is_featured', $review['is_featured'] ? '1' : '0');
            } elseif ($existing_featured === null || $existing_featured === '') {
                update_post_meta($post_id, 'is_featured', '0');
            }

            if (isset($review['priority'])) {
                update_post_meta($post_id, 'priority', intval($review['priority']));
            } elseif ($existing_priority === null || $existing_priority === '') {
                update_post_meta($post_id, 'priority', 0);
            }
        }

        // Translation fields (set by external automation)
        if (!empty($review['review_text_original'])) {
            update_post_meta($post_id, 'review_text_original', $review['review_text_original']);
        }
        if (!empty($review['source_language'])) {
            update_post_meta($post_id, 'source_language', $review['source_language']);
        }
        if (!empty($review['translated_at'])) {
            update_post_meta($post_id, 'translated_at', $review['translated_at']);
        }

        // Set provider taxonomy
        $provider_slug = $this->normalize_provider_slug($review['provider'] ?? '');
        if ($provider_slug) {
            wp_set_object_terms($post_id, $provider_slug, 'review_provider');
        }

        return true;
    }

    /**
     * Normalize provider slug
     */
    private function normalize_provider_slug(string $provider): string {
        return strtolower(str_replace(' ', '-', $provider));
    }

    /**
     * Format date for WordPress
     */
    private function format_date(?string $date): string {
        if (empty($date)) {
            return current_time('mysql');
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return current_time('mysql');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Main sync function
     */
    public function sync_reviews(): array {
        $start_time = microtime(true);
        $offset = 0;
        $limit = 100;
        $total_synced = 0;
        $total_errors = 0;

        do {
            $reviews = $this->fetch_from_supabase($offset, $limit);

            if ($reviews === false) {
                $this->send_error_notification('Sync failed at offset ' . $offset);
                break;
            }

            if (empty($reviews)) {
                break;
            }

            foreach ($reviews as $review) {
                if (!empty($review['status']) && $review['status'] === 'approved') {
                    if ($this->upsert_review($review)) {
                        $total_synced++;
                    } else {
                        $total_errors++;
                    }
                }
            }

            $offset += $limit;

            // Prevent infinite loop
            if ($offset > 10000) {
                break;
            }

        } while (count($reviews) === $limit);

        $results = [
            'synced'         => $total_synced,
            'errors'         => $total_errors,
            'execution_time' => round(microtime(true) - $start_time, 2),
            'timestamp'      => current_time('mysql')
        ];

        $this->log_sync_results($results);

        if ($total_errors > 0) {
            $this->send_error_notification("Sync completed with {$total_errors} errors");
        }

        return $results;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . CPT::POST_TYPE,
            'Sync Settings',
            'Sync Settings',
            'manage_options',
            'shaped-reviews-sync',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page(): void {
        $last_sync = get_option('shaped_reviews_last_sync', []);
        $duplicates = Admin::find_duplicate_reviews();
        ?>
        <div class="wrap">
            <h1>Review Sync Settings</h1>

            <?php if (isset($_GET['synced'])): ?>
                <div class="notice notice-success">
                    <p>Manual sync completed successfully!</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['duplicates_cleaned'])): ?>
                <div class="notice notice-success">
                    <p>
                        Duplicate cleanup completed!<br>
                        <strong>Duplicate groups found:</strong> <?php echo intval($_GET['duplicates_found']); ?><br>
                        <strong>Reviews removed:</strong> <?php echo intval($_GET['reviews_removed']); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>Sync Status</h2>
                <?php if (!empty($last_sync)): ?>
                    <p><strong>Last Sync:</strong> <?php echo esc_html($last_sync['timestamp']); ?></p>
                    <p><strong>Reviews Synced:</strong> <?php echo esc_html($last_sync['synced']); ?></p>
                    <p><strong>Errors:</strong> <?php echo esc_html($last_sync['errors']); ?></p>
                    <p><strong>Execution Time:</strong> <?php echo esc_html($last_sync['execution_time']); ?>s</p>
                <?php else: ?>
                    <p>No sync has been performed yet.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Manual Sync</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('shaped_reviews_manual_sync', 'shaped_reviews_nonce'); ?>
                    <input type="hidden" name="shaped_reviews_action" value="manual_sync">
                    <p>
                        <input type="submit" class="button button-primary" value="Run Manual Sync">
                    </p>
                </form>
            </div>

            <div class="card">
                <h2>Duplicate Reviews</h2>
                <?php if (!empty($duplicates)): ?>
                    <div class="notice notice-warning inline">
                        <p><strong>Warning:</strong> Found <?php echo count($duplicates); ?> duplicate review group(s)!</p>
                    </div>

                    <table class="widefat" style="margin: 15px 0;">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Author</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($duplicates as $dup): ?>
                                <?php foreach ($dup['reviews'] as $review): ?>
                                    <tr>
                                        <td><?php echo esc_html($review->provider); ?></td>
                                        <td><?php echo esc_html($review->author_name); ?></td>
                                        <td><a href="<?php echo get_edit_post_link($review->ID); ?>"><?php echo esc_html($review->post_title); ?></a></td>
                                        <td><?php echo esc_html($review->post_date); ?></td>
                                        <td><?php echo esc_html($dup['count']); ?> duplicates</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <form method="post" action="" onsubmit="return confirm('This will permanently delete duplicate reviews. The oldest review in each group will be kept. Continue?');">
                        <?php wp_nonce_field('shaped_reviews_cleanup_duplicates', 'shaped_reviews_cleanup_nonce'); ?>
                        <input type="hidden" name="shaped_reviews_action" value="cleanup_duplicates">
                        <p>
                            <input type="submit" class="button button-secondary" value="Remove Duplicate Reviews">
                            <span class="description">This will keep the oldest review in each duplicate group and remove the rest.</span>
                        </p>
                    </form>
                <?php else: ?>
                    <p style="color: green;">✓ No duplicate reviews found</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Configuration Status</h2>
                <?php if ($this->has_credentials()): ?>
                    <p style="color: green;">✓ Supabase credentials configured</p>
                <?php else: ?>
                    <p style="color: red;">✗ Supabase credentials missing. Add to wp-config.php:</p>
                    <pre>
define('SUPABASE_URL', 'your-supabase-url');
define('SUPABASE_SERVICE_KEY', 'your-service-key');
                    </pre>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle manual sync
     */
    public function handle_manual_sync(): void {
        if (!isset($_POST['shaped_reviews_action']) || $_POST['shaped_reviews_action'] !== 'manual_sync') {
            return;
        }

        if (!wp_verify_nonce($_POST['shaped_reviews_nonce'], 'shaped_reviews_manual_sync')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $this->sync_reviews();

        wp_redirect(admin_url('edit.php?post_type=' . CPT::POST_TYPE . '&page=shaped-reviews-sync&synced=1'));
        exit;
    }

    /**
     * Handle duplicate cleanup
     */
    public function handle_duplicate_cleanup(): void {
        if (!isset($_POST['shaped_reviews_action']) || $_POST['shaped_reviews_action'] !== 'cleanup_duplicates') {
            return;
        }

        if (!wp_verify_nonce($_POST['shaped_reviews_cleanup_nonce'], 'shaped_reviews_cleanup_duplicates')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = Admin::cleanup_duplicate_reviews();

        wp_redirect(admin_url(
            'edit.php?post_type=' . CPT::POST_TYPE .
            '&page=shaped-reviews-sync' .
            '&duplicates_cleaned=1' .
            '&duplicates_found=' . $stats['duplicates_found'] .
            '&reviews_removed=' . $stats['reviews_removed']
        ));
        exit;
    }

    /**
     * Log sync results
     */
    private function log_sync_results(array $results): void {
        update_option('shaped_reviews_last_sync', $results);
    }

    /**
     * Log errors
     */
    private function log_error(string $message): void {
        error_log('[Shaped Reviews Sync] ' . $message);
    }

    /**
     * Send error notification
     */
    private function send_error_notification(string $message): void {
        $admin_email = apply_filters('shaped/admin_email', get_option('admin_email'));
        $property_name = apply_filters('shaped/property_name', get_bloginfo('name'));

        $subject = $property_name . ' - Review Sync Error';
        $body = "The review sync encountered an error:\n\n" . $message;

        wp_mail($admin_email, $subject, $body);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        register_rest_route('shaped/v1', '/sync-reviews', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_rest_sync'],
            'permission_callback' => [$this, 'verify_sync_secret']
        ]);
    }

    /**
     * Verify sync secret from header
     */
    public function verify_sync_secret(\WP_REST_Request $request): bool {
        if (!defined('SHAPED_SYNC_SECRET')) {
            return false;
        }

        $secret = $request->get_header('X-Sync-Secret');
        return $secret === SHAPED_SYNC_SECRET;
    }

    /**
     * Handle REST sync request
     */
    public function handle_rest_sync(\WP_REST_Request $request): \WP_REST_Response {
        $results = $this->sync_reviews();

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $results
        ], 200);
    }
}
