<?php
/**
 * Landing Page Room Cards Shortcode
 *
 * Displays room cards that open modals instead of navigating to room pages.
 * Optimized for conversion-focused landing pages.
 *
 * Usage: [shaped_landing_rooms]
 *
 * Attributes:
 * - limit: Number of rooms to show (default: -1 for all)
 * - ids: Comma-separated room IDs to show specific rooms
 * - orderby: Order by field (default: menu_order)
 * - order: ASC or DESC (default: ASC)
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shaped_Landing_Rooms
 */
class Shaped_Landing_Rooms {

    /**
     * Initialize
     */
    public static function init(): void {
        add_shortcode('shaped_landing_rooms', [__CLASS__, 'render_shortcode']);

        // Register AJAX handlers for room modal
        add_action('wp_ajax_shaped_load_room_modal', [__CLASS__, 'ajax_load_room_modal']);
        add_action('wp_ajax_nopriv_shaped_load_room_modal', [__CLASS__, 'ajax_load_room_modal']);

        // Enqueue landing page assets when shortcode is present
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_assets'], 30);
    }

    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_shortcode($atts): string {
        $atts = shortcode_atts([
            'limit'   => -1,
            'ids'     => '',
            'orderby' => 'menu_order',
            'order'   => 'ASC',
        ], $atts, 'shaped_landing_rooms');

        // Build query args
        $query_args = [
            'post_type'      => 'mphb_room_type',
            'post_status'    => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'orderby'        => sanitize_key($atts['orderby']),
            'order'          => strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC',
        ];

        // Filter by specific IDs if provided
        if (!empty($atts['ids'])) {
            $ids = array_filter(array_map('intval', explode(',', $atts['ids'])));
            if (!empty($ids)) {
                $query_args['post__in'] = $ids;
                $query_args['orderby'] = 'post__in';
            }
        }

        $rooms = new WP_Query($query_args);

        if (!$rooms->have_posts()) {
            return '<p class="shaped-no-rooms">' . esc_html__('No rooms available.', 'shaped') . '</p>';
        }

        // Mark that we need to load landing page assets
        self::$needs_assets = true;

        ob_start();
        ?>
        <div class="shaped-landing-rooms">
            <?php
            while ($rooms->have_posts()) {
                $rooms->the_post();
                $room_type = get_post();
                include SHAPED_DIR . 'templates/room-card-landing.php';
            }
            wp_reset_postdata();
            ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Flag to track if assets need to be loaded
     */
    private static bool $needs_assets = false;

    /**
     * Maybe enqueue landing page assets
     */
    public static function maybe_enqueue_assets(): void {
        global $post;

        // Check if shortcode is in current post content
        if ($post && has_shortcode($post->post_content, 'shaped_landing_rooms')) {
            self::enqueue_assets();
        }
    }

    /**
     * Enqueue landing page specific assets
     */
    public static function enqueue_assets(): void {
        // Landing room modal CSS
        if (file_exists(SHAPED_DIR . 'assets/css/landing-room-modal.css')) {
            wp_enqueue_style(
                'shaped-landing-room-modal',
                SHAPED_URL . 'assets/css/landing-room-modal.css',
                ['shaped-design-tokens', 'shaped-modals'],
                SHAPED_VERSION
            );
        }

        // Landing room modal JS
        if (file_exists(SHAPED_DIR . 'assets/js/landing-room-modal.js')) {
            wp_enqueue_script(
                'shaped-landing-room-modal',
                SHAPED_URL . 'assets/js/landing-room-modal.js',
                ['jquery', 'shaped-modals'],
                SHAPED_VERSION,
                true
            );

            // Localize script with AJAX URL and nonce
            wp_localize_script('shaped-landing-room-modal', 'ShapedLandingConfig', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('shaped_room_modal'),
            ]);
        }
    }

    /**
     * AJAX handler to load room modal content
     */
    public static function ajax_load_room_modal(): void {
        // Verify nonce (optional but recommended)
        // check_ajax_referer('shaped_room_modal', 'nonce');

        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;

        if (!$room_id) {
            wp_send_json_error(['message' => 'Invalid room ID']);
            return;
        }

        // Verify it's a valid room type
        $room = get_post($room_id);
        if (!$room || $room->post_type !== 'mphb_room_type' || $room->post_status !== 'publish') {
            wp_send_json_error(['message' => 'Room not found']);
            return;
        }

        // Get dates from request (passed from search form)
        $dates = [
            'check_in'  => isset($_POST['check_in']) ? sanitize_text_field($_POST['check_in']) : '',
            'check_out' => isset($_POST['check_out']) ? sanitize_text_field($_POST['check_out']) : '',
            'adults'    => isset($_POST['adults']) ? absint($_POST['adults']) : 1,
        ];

        // Render the modal template
        ob_start();
        include SHAPED_DIR . 'templates/room-modal.php';
        $content = ob_get_clean();

        wp_send_json_success([
            'content' => $content,
            'title'   => get_the_title($room_id),
        ]);
    }
}

// Initialize
Shaped_Landing_Rooms::init();
