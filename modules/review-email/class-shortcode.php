<?php
/**
 * Leave Review Shortcode
 *
 * Displays a review form where guests can rate their stay (1-10) and leave feedback.
 * If rating >= 8: Review is published as "Direct" provider
 * If rating < 8: Guest is prompted to explain what they didn't like
 *
 * @package Shaped_Core
 * @subpackage ReviewEmail
 */

namespace Shaped\Modules\ReviewEmail;

if (!defined('ABSPATH')) {
    exit;
}

class Shortcode {

    /**
     * Minimum rating to publish review publicly
     */
    const PUBLISH_THRESHOLD = 8;

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('shaped_leave_review', [$this, 'render']);
        add_action('wp_ajax_shaped_submit_review', [$this, 'handle_submit']);
        add_action('wp_ajax_nopriv_shaped_submit_review', [$this, 'handle_submit']);
        add_action('wp_ajax_shaped_submit_feedback', [$this, 'handle_feedback']);
        add_action('wp_ajax_nopriv_shaped_submit_feedback', [$this, 'handle_feedback']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue assets when shortcode is present
     */
    public function enqueue_assets(): void {
        global $post;

        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'shaped_leave_review')) {
            return;
        }

        wp_enqueue_style(
            'shaped-leave-review',
            plugins_url('assets/style.css', __FILE__),
            [],
            SHAPED_REVIEW_EMAIL_VERSION
        );

        wp_enqueue_script(
            'shaped-leave-review',
            plugins_url('assets/script.js', __FILE__),
            ['jquery'],
            SHAPED_REVIEW_EMAIL_VERSION,
            true
        );

        wp_localize_script('shaped-leave-review', 'shapedReview', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('shaped_review_nonce'),
            'threshold' => self::PUBLISH_THRESHOLD,
        ]);
    }

    /**
     * Render the shortcode
     *
     * @return string
     */
    public function render(): string {
        // Check for required parameters
        if (!isset($_GET['booking_id'], $_GET['token'])) {
            return $this->render_error('Invalid review link. Please use the link from your email.');
        }

        $booking_id = absint($_GET['booking_id']);
        $token = sanitize_text_field($_GET['token']);

        // Verify token
        if (!Scheduler::verify_token($booking_id, $token)) {
            return $this->render_error('Invalid or expired review link.');
        }

        // Check if already reviewed
        if ($this->has_reviewed($booking_id)) {
            return $this->render_already_reviewed();
        }

        // Get booking details for display
        $booking = \MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) {
            return $this->render_error('Booking not found.');
        }

        $customer = $booking->getCustomer();
        $room_type_ids = $booking->getReservedRoomTypeIds();
        $room_names = array_map('get_the_title', $room_type_ids);

        // Get guest name - with fallback to meta
        $guest_name = $customer ? $customer->getFirstName() : null;
        if (!$guest_name) {
            $guest_name = get_post_meta($booking_id, '_mphb_first_name', true) ?: 'Guest';
        }

        // Get dates - fallback to meta if MPHB object doesn't have them
        $check_in_date = $booking->getCheckInDate();
        $check_out_date = $booking->getCheckOutDate();

        if (!$check_in_date) {
            $check_in_meta = get_post_meta($booking_id, '_mphb_check_in_date', true);
            $check_in_date = $check_in_meta ? \DateTime::createFromFormat('Y-m-d', $check_in_meta) : null;
        }
        if (!$check_out_date) {
            $check_out_meta = get_post_meta($booking_id, '_mphb_check_out_date', true);
            $check_out_date = $check_out_meta ? \DateTime::createFromFormat('Y-m-d', $check_out_meta) : null;
        }

        return $this->render_form([
            'booking_id'  => $booking_id,
            'token'       => $token,
            'guest_name'  => $guest_name,
            'check_in'    => $check_in_date ? $check_in_date->format('F j, Y') : 'N/A',
            'check_out'   => $check_out_date ? $check_out_date->format('F j, Y') : 'N/A',
            'room_list'   => implode(', ', $room_names) ?: 'Accommodation',
        ]);
    }

    /**
     * Render the review form
     *
     * @param array $data Form data
     * @return string
     */
    private function render_form(array $data): string {
        $company_name = shaped_email_config('company_name', 'our property');
        $primary = function_exists('shaped_brand_color') ? shaped_brand_color('primary') : '#E2BD27';
        $text_primary = function_exists('shaped_brand_color') ? shaped_brand_color('textPrimary') : '#26272C';
        $text_muted = function_exists('shaped_brand_color') ? shaped_brand_color('textMuted') : '#666666';
        $success = function_exists('shaped_brand_color') ? shaped_brand_color('success') : '#22c55e';
        $error = function_exists('shaped_brand_color') ? shaped_brand_color('error') : '#ef4444';

        ob_start();
        ?>
        <div class="shaped-review-container" data-booking-id="<?php echo esc_attr($data['booking_id']); ?>" data-token="<?php echo esc_attr($data['token']); ?>">
            <!-- Header -->
            <div class="shaped-review-header">
                <h1>How was your stay?</h1>
                <p>We'd love to hear about your experience at <?php echo esc_html($company_name); ?></p>
            </div>

            <!-- Stay Summary -->
            <div class="shaped-review-summary">
                <div class="summary-row">
                    <span class="label">Guest:</span>
                    <span class="value"><?php echo esc_html($data['guest_name']); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Stay:</span>
                    <span class="value"><?php echo esc_html($data['check_in']); ?> - <?php echo esc_html($data['check_out']); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Accommodation:</span>
                    <span class="value"><?php echo esc_html($data['room_list']); ?></span>
                </div>
            </div>

            <!-- Rating Form -->
            <form class="shaped-review-form" id="shaped-review-form">
                <input type="hidden" name="booking_id" value="<?php echo esc_attr($data['booking_id']); ?>">
                <input type="hidden" name="token" value="<?php echo esc_attr($data['token']); ?>">

                <!-- Rating Selection -->
                <div class="shaped-rating-section">
                    <label>Rate your experience</label>
                    <p class="rating-help">Select a rating from 1 to 10</p>

                    <div class="shaped-rating-buttons">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <button type="button" class="rating-btn" data-rating="<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="shaped-rating-input" value="" required>
                    <div class="rating-label">
                        <span id="rating-description"></span>
                    </div>
                </div>

                <!-- Comment Section -->
                <div class="shaped-comment-section">
                    <label for="shaped-review-comment">Tell us about your experience</label>
                    <textarea
                        name="comment"
                        id="shaped-review-comment"
                        rows="5"
                        placeholder="What did you enjoy most about your stay?"
                    ></textarea>
                </div>

                <!-- Guest Name (optional override) -->
                <div class="shaped-name-section">
                    <label for="shaped-review-name">Your name</label>
                    <input
                        type="text"
                        name="author_name"
                        id="shaped-review-name"
                        value="<?php echo esc_attr($data['guest_name']); ?>"
                        placeholder="Your name"
                    >
                </div>

                <!-- Submit Button -->
                <div class="shaped-submit-section">
                    <button type="submit" class="shaped-submit-btn" disabled>
                        <span class="btn-text">Submit Review</span>
                        <span class="btn-loading" style="display: none;">Submitting...</span>
                    </button>
                </div>

                <div class="shaped-form-error" style="display: none;"></div>
            </form>

            <!-- Feedback Form (shown for low ratings) -->
            <div class="shaped-feedback-section" id="shaped-feedback-section" style="display: none;">
                <div class="feedback-header">
                    <h2>We're Sorry to Hear That</h2>
                    <p>We appreciate your honest feedback. Could you tell us more about what could have been better?</p>
                </div>

                <form class="shaped-feedback-form" id="shaped-feedback-form">
                    <input type="hidden" name="booking_id" value="<?php echo esc_attr($data['booking_id']); ?>">
                    <input type="hidden" name="token" value="<?php echo esc_attr($data['token']); ?>">
                    <input type="hidden" name="original_rating" id="shaped-original-rating" value="">

                    <div class="feedback-options">
                        <label class="feedback-checkbox">
                            <input type="checkbox" name="issues[]" value="cleanliness">
                            <span>Cleanliness</span>
                        </label>
                        <label class="feedback-checkbox">
                            <input type="checkbox" name="issues[]" value="comfort">
                            <span>Comfort</span>
                        </label>
                        <label class="feedback-checkbox">
                            <input type="checkbox" name="issues[]" value="amenities">
                            <span>Amenities</span>
                        </label>
                        <label class="feedback-checkbox">
                            <input type="checkbox" name="issues[]" value="staff">
                            <span>Staff & Service</span>
                        </label>
                        <label class="feedback-checkbox">
                            <input type="checkbox" name="issues[]" value="location">
                            <span>Location</span>
                        </label>
                        <label class="feedback-checkbox">
                            <input type="checkbox" name="issues[]" value="value">
                            <span>Value for Money</span>
                        </label>
                        <label class="feedback-checkbox">
                            <input type="checkbox" name="issues[]" value="noise">
                            <span>Noise Level</span>
                        </label>
                        <label class="feedback-checkbox">
                            <input type="checkbox" name="issues[]" value="other">
                            <span>Other</span>
                        </label>
                    </div>

                    <div class="feedback-details">
                        <label for="shaped-feedback-details">Please share any additional details</label>
                        <textarea
                            name="details"
                            id="shaped-feedback-details"
                            rows="4"
                            placeholder="Help us understand what went wrong so we can improve..."
                        ></textarea>
                    </div>

                    <div class="shaped-submit-section">
                        <button type="submit" class="shaped-submit-btn shaped-submit-feedback">
                            <span class="btn-text">Submit Feedback</span>
                            <span class="btn-loading" style="display: none;">Submitting...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Thank You Message -->
            <div class="shaped-thank-you-section" id="shaped-thank-you-section" style="display: none;">
                <div class="thank-you-icon">&#10003;</div>
                <h2>Thank You!</h2>
                <p id="thank-you-message">Your review has been submitted successfully.</p>
                <p class="thank-you-subtext">We appreciate you taking the time to share your experience.</p>
            </div>
        </div>

        <style>
            :root {
                --shaped-primary: <?php echo esc_attr($primary); ?>;
                --shaped-text-primary: <?php echo esc_attr($text_primary); ?>;
                --shaped-text-muted: <?php echo esc_attr($text_muted); ?>;
                --shaped-success: <?php echo esc_attr($success); ?>;
                --shaped-error: <?php echo esc_attr($error); ?>;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Render error message
     *
     * @param string $message Error message
     * @return string
     */
    private function render_error(string $message): string {
        $error = function_exists('shaped_brand_color') ? shaped_brand_color('error') : '#ef4444';

        return sprintf(
            '<div class="shaped-review-container shaped-review-error">
                <div class="error-icon">&#10007;</div>
                <h2>Unable to Load Review Form</h2>
                <p>%s</p>
            </div>',
            esc_html($message)
        );
    }

    /**
     * Render already reviewed message
     *
     * @return string
     */
    private function render_already_reviewed(): string {
        $success = function_exists('shaped_brand_color') ? shaped_brand_color('success') : '#22c55e';

        return sprintf(
            '<div class="shaped-review-container shaped-review-success">
                <div class="success-icon" style="color: #ffffff;</div>
                <h2>Review already submitted</h2>
                <p>You have already submitted a review for this stay. Thank you for your feedback!</p>
            </div>',
            esc_attr($success)
        );
    }

    /**
     * Check if booking has been reviewed (or review is in progress)
     *
     * @param int $booking_id Booking ID
     * @return bool
     */
    private function has_reviewed(int $booking_id): bool {
        // Check if review was submitted (either published or feedback)
        if (get_post_meta($booking_id, '_shaped_review_submitted', true)) {
            return true;
        }
        // Also check if review process was started (low rating submitted, awaiting feedback)
        if (get_post_meta($booking_id, '_shaped_review_started', true)) {
            return true;
        }
        return false;
    }

    /**
     * Handle review submission (AJAX)
     */
    public function handle_submit(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'shaped_review_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        $booking_id = absint($_POST['booking_id'] ?? 0);
        $token = sanitize_text_field($_POST['token'] ?? '');
        $rating = absint($_POST['rating'] ?? 0);
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');
        $author_name = sanitize_text_field($_POST['author_name'] ?? '');

        // Validate
        if (!$booking_id || !$token || !$rating) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        // Verify token
        if (!Scheduler::verify_token($booking_id, $token)) {
            wp_send_json_error(['message' => 'Invalid token']);
        }

        // Check if already reviewed
        if ($this->has_reviewed($booking_id)) {
            wp_send_json_error(['message' => 'Already reviewed']);
        }

        // Validate rating range
        if ($rating < 1 || $rating > 10) {
            wp_send_json_error(['message' => 'Invalid rating']);
        }

        // If rating is below threshold, prompt for feedback
        if ($rating < self::PUBLISH_THRESHOLD) {
            // Mark review as started to prevent re-access
            update_post_meta($booking_id, '_shaped_review_started', current_time('mysql'));
            update_post_meta($booking_id, '_shaped_review_initial_rating', $rating);
            update_post_meta($booking_id, '_shaped_review_initial_comment', $comment);

            wp_send_json_success([
                'action'  => 'feedback',
                'rating'  => $rating,
                'comment' => $comment, // Pass comment to pre-populate feedback form
                'message' => 'Please help us understand what could have been better.',
            ]);
            return;
        }

        // Rating is 8 or above - create published review
        $result = $this->create_review($booking_id, $rating, $comment, $author_name);

        if ($result) {
            update_post_meta($booking_id, '_shaped_review_submitted', current_time('mysql'));
            update_post_meta($booking_id, '_shaped_review_id', $result);

            wp_send_json_success([
                'action'  => 'published',
                'message' => 'Thank you! Your review has been published.',
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to save review']);
        }
    }

    /**
     * Handle feedback submission for low ratings (AJAX)
     */
    public function handle_feedback(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'shaped_review_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        $booking_id = absint($_POST['booking_id'] ?? 0);
        $token = sanitize_text_field($_POST['token'] ?? '');
        $rating = absint($_POST['original_rating'] ?? 0);
        $issues = isset($_POST['issues']) ? array_map('sanitize_text_field', (array) $_POST['issues']) : [];
        $details = sanitize_textarea_field($_POST['details'] ?? '');

        // Validate
        if (!$booking_id || !$token) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        // Verify token
        if (!Scheduler::verify_token($booking_id, $token)) {
            wp_send_json_error(['message' => 'Invalid token']);
        }

        // Check if already reviewed
        if ($this->has_reviewed($booking_id)) {
            wp_send_json_error(['message' => 'Already reviewed']);
        }

        // Store the feedback privately (not as a published review)
        $this->store_private_feedback($booking_id, $rating, $issues, $details);

        // Mark as reviewed
        update_post_meta($booking_id, '_shaped_review_submitted', current_time('mysql'));
        update_post_meta($booking_id, '_shaped_review_type', 'feedback');

        wp_send_json_success([
            'action'  => 'feedback_received',
            'message' => 'Thank you for your feedback. We truly value your input and will use it to improve our service.',
        ]);
    }

    /**
     * Create a published review
     *
     * @param int    $booking_id  Booking ID
     * @param int    $rating      Rating (1-10)
     * @param string $comment     Review comment
     * @param string $author_name Guest name
     * @return int|false Review post ID or false on failure
     */
    private function create_review(int $booking_id, int $rating, string $comment, string $author_name) {
        // Get booking for additional info
        $booking = \MPHB()->getBookingRepository()->findById($booking_id, true);
        $customer = $booking ? $booking->getCustomer() : null;

        // Ensure author name has minimum length
        if (strlen($author_name) < 4 && $customer) {
            $author_name = $customer->getFirstName();
            if (strlen($author_name) < 4) {
                $author_name .= ' ' . substr($customer->getLastName(), 0, 1) . '.';
            }
        }

        // Create review post
        $post_data = [
            'post_title'   => $author_name . ' - ' . date('F Y'),
            'post_content' => $comment,
            'post_status'  => 'publish',
            'post_type'    => 'shaped_review',
        ];

        $review_id = wp_insert_post($post_data);

        if (is_wp_error($review_id)) {
            error_log('[Shaped Review] Failed to create review: ' . $review_id->get_error_message());
            return false;
        }

        // Set meta fields
        update_post_meta($review_id, 'review_rating', $rating);
        update_post_meta($review_id, 'provider', 'direct');
        update_post_meta($review_id, 'author_name', $author_name);
        update_post_meta($review_id, 'review_date', date('Y-m-d'));
        update_post_meta($review_id, 'is_featured', '0');
        update_post_meta($review_id, 'priority', 0);
        update_post_meta($review_id, 'content_locked', '1');
        update_post_meta($review_id, 'status', 'published');
        update_post_meta($review_id, '_shaped_booking_id', $booking_id);

        // Generate external key for consistency with sync system
        $external_key = md5('direct' . $author_name . date('Y-m-d') . substr($comment, 0, 50));
        update_post_meta($review_id, 'external_key', $external_key);

        // Set provider taxonomy
        wp_set_object_terms($review_id, 'direct', 'review_provider');

        error_log('[Shaped Review] Created review #' . $review_id . ' for booking #' . $booking_id . ' with rating ' . $rating);

        return $review_id;
    }

    /**
     * Store private feedback (not published)
     *
     * @param int    $booking_id Booking ID
     * @param int    $rating     Rating
     * @param array  $issues     Selected issue categories
     * @param string $details    Additional details
     */
    private function store_private_feedback(int $booking_id, int $rating, array $issues, string $details): void {
        $feedback = [
            'rating'     => $rating,
            'issues'     => $issues,
            'details'    => $details,
            'submitted'  => current_time('mysql'),
        ];

        update_post_meta($booking_id, '_shaped_guest_feedback', $feedback);

        // Log for admin notification
        error_log('[Shaped Review] Private feedback received for booking #' . $booking_id . ': Rating ' . $rating . ', Issues: ' . implode(', ', $issues));

        // Optionally send admin notification
        $this->notify_admin_of_feedback($booking_id, $feedback);
    }

    /**
     * Notify admin of negative feedback
     *
     * @param int   $booking_id Booking ID
     * @param array $feedback   Feedback data
     */
    private function notify_admin_of_feedback(int $booking_id, array $feedback): void {
        $admin_email = get_option('admin_email');
        $from_name = shaped_email_config('from_name', get_bloginfo('name'));

        $subject = 'Guest Feedback Received - Booking #' . $booking_id;

        // Get guest info with fallbacks to meta
        $booking = \MPHB()->getBookingRepository()->findById($booking_id, true);
        $customer = $booking ? $booking->getCustomer() : null;

        $guest_first = $customer ? $customer->getFirstName() : null;
        $guest_last = $customer ? $customer->getLastName() : null;
        $guest_email = $customer ? $customer->getEmail() : null;

        // Fallback to meta values
        if (!$guest_first) {
            $guest_first = get_post_meta($booking_id, '_mphb_first_name', true) ?: '';
        }
        if (!$guest_last) {
            $guest_last = get_post_meta($booking_id, '_mphb_last_name', true) ?: '';
        }
        if (!$guest_email) {
            $guest_email = get_post_meta($booking_id, '_mphb_email', true) ?: 'Unknown';
        }

        $guest_name = trim($guest_first . ' ' . $guest_last) ?: 'Unknown';

        // Get the original comment from the first form if no details provided
        $details = $feedback['details'];
        if (!$details) {
            $details = get_post_meta($booking_id, '_shaped_review_initial_comment', true) ?: 'No additional details provided';
        }

        $message = sprintf(
            "A guest has provided feedback after their stay.\n\n" .
            "Booking ID: #%d\n" .
            "Guest: %s (%s)\n" .
            "Rating: %d/10\n" .
            "Issues: %s\n" .
            "Details: %s\n\n" .
            "This feedback was not published publicly due to the rating being below %d.",
            $booking_id,
            $guest_name,
            $guest_email,
            $feedback['rating'],
            !empty($feedback['issues']) ? implode(', ', $feedback['issues']) : 'None selected',
            $details,
            self::PUBLISH_THRESHOLD
        );

        wp_mail($admin_email, $subject, $message);
    }
}
