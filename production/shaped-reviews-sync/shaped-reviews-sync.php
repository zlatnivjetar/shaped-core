<?php
/**
 * Plugin Name: Shaped Reviews Sync
 * Description: Syncs approved reviews from Supabase to WordPress CPT
 * Version: 1.0.0
 * Author: Shaped Systems
 */
 
 

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


// Define constants
define('PRS_VERSION', '1.0.0');
define('PRS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRS_PLUGIN_URL', plugin_dir_url(__FILE__));

// In preelook-reviews-sync.php
require_once PRS_PLUGIN_DIR . 'includes/review-enhancements.php';


/**
 * Register Custom Post Type for Reviews
 */
function prs_register_review_cpt() {
    $labels = [
        'name'               => 'Reviews',
        'singular_name'      => 'Review',
        'menu_name'          => 'Guest Reviews',
        'add_new'            => 'Add New Review',
        'add_new_item'       => 'Add New Review',
        'edit_item'          => 'Edit Review',
        'new_item'           => 'New Review',
        'view_item'          => 'View Review',
        'search_items'       => 'Search Reviews',
        'not_found'          => 'No reviews found',
        'not_found_in_trash' => 'No reviews found in trash'
    ];

    $args = [
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => ['slug' => 'reviews'],
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-star-filled',
        'supports'            => ['title', 'editor', 'custom-fields'],
        'show_in_rest'        => true, // Enable for Gutenberg/Elementor
    ];

    register_post_type('preelook_review', $args);
}
add_action('init', 'prs_register_review_cpt');

// Add this after the provider taxonomy registration
function prs_register_review_themes_taxonomy() {
    register_taxonomy('review_themes', 'preelook_review', [
        'labels' => [
            'name' => 'Review Themes',
            'singular_name' => 'Theme',
            'all_items' => 'All Themes',
            'edit_item' => 'Edit Theme',
            'view_item' => 'View Theme',
            'update_item' => 'Update Theme',
            'add_new_item' => 'Add New Theme',
            'new_item_name' => 'New Theme Name',
            'menu_name' => 'Themes'
        ],
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => ['slug' => 'review-theme'],
        'query_var' => true
    ]);
    
    // Create default theme terms
    $themes = [
        'easy-parking' => 'Easy Parking',
        'clean-place' => 'Clean Place',
        'good-amenities' => 'Good Amenities',
        'great-breakfast' => 'Great Breakfast',
        'mixed-reviews' => 'Mixed Reviews',
        'well-equipped' => 'Well equipped',
        'some-challenges' => 'Some Challenges'
    ];
    
    foreach ($themes as $slug => $name) {
        if (!term_exists($slug, 'review_themes')) {
            wp_insert_term($name, 'review_themes', ['slug' => $slug]);
        }
    }
}
add_action('init', 'prs_register_review_themes_taxonomy', 5);

function prs_auto_assign_themes($post_id) {
    if (get_post_type($post_id) !== 'preelook_review') return;
    
    $content = get_post_field('post_content', $post_id);
    $title = get_the_title($post_id);
    $full_text = strtolower($title . ' ' . $content);
    
    $theme_keywords = [
        'easy-parking' => ['parking', 'park', 'car space', 'garage', 'parken'],
        'clean-place' => ['clean', 'spotless', 'tidy', 'hygiene', 'pristine', 'sauber'],
        'good-amenities' => ['amenities', 'facilities', 'equipped', 'features', 'ausstattung'],
        'great-breakfast' => ['breakfast', 'morning meal', 'coffee', 'frühstück', 'dejeuner'],
        'well-equipped' => ['equipped', 'appliances', 'everything needed', 'well-stocked', 'kitchen'],
        'mixed-reviews' => ['however', 'but', 'although', 'despite', 'unfortunately', 'could be better', 'leider'],
        'some-challenges' => ['difficult', 'hard to find', 'unable', 'problem', 'issue', 'challenge', 'complicated']
    ];
    
    $assigned_themes = [];
    
    foreach ($theme_keywords as $theme_slug => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($full_text, $keyword) !== false) {
                $assigned_themes[] = $theme_slug;
                break;
            }
        }
    }
    
    if (!empty($assigned_themes)) {
        wp_set_object_terms($post_id, $assigned_themes, 'review_themes', false);
    }
}

// Run theme assignment on existing reviews
add_action('admin_init', function() {
    if (get_option('prs_themes_assigned') !== 'yes') {
        $reviews = get_posts([
            'post_type' => 'preelook_review',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);
        
        foreach ($reviews as $review) {
            prs_auto_assign_themes($review->ID);
        }
        
        update_option('prs_themes_assigned', 'yes');
    }
});


// Register taxonomy
function prs_register_provider_taxonomy() {
    register_taxonomy('review_provider', 'preelook_review', [
        'labels' => [
            'name' => 'Providers',
            'singular_name' => 'Provider',
            'all_items' => 'All Providers',
            'edit_item' => 'Edit Provider',
            'view_item' => 'View Provider',
            'update_item' => 'Update Provider',
            'add_new_item' => 'Add New Provider',
            'new_item_name' => 'New Provider Name',
            'menu_name' => 'Providers'
        ],
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => ['slug' => 'review-provider'],
        'query_var' => true
    ]);
    
    // Create default terms if they don't exist
    $providers = [
        'booking' => 'Booking',
        'google-maps' => 'Google',
        'tripadvisor' => 'TripAdvisor',
        'expedia' => 'Expedia'
    ];
    
    foreach ($providers as $slug => $name) {
        if (!term_exists($slug, 'review_provider')) {
            wp_insert_term($name, 'review_provider', ['slug' => $slug]);
        }
    }
}
add_action('init', 'prs_register_provider_taxonomy', 5);

// Run migration - add this as a one-time function
function prs_migrate_providers_to_taxonomy() {
    $reviews = get_posts([
        'post_type' => 'preelook_review',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    foreach ($reviews as $review) {
        $provider = get_post_meta($review->ID, 'provider', true);
        if ($provider) {
            // Normalize provider name to slug
            $provider_slug = strtolower(str_replace(' ', '-', $provider));
            if ($provider_slug === 'booking.com') $provider_slug = 'booking';
            if ($provider_slug === 'google') $provider_slug = 'google-maps';
            
            wp_set_object_terms($review->ID, $provider_slug, 'review_provider');
        }
    }
}

// Run migration once - trigger this manually or on plugin activation
add_action('admin_init', function() {
    if (get_option('prs_taxonomy_migrated') !== 'yes') {
        prs_migrate_providers_to_taxonomy();
        update_option('prs_taxonomy_migrated', 'yes');
    }
});


/**
 * Register meta fields for reviews
 */
 
function prs_register_review_meta() {
    $meta_fields = [
        'external_key' => 'string',
        'provider' => 'string',
        'review_date' => 'string',
        'review_rating' => 'number',
        'author_name' => 'string',
        'is_featured' => 'boolean',
        'priority' => 'number'
    ];

    foreach ($meta_fields as $key => $type) {
        register_post_meta('preelook_review', $key, [
            'type' => $type,
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => $type === 'number' ? 'absint' : 
            ($type === 'boolean' ? 'rest_sanitize_boolean' : 'sanitize_text_field')
        ]);
    }
}

add_action('init', 'prs_register_review_meta');



/**
 * Main sync class
 */
class PRS_Supabase_Sync {
    
    private $supabase_url;
    private $supabase_key;
    private $table_name = 'preelook_reviews';
    
    public function __construct() {
        // Get credentials from wp-config.php
        $this->supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : '';
        $this->supabase_key = defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : '';
        

        // Admin actions
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_manual_sync']);
    }
    
    /**
     * Fetch reviews from Supabase
     */
    private function fetch_from_supabase($offset = 0, $limit = 100) {
        if (empty($this->supabase_url) || empty($this->supabase_key)) {
            $this->log_error('Missing Supabase credentials');
            return false;
        }
        
        $endpoint = $this->supabase_url . '/rest/v1/' . $this->table_name;
        $params = [
            'select' => '*',
            'offset' => $offset,
            'limit' => $limit,
            'order' => 'reviewDate.desc'
        ];
        
        $url = $endpoint . '?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'apikey' => $this->supabase_key,
                'Authorization' => 'Bearer ' . $this->supabase_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            $this->log_error('API request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('Invalid JSON response');
            return false;
        }
        
        return $data;
    }
    
    /**
     * Generate unique key for deduplication
     */
    private function generate_external_key($review) {
        // Create stable key from provider + identifying info
        $components = [
            $review['provider'] ?? '',
            $review['authorName'] ?? '',
            $review['reviewDate'] ?? '',
            substr($review['reviewText'] ?? '', 0, 50)
        ];
        
        return md5(implode('|', $components));
    }
    
    /**
     * Upsert review to WordPress
     */
private function upsert_review($review) {
    // Skip if not approved
    if (empty($review['status']) || $review['status'] !== 'approved') {
        return false;
    }
    
    $external_key = $this->generate_external_key($review);
    
    // Check if review exists
    $existing = get_posts([
        'post_type' => 'preelook_review',
        'meta_key' => 'external_key',
        'meta_value' => $external_key,
        'posts_per_page' => 1,
        'post_status' => 'any'
    ]);
    
    // CRITICAL: Store existing values BEFORE any updates
    $existing_featured = null;
    $existing_priority = null;
    
    if (!empty($existing)) {
        $post_id = $existing[0]->ID;
        $existing_featured = get_post_meta($post_id, 'is_featured', true);
        $existing_priority = get_post_meta($post_id, 'priority', true);
        
        // Update existing post
        $post_data = [
            'ID' => $post_id,
            'post_type' => 'preelook_review',
            'post_title' => !empty($review['reviewTitle']) ? $review['reviewTitle'] : 'Guest Review',
            'post_content' => $review['reviewText'] ?? '',
            'post_status' => 'publish',
            'post_date' => $this->format_date($review['reviewDate'])
        ];
        
        $post_id = wp_update_post($post_data);
    } else {
        // Create new post
        $post_data = [
            'post_type' => 'preelook_review',
            'post_title' => !empty($review['reviewTitle']) ? $review['reviewTitle'] : 'Guest Review',
            'post_content' => $review['reviewText'] ?? '',
            'post_status' => 'publish',
            'post_date' => $this->format_date($review['reviewDate'])
        ];
        
        $post_id = wp_insert_post($post_data);
    }
    
    if ($post_id && !is_wp_error($post_id)) {
        // Update meta fields from Supabase
        update_post_meta($post_id, 'external_key', $external_key);
        update_post_meta($post_id, 'provider', $review['provider'] ?? '');
        update_post_meta($post_id, 'review_date', $review['reviewDate'] ?? '');
        update_post_meta($post_id, 'review_rating', intval($review['reviewRating'] ?? 0));
        update_post_meta($post_id, 'author_name', $review['authorName'] ?? 'Guest');
        update_post_meta($post_id, 'status', $review['status'] ?? '');
        
        // CRITICAL: Only update featured/priority if:
        // 1. They exist in Supabase data (override local)
        // 2. OR it's a new review (set defaults)
        // 3. OTHERWISE preserve existing values
        
        // Featured field
        if (isset($review['is_featured'])) {
            // Supabase has this field - use it
            update_post_meta($post_id, 'is_featured', $review['is_featured'] ? '1' : '0');
        } elseif ($existing_featured === null || $existing_featured === '') {
            // New review or never set - default to 0
            update_post_meta($post_id, 'is_featured', '0');
        }
        // If existing_featured has a value and Supabase doesn't have the field, 
        // we don't update it at all - it keeps its current value
        
        // Priority field
        if (isset($review['priority'])) {
            // Supabase has this field - use it
            update_post_meta($post_id, 'priority', intval($review['priority']));
        } elseif ($existing_priority === null || $existing_priority === '') {
            // New review or never set - default to 0
            update_post_meta($post_id, 'priority', 0);
        }
        // If existing_priority has a value and Supabase doesn't have the field,
        // we don't update it at all - it keeps its current value
        
        return true;
    }
    
    return false;
}

    /**
     * Format date for WordPress
     */
    private function format_date($date) {
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
    public function sync_reviews() {
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
                break; // No more reviews
            }
            
            foreach ($reviews as $review) {
                // Only process approved reviews
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
        
        $execution_time = microtime(true) - $start_time;
        
        // Log results
        $this->log_sync_results([
            'synced' => $total_synced,
            'errors' => $total_errors,
            'execution_time' => round($execution_time, 2),
            'timestamp' => current_time('mysql')
        ]);
        
        // Send notification if errors
        if ($total_errors > 0) {
            $this->send_error_notification("Sync completed with {$total_errors} errors");
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=preelook_review',
            'Sync Settings',
            'Sync Settings',
            'manage_options',
            'prs-sync-settings',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $last_sync = get_option('prs_last_sync', []);
        ?>
        <div class="wrap">
            <h1>Review Sync Settings</h1>
            
            <?php if (isset($_GET['synced'])): ?>
                <div class="notice notice-success">
                    <p>Manual sync completed successfully!</p>
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
                    <?php wp_nonce_field('prs_manual_sync', 'prs_nonce'); ?>
                    <input type="hidden" name="prs_action" value="manual_sync">
                    <p>
                        <input type="submit" class="button button-primary" value="Run Manual Sync">
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2>Configuration Status</h2>
                <?php if (!empty($this->supabase_url) && !empty($this->supabase_key)): ?>
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
    public function handle_manual_sync() {
        if (!isset($_POST['prs_action']) || $_POST['prs_action'] !== 'manual_sync') {
            return;
        }
        
        if (!wp_verify_nonce($_POST['prs_nonce'], 'prs_manual_sync')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $this->sync_reviews();
        
        wp_redirect(admin_url('edit.php?post_type=preelook_review&page=prs-sync-settings&synced=1'));
        exit;
    }
    
    /**
     * Log sync results
     */
    private function log_sync_results($results) {
        update_option('prs_last_sync', $results);
    }
    
    /**
     * Log errors
     */
    private function log_error($message) {
        error_log('[PRS Sync Error] ' . $message);
    }
    
    /**
     * Send error notification
     */
    private function send_error_notification($message) {
        $admin_email = get_option('admin_email');
        $subject = 'Preelook Review Sync Error';
        $body = "The review sync encountered an error:\n\n" . $message;
        
        wp_mail($admin_email, $subject, $body);
    }
}

// Add admin columns for featured status, rating, and provider
add_filter('manage_preelook_review_posts_columns', function($columns) {
    $new_columns = [];
    foreach($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['rating'] = 'Rating';
            $new_columns['provider'] = 'Provider';
            $new_columns['featured'] = 'Featured';
            $new_columns['priority'] = 'Priority';
        }
    }
    return $new_columns;
});

add_action('manage_preelook_review_posts_custom_column', function($column, $post_id) {
    switch($column) {
        case 'rating':
            $rating = get_post_meta($post_id, 'review_rating', true);
            $provider = get_post_meta($post_id, 'provider', true);
            
            // Determine if it's a 5 or 10 scale based on provider
            $is_five_scale = in_array(strtolower($provider), ['google-maps', 'google', 'tripadvisor']);
            
            if ($rating) {
                // Display stars
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
                echo ' <small style="color:#666;">(' . $display . ')</small>';
                echo '</div>';
            }  
              
            
            break;
            
        case 'provider':
            $provider = get_post_meta($post_id, 'provider', true);
            
            // Provider badges with colors
            $provider_configs = [
                'booking' => ['name' => 'Booking.com', 'color' => '#003580'],
                'booking.com' => ['name' => 'Booking.com', 'color' => '#003580'],
                'google-maps' => ['name' => 'Google Maps', 'color' => '#4285f4'],
                'google' => ['name' => 'Google Maps', 'color' => '#4285f4'],
                'tripadvisor' => ['name' => 'TripAdvisor', 'color' => '#00af87'],
                'expedia' => ['name' => 'Expedia', 'color' => '#ffda00', 'text' => '#000']
            ];
            
            $provider_lower = strtolower($provider);
            $config = $provider_configs[$provider_lower] ?? [
                'name' => ucfirst($provider),
                'color' => '#666'
            ];
            
            $text_color = $config['text'] ?? '#fff';
            
            if ($provider) {
                printf(
                    '<span style="display:inline-block; padding:2px 8px; border-radius:3px; 
                           background:%s; color:%s; font-size:11px; font-weight:600;">%s</span>',
                    esc_attr($config['color']),
                    esc_attr($text_color),
                    esc_html($config['name'])
                );
            } else {
                echo '—';
            }
            break;
            
        case 'featured':
            $is_featured = get_post_meta($post_id, 'is_featured', true);
            if ($is_featured) {
                echo '<span style="color:#D1AF5D; font-size:18px;" title="Featured">⭐</span>';
            } else {
                echo '<span style="color:#ddd;">—</span>';
            }
            break;
            
        case 'priority':
            $priority = get_post_meta($post_id, 'priority', true);
            $is_featured = get_post_meta($post_id, 'is_featured', true);
            
            if ($is_featured && $priority > 0) {
                echo '<strong style="color:#D1AF5D;">' . intval($priority) . '</strong>';
            } elseif ($priority > 0) {
                echo intval($priority);
            } else {
                echo '<span style="color:#ddd;">0</span>';
            }
            break;
    }
}, 10, 2);

// Add JavaScript for Quick Edit functionality
add_action('admin_footer-edit.php', function() {
    if (get_current_screen()->post_type !== 'preelook_review') return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Store original values when Quick Edit opens
        var wp_inline_edit = inlineEditPost.edit;
        inlineEditPost.edit = function(id) {
            wp_inline_edit.apply(this, arguments);
            
            var post_id = 0;
            if (typeof(id) == 'object') {
                post_id = parseInt(this.getId(id));
            }
            
            if (post_id > 0) {
                // Get the row data
                var edit_row = $('#edit-' + post_id);
                var post_row = $('#post-' + post_id);
                
                // Get current values from the post row
                var featured = post_row.find('.column-featured span[title="Featured"]').length > 0;
                var priority = post_row.find('.column-priority').text().trim();
                
                // Set values in Quick Edit form
                edit_row.find('input[name="is_featured"]').prop('checked', featured);
                edit_row.find('input[name="priority"]').val(priority || '0');
            }
        };
        
        // Save Quick Edit data via AJAX
        $(document).on('click', '.inline-edit-save .save', function() {
            var row = $(this).closest('tr');
            var post_id = row.attr('id').replace('edit-', '');
            var featured = row.find('input[name="is_featured"]').is(':checked') ? '1' : '0';
            var priority = row.find('input[name="priority"]').val() || '0';
            
            // Send AJAX request to save meta
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
});

// Handle AJAX save for Quick Edit
add_action('wp_ajax_save_review_quick_edit', function() {
    check_ajax_referer('review_quick_edit');
    
    $post_id = intval($_POST['post_id']);
    if (!current_user_can('edit_post', $post_id)) {
        wp_die();
    }
    
    update_post_meta($post_id, 'is_featured', $_POST['is_featured']);
    update_post_meta($post_id, 'priority', intval($_POST['priority']));
    
    wp_die();
});

// Alternative: Hook into save_post for Quick Edit
add_action('save_post_preelook_review', function($post_id, $post, $update) {
    // Handle Quick Edit saves
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    // Check if this is a Quick Edit save (has our custom fields in POST)
    if (isset($_POST['is_featured'])) {
        update_post_meta($post_id, 'is_featured', $_POST['is_featured'] === '1' ? '1' : '0');
    }
    
    if (isset($_POST['priority'])) {
        update_post_meta($post_id, 'priority', intval($_POST['priority']));
    }
}, 10, 3);

// Make columns sortable
add_filter('manage_edit-preelook_review_sortable_columns', function($columns) {
    $columns['rating'] = 'rating';
    $columns['provider'] = 'provider';
    $columns['featured'] = 'featured';
    $columns['priority'] = 'priority';
    return $columns;
});

// Handle sorting
add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if ($query->get('post_type') !== 'preelook_review') {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    switch($orderby) {
        case 'rating':
            $query->set('meta_key', 'review_rating');
            $query->set('orderby', 'meta_value_num');
            break;
            
        case 'provider':
            $query->set('meta_key', 'provider');
            $query->set('orderby', 'meta_value');
            break;
            
        case 'featured':
            $query->set('meta_key', 'is_featured');
            $query->set('orderby', 'meta_value');
            break;
            
        case 'priority':
            $query->set('meta_key', 'priority');
            $query->set('orderby', 'meta_value_num');
            break;
    }
});

// Add filters dropdown for providers
add_action('restrict_manage_posts', function($post_type) {
    if ($post_type !== 'preelook_review') {
        return;
    }
    
    global $wpdb;
    
    // Get unique providers
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
            <option value="<?php echo esc_attr($provider); ?>" 
                    <?php selected($current_provider, $provider); ?>>
                <?php echo esc_html(ucfirst($provider)); ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <select name="featured_filter">
        <option value="">All Reviews</option>
        <option value="featured" <?php selected(($_GET['featured_filter'] ?? ''), 'featured'); ?>>
            Featured Only
        </option>
        <option value="not_featured" <?php selected(($_GET['featured_filter'] ?? ''), 'not_featured'); ?>>
            Not Featured
        </option>
    </select>
    <?php
});

// Apply filters
add_filter('parse_query', function($query) {
    global $pagenow;
    
    if (!is_admin() || $pagenow !== 'edit.php' || 
        !isset($_GET['post_type']) || $_GET['post_type'] !== 'preelook_review') {
        return;
    }
    
    // Provider filter
    if (!empty($_GET['provider_filter'])) {
        $query->query_vars['meta_query'][] = [
            'key' => 'provider',
            'value' => $_GET['provider_filter'],
            'compare' => '='
        ];
    }
    
    // Featured filter
    if (!empty($_GET['featured_filter'])) {
        if ($_GET['featured_filter'] === 'featured') {
            $query->query_vars['meta_query'][] = [
                'key' => 'is_featured',
                'value' => '1',
                'compare' => '='
            ];
        } elseif ($_GET['featured_filter'] === 'not_featured') {
            $query->query_vars['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => 'is_featured',
                    'value' => '0',
                    'compare' => '='
                ],
                [
                    'key' => 'is_featured',
                    'compare' => 'NOT EXISTS'
                ]
            ];
        }
    }
});

// Optional: Add custom CSS for better admin styling
add_action('admin_head', function() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'preelook_review') {
        ?>
        <style>
            .column-rating { width: 140px; }
            .column-provider { width: 120px; }
            .column-featured { width: 80px; text-align: center; }
            .column-priority { width: 70px; text-align: center; }
            
            /* Make the admin table look better */
            .wp-list-table .column-featured,
            .wp-list-table .column-priority {
                text-align: center;
            }
            
            /* Highlight featured rows */
            .wp-list-table tr:has(td.column-featured span[title="Featured"]) {
                background-color: #fffef5;
            }
            
            .wp-list-table tr:hover:has(td.column-featured span[title="Featured"]) {
                background-color: #fffdf0;
            }
        </style>
        <?php
    }
});

// Add quick edit for featured status
add_action('quick_edit_custom_box', function($column_name, $post_type) {
    if ($post_type !== 'preelook_review') return;
    
    if ($column_name === 'featured') {
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
}, 10, 2);

// Add meta box for featured settings
add_action('add_meta_boxes', function() {
    add_meta_box(
        'prs_featured_settings',
        'Featured Review Settings',
        'prs_render_featured_meta_box',
        'preelook_review',
        'side',
        'high'
    );
});


// Add to the meta box rendering function
function prs_render_featured_meta_box($post) {
    $is_featured = get_post_meta($post->ID, 'is_featured', true);
    $priority = get_post_meta($post->ID, 'priority', true);
    $is_locked = get_post_meta($post->ID, 'featured_locked', true);
    wp_nonce_field('prs_featured_nonce', 'prs_featured_nonce');
    ?>
    <p>
        <label>
            <input type="checkbox" name="prs_is_featured" value="1" <?php checked($is_featured, '1'); ?>>
            <strong>Feature this review</strong>
        </label>
    </p>
    <p>
        <label for="prs_priority">Priority (0-100):</label><br>
        <input type="number" id="prs_priority" name="prs_priority" 
               value="<?php echo esc_attr($priority ?: '0'); ?>" 
               min="0" max="100" style="width:100%">
        <small>Higher numbers appear first among featured reviews</small>
    </p>
    <p>
        <label>
            <input type="checkbox" name="prs_featured_locked" value="1" <?php checked($is_locked, '1'); ?>>
            <strong>Lock featured settings</strong>
        </label>
        <small>Prevents sync from overwriting these values</small>
    </p>
    <?php
}

// Update save function
add_action('save_post_preelook_review', function($post_id) {
    if (!isset($_POST['prs_featured_nonce']) || 
        !wp_verify_nonce($_POST['prs_featured_nonce'], 'prs_featured_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    update_post_meta($post_id, 'is_featured', isset($_POST['prs_is_featured']) ? '1' : '0');
    update_post_meta($post_id, 'priority', intval($_POST['prs_priority'] ?? 0));
    update_post_meta($post_id, 'featured_locked', isset($_POST['prs_featured_locked']) ? '1' : '0');
});



// Add bulk action for featuring/unfeaturing
add_filter('bulk_actions-edit-preelook_review', function($actions) {
    $actions['make_featured'] = 'Make Featured';
    $actions['remove_featured'] = 'Remove Featured';
    return $actions;
});

add_filter('handle_bulk_actions-edit-preelook_review', function($redirect, $action, $post_ids) {
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
}, 10, 3);

// Register custom query for Elementor Loop Grid
add_action('elementor/query/featured_reviews_query', function($query) {
    // Ensure we're querying the right post type
    $query->set('post_type', 'preelook_review');
    $query->set('post_status', 'publish');
    
    // Set up meta query for sorting
    $query->set('meta_query', [
        'relation' => 'AND',
        'featured_clause' => [
            'key' => 'is_featured',
            'compare' => 'EXISTS',
            'type' => 'NUMERIC'
        ],
        'priority_clause' => [
            'key' => 'priority',
            'compare' => 'EXISTS',
            'type' => 'NUMERIC'
        ]
    ]);
    
    // Set up complex ordering
    $query->set('orderby', [
        'featured_clause' => 'DESC',  // Featured (1) before non-featured (0)
        'priority_clause' => 'DESC',  // Higher priority first
        'date' => 'DESC'              // Then by date
    ]);
});

// Initialize sync class
new PRS_Supabase_Sync();


