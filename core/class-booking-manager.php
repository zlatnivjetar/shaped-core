<?php
/**
 * Booking Management & UI
 * - Abandonment scheduler
 * - Checkout cancellation via query
 * - Abandon helper (manual)
 * - Manage booking route
 * - Manage booking / Cancelled / Thank You shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Booking_Manager
{
    public function __construct()
    {
        /* [09] Abandonment Scheduler */
        add_filter('cron_schedules', [$this, 'register_every_minute']);
        add_action('init', [$this, 'schedule_abandonment_check']);
        add_action('shaped_check_abandoned_bookings', [$this, 'process_abandoned_bookings']);

        /* [22] Checkout Cancellation Via Query */
        add_action('init', [$this, 'handle_checkout_cancellation']);

        /* [26] Manage Booking Entry Route */
        add_action('init', [$this, 'manage_booking_entry_route']);

        /* [30] Shortcode Registrations */
        add_shortcode('shaped_manage_booking',   [$this, 'shortcode_manage_booking']);
        add_shortcode('shaped_booking_cancelled',[$this, 'shortcode_booking_cancelled']);
        add_shortcode('shaped_thank_you',        [$this, 'shortcode_thank_you']);
    }

    /* ============================================================
     * [09] Abandonment Scheduler
     * ========================================================== */

    public function register_every_minute(array $schedules): array
    {
        if (!isset($schedules['every_minute'])) {
            $schedules['every_minute'] = [
                'interval' => 60,
                'display'  => __('Every Minute', 'shaped'),
            ];
        }
        return $schedules;
    }

    public function schedule_abandonment_check(): void
    {
        if (!wp_next_scheduled('shaped_check_abandoned_bookings')) {
            wp_schedule_event(time(), 'every_minute', 'shaped_check_abandoned_bookings');
        }
    }

    public function process_abandoned_bookings(): void
    {
        $args = [
            'post_type'      => 'mphb_booking',
            'post_status'    => ['pending-payment', 'Confirmed'], // Keep original capitalization from legacy
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_shaped_checkout_started',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'   => '_shaped_payment_status',
                    'value' => 'pending',
                ],
            ],
        ];

        $pending_bookings = get_posts($args);

        foreach ($pending_bookings as $booking_post) {
            $booking_id       = (int) $booking_post->ID;
            $checkout_started = get_post_meta($booking_id, '_shaped_checkout_started', true);

            if (!$checkout_started) {
                continue;
            }

            $time_elapsed = current_time('timestamp') - strtotime($checkout_started);

            // Mark as abandoned and release room after 5 minutes
            if ($time_elapsed >= 300) {
                error_log('[Shaped Abandonment] Auto-abandoning booking #' . $booking_id . ' after ' . round($time_elapsed / 60) . ' minutes');

                $booking = MPHB()->getBookingRepository()->findById($booking_id);
                if (!$booking) {
                    continue;
                }

                $payment_status = get_post_meta($booking_id, '_shaped_payment_status', true);
                if ($payment_status !== 'pending') {
                    continue;
                }

                // Release room reservations immediately
                $reserved_rooms = $booking->getReservedRooms();
                foreach ($reserved_rooms as $room) {
                    wp_delete_post($room->getId(), true);
                    error_log('[Shaped Abandonment] Released room #' . $room->getId());
                }

                // Update post status to abandoned
                wp_update_post([
                    'ID'          => $booking_id,
                    'post_status' => 'abandoned',
                ]);

                // Update metadata
                update_post_meta($booking_id, '_shaped_payment_status', 'abandoned');
                update_post_meta($booking_id, '_shaped_abandoned_at', current_time('mysql'));

                // Clear transients/cache
                delete_transient('shaped_pending_' . $booking_id);
                delete_transient('mphb_booking_' . $booking_id);

                // MPHB availability
                do_action('mphb_booking_status_changed', $booking, 'abandoned');

                error_log('[Shaped Abandonment] Booking #' . $booking_id . ' abandoned and rooms released');
            }
        }
    }

    /* ============================================================
     * [22] Checkout Cancellation Via Query
     * ========================================================== */

    public function handle_checkout_cancellation(): void
    {
        if (!isset($_GET['cancel'], $_GET['booking_id']) || $_GET['cancel'] != '1') {
            return;
        }

        $booking_id = absint($_GET['booking_id']);
        if (!$booking_id) return;

        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking) return;

        $payment_status = get_post_meta($booking_id, '_shaped_payment_status', true);

        // Only process if it's a pending booking
        if ($payment_status === 'pending' || $booking->getStatus() === 'pending-payment') {
            error_log('[Shaped] Processing checkout cancellation for booking #' . $booking_id);

            // Release room reservations immediately
            $reserved_rooms = $booking->getReservedRooms();
            foreach ($reserved_rooms as $room) {
                wp_delete_post($room->getId(), true);
            }

            // Update booking status to abandoned
            wp_update_post([
                'ID'          => $booking_id,
                'post_status' => 'abandoned',
            ]);

            // Update metadata
            update_post_meta($booking_id, '_shaped_payment_status', 'abandoned');
            update_post_meta($booking_id, '_shaped_abandoned_at', current_time('mysql'));

            // Clear transients/cache
            delete_transient('shaped_pending_' . $booking_id);
            delete_transient('mphb_booking_' . $booking_id);

            // MPHB availability
            do_action('mphb_booking_status_changed', $booking, 'abandoned');

            error_log('[Shaped] Booking #' . $booking_id . ' abandoned and rooms released');
        }
    }

    /* ============================================================

    /* ============================================================
     * [23] Abandon Helper (Manual)
     * ========================================================== */

    public static function mark_booking_abandoned(int $booking_id): void
    {
        update_post_meta($booking_id, '_shaped_payment_status', 'abandoned');
        update_post_meta($booking_id, '_shaped_abandoned_at', current_time('mysql'));

        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if ($booking) {
            $reserved_rooms = $booking->getReservedRooms();
            foreach ($reserved_rooms as $room) {
                wp_delete_post($room->getId(), true);
            }
        }

        error_log('[Shaped] Marked booking #' . $booking_id . ' as abandoned');
    }

    /* ============================================================
     * [26] Manage Booking Entry Route
     * ========================================================== */

    public function manage_booking_entry_route(): void
    {
        if (!isset($_GET['manage_booking'], $_GET['booking_id'])) {
            return;
        }

        $booking_id = absint($_GET['booking_id']);
        $token      = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking) {
            wp_die('Invalid booking');
        }

        $expected_token = md5($booking_id . $booking->getCustomer()->getEmail());
        if ($token !== $expected_token) {
            wp_die('Invalid token');
        }

        // Include custom template if present (legacy behavior)
        $template = plugin_dir_path(SHAPED_PLUGIN_FILE) . 'templates/manage-booking.php';
        if (file_exists($template)) {
            include $template;
            exit;
        }
        // Fallback: render shortcode output directly
        echo do_shortcode('[shaped_manage_booking]');
        exit;
    }

    /* ============================================================
     * [27] Manage Booking Shortcode/UI
     * ========================================================== */

    public function shortcode_manage_booking(): string
    {
        // Prevent caching of this page
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        if (!isset($_GET['booking_id'], $_GET['token'])) {
            return '<p style="font-size: 1.5rem; font-weight:700; padding: 24px; border-bottom: 2px solid var(--color-brand-primary); padding-bottom: 8px;">Booking not found.</p>';
        }

        $booking_id = absint($_GET['booking_id']);
        $token      = sanitize_text_field($_GET['token']);

        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking) {
            return '<p style="font-size: 1.5rem; font-weight:700; padding: 24px; border-bottom: 2px solid var(--color-brand-primary); padding-bottom: 8px;">Booking not found.</p>';
        }

        $expected_token = md5($booking_id . $booking->getCustomer()->getEmail());
        if ($token !== $expected_token) {
            return '<p>Invalid access token.</p>';
        }

        if ($booking->getStatus() === 'cancelled') {
            return '<div style="max-width: 600px; margin: 0 auto; font-family: \'DM Sans\', -apple-system, BlinkMacSystemFont, sans-serif; background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 8px 32px rgba(20, 19, 16, 0.12); text-align: center;">
                <div style="color: var(--color-semantic-error); font-size: 3rem; margin-bottom: 16px;">✗</div>
                <h2 style="color: var(--color-text-primary); font-size: 1.5rem; font-weight: 600; margin-bottom: 16px;">Booking Cancelled</h2>
                <p style="color: var(--color-text-muted); margin-bottom: 24px; line-height: 1.5;">
                    This booking (#' . $booking_id . ') has been cancelled and can no longer be managed.
                </p>
                <p style="color: var(--color-text-muted); font-size: 0.875rem; margin: 0;">
                    If you need assistance, please contact us at<br>
                    <a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', shaped_brand('contact.phone', ''))) . '" style="color: var(--color-text-primary); text-decoration: none;">' . esc_html(shaped_brand('contact.phone', '')) . '</a> or
                    <a href="mailto:' . esc_attr(shaped_brand('contact.email', '')) . '" style="color: var(--color-text-primary); text-decoration: none;">' . esc_html(shaped_brand('contact.email', '')) . '</a>
                </p>
            </div>';
        }

        // Handle cancellation submission (Step 4)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
            $booking_url = add_query_arg([
                'booking_id' => $booking_id,
                'token'      => $token,
                'action'     => 'cancelled',
            ], home_url('/manage-booking/'));

            wp_redirect($booking_url);
            exit;
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'manage';
        if ($action === 'cancelled') {
            return $this->shortcode_booking_cancelled();
        }

        return $this->render_manage_booking_form($booking);
    }

    private function render_manage_booking_form($booking): string
    {
        // Refresh entity
        $booking = MPHB()->getBookingRepository()->findById($booking->getId(), true);

        $context = class_exists('Shaped_Payment_Processor')
            ? Shaped_Payment_Processor::get_payment_context($booking)
            : null;

        if (!$context) return '<p>Invalid booking.</p>';

        $booking_id = $context['booking_id'];
        $customer   = $booking->getCustomer();

        // Accommodation name
        $reserved_rooms     = $booking->getReservedRooms();
        $accommodation_name = '';
        if (!empty($reserved_rooms)) {
            $room      = reset($reserved_rooms);
            $room_type = MPHB()->getRoomTypeRepository()->findById($room->getRoomTypeId());
            if ($room_type) {
                $accommodation_name = $room_type->getTitle();
            }
        }

        $info_only = ($context['is_immediate'] || $booking->getStatus() === 'cancelled');

        ob_start();
        ?>
        <div class="shaped-manage-booking" style="max-width: 600px; margin: 0 auto; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif; background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 8px 32px rgba(20, 19, 16, 0.12);">
            <!-- Booking Details -->
            <div style="padding-bottom: 24px; margin-bottom: 24px; border-bottom: 1px solid #f0f0f0;">
                <h2 style="color: var(--color-text-primary); font-size: 1.5rem; font-weight: 600; margin-bottom: 24px; padding-bottom:16px; border-bottom: 2px solid var(--color-brand-primary);">Booking Details</h2>
                <div style="background: #fafafa; border-radius: 8px; padding: 24px; box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; border: 1px solid #f0f0f0;">
                    <div style="display: grid; gap: 12px; color: var(--color-text-primary);">
                        <div><span style="color: var(--color-text-muted);">Booking ID:</span> <strong>#<?php echo $booking_id; ?></strong></div>
                        <div><span style="color: var(--color-text-muted);">Guest:</span> <strong><?php echo esc_html($customer->getFirstName() . ' ' . $customer->getLastName()); ?></strong></div>
                        <div><span style="color: var(--color-text-muted);">Check-in:</span> <strong><?php echo $context['check_in']->format('F j, Y'); ?></strong></div>
                        <div><span style="color: var(--color-text-muted);">Check-out:</span> <strong><?php echo $context['check_out']->format('F j, Y'); ?></strong></div>
                        <?php if ($accommodation_name): ?>
                        <div><span style="color: var(--color-text-muted);">Accommodation:</span> <strong><?php echo esc_html($accommodation_name); ?></strong></div>
                        <?php endif; ?>

                        <?php if ($context['payment_type'] === 'deposit' && $context['deposit_amount'] > 0): ?>
                            <!-- DEPOSIT PAYMENT -->
                            <div style="padding-top: 8px; border-top: 1px solid #e0e0e0; margin-top: 8px;">
                                <div style="margin-bottom: 6px;"><span style="color: var(--color-text-muted);">Deposit Paid:</span> <strong style="color: var(--color-semantic-success);">€<?php echo number_format($context['deposit_amount'], 2); ?></strong></div>
                                <?php if ($context['balance_due'] > 0): ?>
                                <div style="margin-bottom: 6px;"><span style="color: var(--color-text-muted);">Balance Due on Arrival:</span> <strong style="color: var(--color-text-primary); font-weight: 600;">€<?php echo number_format($context['balance_due'], 2); ?></strong></div>
                                <?php endif; ?>
                                <div><span style="color: var(--color-text-muted);">Total Booking Amount:</span> <strong>€<?php echo number_format($context['deposit_amount'] + $context['balance_due'], 2); ?></strong></div>
                            </div>
                        <?php else: ?>
                            <!-- FULL PAYMENT -->
                            <?php if (is_numeric($context['amount'])): ?>
                            <div><span style="color: var(--color-text-muted);">Total Amount:</span> <strong style="color: var(--color-text-primary); font-weight: 600;">€<?php echo number_format($context['amount'], 2); ?></strong></div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div style="padding-top: 8px; border-top: 1px solid #e0e0e0;">
                            <span style="color: var(--color-text-muted);">Payment Status:</span>
                            <?php if ($context['payment_status'] === 'paid'): ?>
                                <strong style="color: var(--color-semantic-success);">Paid in Full</strong>
                            <?php elseif ($context['payment_status'] === 'deposit_paid'): ?>
                                <strong style="color: var(--color-semantic-success);">Deposit Paid</strong> <span style="color: var(--color-text-muted);">(Balance due on arrival)</span>
                            <?php elseif ($context['payment_status'] === 'failed'): ?>
                                <strong style="color: var(--color-semantic-error);">Payment Failed</strong>
                            <?php elseif ($context['payment_status'] === 'authorized'): ?>
                                <?php if ($context['days_until_charge'] > 0): ?>
                                    <strong style="color: var(--color-text-primary);">Will be charged on <?php echo $context['charge_date']->format('F j'); ?></strong>
                                <?php else: ?>
                                    <strong style="color: var(--color-semantic-success);">Processing</strong>
                                <?php endif; ?>
                            <?php else: ?>
                                <strong style="color: #999;">Pending</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$info_only): ?>
                <div>
                    <div style="box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; background: #fcf4f3; border: 1px solid #f0f0f0; padding: 24px; border-radius: 8px;">
                        <h2 style="color: var(--color-text-primary); font-size: 1.5rem; font-weight: 600; margin-bottom: 16px;">Cancel Your Booking</h2>
                        <p style="color: var(--color-text-muted); margin-bottom: 20px; line-height: 1.5;">
                            You can cancel your booking free of charge since payment has not been processed yet.
                        </p>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                            <button type="submit" name="action" value="cancel"
                                    style="background: var(--color-semantic-error); color: var(--color-text-inverse); padding: 16px 32px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">
                                Cancel Booking
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top:24px">
                <p style="color: var(--color-text-muted); margin: 0; font-size: 0.875rem;">
                    Questions? Contact us at<br>
                    <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', shaped_brand('contact.phone', ''))); ?>" style="color: var(--color-text-primary); text-decoration: none;"><?php echo esc_html(shaped_brand('contact.phone', '')); ?></a> or
                    <a href="mailto:<?php echo esc_attr(shaped_brand('contact.email', '')); ?>" style="color: var(--color-text-primary); text-decoration: none;"><?php echo esc_html(shaped_brand('contact.email', '')); ?></a>
                </p>
            </div>
        </div>

        <style>
        #redsubmit:hover{
          color: #fff !important;
          transform: translateY(-2px);
          box-shadow:
            0 0 4px rgba(184, 60, 46, 0.53),
            0 0 8px rgba(184, 60, 46, 0.47),
            0 0 16px rgba(184, 60, 46, 0.31);
        }
        @media (max-width: 640px) {
            .shaped-manage-booking > div, .shaped-manage-booking {
                padding: 0px !important;
            }
            .shaped-manage-booking > div {
                padding: 32px 24px 24px 24px !important;
                margin-bottom: 0 !important;
            }
            .shaped-manage-booking h1 {
                font-size: 1.75rem !important;
            }
            .shaped-manage-booking {
                border-radius: 0px !important;
            }
            #thetextincancelsec {
                font-size: .9375rem !important;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /* ============================================================
     * [28] Cancelled Page Shortcode & Flow
     * ========================================================== */

    public function shortcode_booking_cancelled(): string
    {
        if (!isset($_GET['booking_id'])) {
            return '<p>No booking specified.</p>';
        }

        $booking_id = absint($_GET['booking_id']);
        $booking    = MPHB()->getBookingRepository()->findById($booking_id);

        if (!$booking) {
            return '<p>Booking not found.</p>';
        }

        // Process cancellation if not already cancelled
        if ($booking->getStatus() !== 'cancelled') {
            $this->process_cancellation($booking_id);
        }

        $pending_amount = get_post_meta($booking_id, '_stripe_pending_amount', true);
        $was_charged    = get_post_meta($booking_id, '_stripe_payment_charged', true);

        ob_start();
        ?>
        <div class="shaped-cancelled" style="width: 600px; margin: 0 auto; padding: 32px; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;background: #ffffff; border-radius: 12px; box-shadow: 0 8px 32px rgba(20, 19, 16, 0.12); text-align: center;">
            <div id="checkwrap" style="width: 64px; height: 64px; background: var(--color-semantic-success); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: white; font-size: 32px;">✓</div>

            <h2 style="color: var(--color-text-primary); padding-top:8px; font-size: 2rem; font-weight: 700; margin-bottom: 24px; line-height: 1.2;" class="manageheaderbooking">Booking Cancelled</h2>

            <div style="padding-bottom: 16px; margin-bottom: 24px; border-bottom: 2px solid var(--color-brand-primary);">
                <p style="font-size: 1.125rem; color: var(--color-text-primary); margin-bottom: 0; line-height: 1.5;">
                    Your booking <strong>#<?php echo $booking_id; ?></strong> has been successfully cancelled.
                </p>
            </div>

            <?php if (!$was_charged): ?>
                <div style="background: #fffbf0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
                    <h2 style="color: var(--color-text-primary); font-size: 1.5rem; font-weight: 600; margin-bottom: 16px;">No Charge Applied</h2>
                    <p style="font-size: 1.125rem; color: var(--color-text-primary); margin: 0;">
                        Your card has not been charged. The reservation of
                        <strong style="color: var(--color-text-primary); font-weight: 600;">€<?php echo number_format((float)$pending_amount, 2); ?></strong>
                        has been cancelled.
                    </p>
                </div>
            <?php else:
                $threshold_days = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_scheduled_threshold_days() : 7;
            ?>
                <div style="background: #fff5f5; border: 1px solid #f8d7da; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                    <p style="color: var(--color-semantic-error); margin: 0;">
                        Your payment of <strong>€<?php echo number_format((float)$pending_amount, 2); ?></strong> was already processed.
                        Per our cancellation policy, bookings cancelled within <?php echo esc_html($threshold_days); ?> days of check-in are non-refundable.
                    </p>
                </div>
            <?php endif; ?>

            <p style="color: var(--color-text-muted); font-size: 0.9375rem; margin: 0; line-height: 1.5;">
                A confirmation email has been sent to your registered email address.
            </p>
        </div>

        <style>
        @media (max-width: 640px) {
            .manageheaderbooking {
                font-size: 1.75rem !important;
                margin-top:8px !important;
                margin-bottom: 0px !important;
            }

            .shaped-cancelled > div {
                padding: 24px !important;
            }

            #checkwrap {
                width:56px !important;
                height: 56px !important;
                margin-bottom: 16px !important;
            }

            .shaped-cancelled {
                padding: 32px 24px 48px 24px !important;
                width: 100% !important;
                border-radius: 0 !important;
            }

            .shaped-cancelled > div > div:not(:first-child) {
                padding-bottom: 8px !important;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }

    private function process_cancellation(int $booking_id): void
    {
        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking || $booking->getStatus() === 'cancelled') {
            return;
        }

        $was_charged     = get_post_meta($booking_id, '_stripe_payment_charged', true);
        $payment_status  = get_post_meta($booking_id, '_shaped_payment_status', true);

        // If authorized but not charged, prevent future charge
        if ($payment_status === 'authorized' && !$was_charged) {
            // Detach payment method and clear schedule
            if (class_exists('Shaped_Payment_Processor')) {
                $processor = new Shaped_Payment_Processor();
                $processor->detach_payment_method($booking_id);
            }

            $charge_at = get_post_meta($booking_id, '_shaped_charge_at', true);
            if ($charge_at) {
                $idempotency_key = get_post_meta($booking_id, '_shaped_idempotency_key', true);
                wp_clear_scheduled_hook('shaped_charge_single_booking', [$booking_id, $idempotency_key]);

                update_post_meta($booking_id, '_shaped_payment_status', 'cancelled');
                delete_post_meta($booking_id, '_shaped_charge_scheduled');
            }
        }

        // Release room reservations
        $reserved_rooms = $booking->getReservedRooms();
        foreach ($reserved_rooms as $room) {
            wp_delete_post($room->getId(), true);
        }

        // Mark as cancelled
        wp_update_post([
            'ID'          => $booking_id,
            'post_status' => 'cancelled',
        ]);
        
        do_action('shaped_booking_cancelled', $booking_id);

        // Clear booking cache
        delete_transient('mphb_booking_' . $booking_id);

        // Trigger MPHB hooks
        do_action('mphb_booking_status_changed', $booking, 'cancelled');

        // Emails
        $pending_amount = (float) get_post_meta($booking_id, '_stripe_pending_amount', true);
        $refund_amount  = $was_charged ? 0 : $pending_amount;

        if (function_exists('shaped_send_cancellation_email')) {
            shaped_send_cancellation_email($booking_id, $refund_amount, 0);
        }
        if (function_exists('shaped_send_admin_cancellation_email')) {
            shaped_send_admin_cancellation_email($booking_id, $refund_amount, 0);
        }

        error_log('[Shaped] Booking #' . $booking_id . ' cancelled, payment method detached (if any), rooms released');
    }

    /* ============================================================
     * [29] Thank You Page Shortcode
     * ========================================================== */

    public function shortcode_thank_you(): string
    {
        if (!isset($_GET['booking_id'], $_GET['session_id'])) {
            return '<p style="text-align: center; padding: 48px 24px;">Invalid booking reference.</p>';
        }

        $booking_id = absint($_GET['booking_id']);
        $session_id = sanitize_text_field($_GET['session_id']);

        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking) {
            return '<p style="text-align: center; padding: 48px 24px;">Booking not found.</p>';
        }

        // Security window (1h from creation)
        $booking_date = get_post_field('post_date', $booking_id);
        $time_diff    = current_time('timestamp') - strtotime($booking_date);
        if ($time_diff > 3600) {
            return '<p style="text-align: center; padding: 48px 24px;">This page has expired. Please check your email for booking details.</p>';
        }

        $context = class_exists('Shaped_Payment_Processor')
            ? Shaped_Payment_Processor::get_payment_context($booking)
            : null;

        if (!$context) {
            return '<p style="text-align: center; padding: 48px 24px;">Unable to load booking details.</p>';
        }

        $customer       = $booking->getCustomer();
        $reserved_rooms = $booking->getReservedRooms();
        $accommodation_name = '';

        if (!empty($reserved_rooms)) {
            $room      = reset($reserved_rooms);
            $room_type = MPHB()->getRoomTypeRepository()->findById($room->getRoomTypeId());
            if ($room_type) {
                $accommodation_name = $room_type->getTitle();
            }
        }

        $token     = md5($booking_id . $customer->getEmail());
        $manage_url= home_url('/manage-booking/?booking_id=' . $booking_id . '&token=' . $token);

        ob_start();
        ?>
        <div class="shaped-thank-you" style="max-width: 600px; margin: 0 auto; margin-top: -12rem; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--color-surface-page); border-radius: 12px; padding: 32px; box-shadow: 0 8px 32px rgba(20, 19, 16, 0.12);">
            <!-- Success -->
            <div style="text-align: center; padding-bottom: 24px; margin-bottom: 24px; border-bottom: 1px solid var(--color-border-default);">
                <div style="color: var(--color-semantic-success); font-size: 3rem; margin-bottom: 16px;">✓</div>
                <h1 style="color: var(--color-text-primary); font-size: 1.5rem; font-weight: 600; margin: 0 0 8px 0;">Booking Confirmed!</h1>
                <p style="color: var(--color-text-muted); margin: 0;">Your reservation has been successfully secured.</p>
            </div>

            <!-- Details -->
            <div style="padding-bottom: 24px; margin-bottom: 24px; border-bottom: 1px solid var(--color-border-default);">
                <h2 style="color: var(--color-text-primary); font-size: 1.5rem; font-weight: 600; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--color-brand-primary);">Booking Details</h2>
                <div style="background: var(--color-surface-alt); border-radius: 8px; padding: 24px; box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; border: 1px solid var(--color-border-default);">
                    <div style="display: grid; gap: 12px; color: var(--color-text-primary);">
                        <div><span style="color: var(--color-text-muted);">Booking ID:</span> <strong>#<?php echo $booking_id; ?></strong></div>
                        <div><span style="color: var(--color-text-muted);">Guest Name:</span> <strong><?php echo esc_html($customer->getFirstName() . ' ' . $customer->getLastName()); ?></strong></div>
                        <div><span style="color: var(--color-text-muted);">Check-in:</span> <strong><?php echo $context['check_in']->format('F j, Y'); ?></strong> from 16:00h</div>
                        <div><span style="color: var(--color-text-muted);">Check-out:</span> <strong><?php echo $context['check_out']->format('F j, Y'); ?></strong> until 11:00h</div>
                        <div><span style="color: var(--color-text-muted);">Accommodation:</span> <strong><?php echo esc_html($accommodation_name); ?></strong></div>
                    </div>
                </div>
            </div>

            <!-- Payment -->
            <div style="padding-bottom: 24px; margin-bottom: 24px; border-bottom: 1px solid var(--color-border-default);">
                <div style="background: var(--color-surface-highlight); box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; padding: 24px; border-radius: 8px; border: 1px solid var(--color-border-default);">
                    <h2 style="color: var(--color-text-primary); font-size: 1.5rem; font-weight: 600; margin-bottom: 16px;">Payment Information</h2>

                    <?php if ($context['payment_type'] === 'deposit' && $context['deposit_amount'] > 0): ?>
                        <!-- DEPOSIT PAYMENT -->
                        <p style="color: var(--color-text-primary); margin-bottom: 12px; line-height: 1.5;">
                            <strong>Deposit Paid:</strong>
                            <span style="color: var(--color-text-primary); font-size: 1.25rem; font-weight: 600;">
                                €<?php echo number_format((float)$context['deposit_amount'], 2); ?>
                            </span>
                        </p>
                        <?php if ($context['balance_due'] > 0): ?>
                        <p style="color: var(--color-text-primary); margin-bottom: 12px; line-height: 1.5;">
                            <strong>Balance Due on Arrival:</strong>
                            <span style="font-size: 1.125rem; font-weight: 600;">
                                €<?php echo number_format((float)$context['balance_due'], 2); ?>
                            </span>
                        </p>
                        <?php endif; ?>
                        <p style="color: var(--color-text-muted); margin-bottom: 8px; padding-top: 8px; border-top: 1px solid var(--color-border-default); font-size: 0.9375rem;">
                            Total Booking Amount: <strong>€<?php echo number_format((float)($context['deposit_amount'] + $context['balance_due']), 2); ?></strong>
                        </p>
                        <p style="color: var(--color-text-primary); margin-bottom: 0; line-height: 1.5;">
                            Thank you for your deposit. The remaining balance is due upon arrival at the property. You'll receive a receipt by email shortly.
                        </p>
                    <?php else: ?>
                        <!-- FULL PAYMENT (immediate or delayed) -->
                        <?php if (is_numeric($context['amount'])): ?>
                        <p style="color: var(--color-text-primary); margin-bottom: 12px; line-height: 1.5;">
                            <strong>Total Amount:</strong>
                            <span style="color: var(--color-text-primary); font-size: 1.25rem; font-weight: 600;">
                                €<?php echo number_format((float)$context['amount'], 2); ?>
                            </span>
                        </p>
                        <?php endif; ?>

                        <?php if ($context['is_immediate']): ?>
                            <p style="color: var(--color-text-primary); margin-bottom: 8px; line-height: 1.5;">
                                Thank you for booking with us. You'll receive a receipt by email shortly.
                            </p>
                        <?php else:
                            $threshold_days = isset($context['threshold_days']) ? $context['threshold_days'] : 7;
                        ?>
                            <p style="color: var(--color-text-primary); margin-bottom: 8px; line-height: 1.5;">
                                Your card has been securely saved and will be charged <strong><?php echo esc_html($threshold_days); ?> days before check-in</strong>
                                (<?php echo $context['charge_date']->format('F j, Y'); ?>).
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Getting Here -->
            <div style="padding-bottom: 24px; margin-bottom: 24px; border-bottom: 1px solid var(--color-border-default);">
                <div style="background: var(--color-surface-alt); box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; padding: 24px; border-radius: 8px;">
                    <h2 style="color: var(--color-text-primary); font-size: 1.5rem; font-weight: 600; margin-bottom: 16px;">Getting Here</h2>
                    <p style="color: var(--color-text-muted); margin-bottom: 12px; line-height: 1.5;">
                        <strong style="color: var(--color-text-primary);">Address:</strong> <?php
                        $address_raw = shaped_brand('contact.address', '');
                        $address_formatted = function_exists('shaped_email_format_address') ? shaped_email_format_address($address_raw) : $address_raw;
                        echo esc_html($address_formatted);
                        ?>
                    </p>
                    <p style="color: var(--color-text-muted); margin: 0; line-height: 1.5;">
                        <?php echo esc_html(shaped_brand('email.checkInInstructions', 'Visit us at reception upon arrival.')); ?>
                    </p>
                </div>
            </div>

            <!-- Next Steps -->
            <div style="text-align: center;">
                <p style="color: var(--color-text-muted); margin-bottom: 20px;">
                    We've sent details to <strong><?php echo esc_html($customer->getEmail()); ?></strong>
                </p>
                <p style="color: var(--color-text-muted); margin-top: 24px; font-size: 0.875rem;">
                    Questions? Contact us at <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', shaped_brand('contact.phone', ''))); ?>" style="color: var(--color-text-primary); text-decoration: none;"><?php echo esc_html(shaped_brand('contact.phone', '')); ?></a> or
                    <a href="mailto:<?php echo esc_attr(shaped_brand('contact.email', '')); ?>" style="color: var(--color-text-primary); text-decoration: none;"><?php echo esc_html(shaped_brand('contact.email', '')); ?></a>
                </p>
            </div>
        </div>

        <style>
            .shaped-thank-you a[style*="background: var(--color-brand-primary)"]:hover {
                transform: translateY(-2px);
                box-shadow:
                    0 0 4px rgba(209,175,93,0.6),
                    0 0 8px rgba(209,175,93,0.45),
                    0 0 16px rgba(209,175,93,0.3);
                background: var(--color-brand-primary-hover) !important;
            }

            @media (max-width: 640px) {
                .shaped-thank-you {
                    padding: 0px !important;
                    margin: 0 !important;
                }
                .shaped-thank-you > div {
                    padding: 32px 24px 24px 24px !important;
                    margin-bottom: 0 !important;
                }
                .shaped-thank-you h1 {
                    font-size: 1.25rem !important;
                }
                .shaped-thank-you {
                    border-radius: 0px !important;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }
}