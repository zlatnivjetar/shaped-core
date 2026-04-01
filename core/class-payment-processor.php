<?php
/**
 * Core Payment Logic
 * 
 * Supports two property-wide modes:
 * 1. Scheduled Charge: <7 days = immediate full, ≥7 days = save card + charge at T-7
 * 2. Deposit: ALL bookings charge X% immediately, balance due on arrival
 *
 * @package Shaped_Core
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use MPHB\Entities\Payment;

class Shaped_Payment_Processor
{
    public function __construct()
    {
        // Checkout Payment Notice (UI)
        add_action('mphb_sc_checkout_form', [$this, 'render_checkout_payment_notice'], 55, 3);

        // Checkout Redirect & Session Creation
        add_action('template_redirect', [$this, 'handle_card_update_redirect'], 5);
        add_action('template_redirect', [$this, 'intercept_checkout_redirect']);

        // Webhook Route Registration
        add_action('rest_api_init', [$this, 'register_webhook_route']);

        // Scheduled Charge Executor (for scheduled mode only)
        add_action('shaped_charge_single_booking', [$this, 'charge_single_booking'], 10, 2);

        // Charge Cleanup Hooks
        add_action('before_delete_post', [$this, 'on_booking_delete_clear_schedule'], 10);
        add_action('wp_trash_post',      [$this, 'on_booking_delete_clear_schedule'], 10, 1);
        add_action('mphb_booking_status_changed', [$this, 'on_booking_status_changed'], 10, 2);

        // Daily Fallback Scheduler (for scheduled mode)
        add_action('init', [$this, 'ensure_daily_fallback_schedule']);
        add_action('shaped_daily_charge_fallback', [$this, 'daily_charge_fallback']);

        // Failed charge recovery and expiry
        add_action('init', [$this, 'schedule_card_update_expiry_sweep']);
        add_action('shaped_expire_card_update_bookings', [$this, 'expire_overdue_card_updates']);
        add_action('init', [$this, 'maybe_expire_overdue_card_updates'], 25);
    }

    /* ============================================================
     * Idempotency Helpers
     * ========================================================== */

    public static function event_already_processed(string $event_id): bool
    {
        return $event_id ? (bool) get_transient('shaped_evt_' . $event_id) : false;
    }

    public static function mark_event_processed(string $event_id): void
    {
        if ($event_id) {
            set_transient('shaped_evt_' . $event_id, 1, DAY_IN_SECONDS * 14);
        }
    }

    public static function session_already_processed(string $session_id): bool
    {
        return $session_id ? (bool) get_transient('shaped_sess_' . $session_id) : false;
    }

    public static function mark_session_processed(string $session_id): void
    {
        if ($session_id) {
            set_transient('shaped_sess_' . $session_id, 1, DAY_IN_SECONDS * 14);
        }
    }

    public static function clear_scheduled_charge(int $booking_id): void
    {
        $key = get_post_meta($booking_id, '_shaped_idempotency_key', true);
        if ($key) {
            wp_clear_scheduled_hook('shaped_charge_single_booking', [$booking_id, $key]);
        }
    }

    public static function clear_scheduled_charge_state(int $booking_id): void
    {
        delete_post_meta($booking_id, '_shaped_charge_scheduled');
        delete_post_meta($booking_id, '_shaped_idempotency_key');
    }

    public static function clear_card_update_recovery(int $booking_id): void
    {
        delete_post_meta($booking_id, '_shaped_card_update_required_at');
        delete_post_meta($booking_id, '_shaped_card_update_deadline_at');
        delete_post_meta($booking_id, '_shaped_card_update_token_hash');
        delete_post_meta($booking_id, '_shaped_card_update_session_id');
        delete_post_meta($booking_id, '_shaped_card_update_session_expires_at');
    }

    public static function detach_saved_payment_method(int $booking_id): bool
    {
        $customer_id       = get_post_meta($booking_id, '_stripe_customer_id', true);
        $payment_method_id = get_post_meta($booking_id, '_stripe_payment_method_id', true);

        if (!$customer_id || !$payment_method_id) {
            error_log('[Shaped] Cannot detach - missing customer or PM for booking #' . $booking_id);
            return false;
        }

        try {
            shaped_load_stripe_sdk();
            $stripe = new \Stripe\StripeClient(shaped_get_stripe_secret());
            $stripe->paymentMethods->detach($payment_method_id);
            delete_post_meta($booking_id, '_stripe_payment_method_id');
            error_log('[Shaped] Payment method detached for booking #' . $booking_id);
            return true;
        } catch (\Throwable $e) {
            error_log('[Shaped] Detach failed: ' . $e->getMessage());
            delete_post_meta($booking_id, '_stripe_payment_method_id');
            return false;
        }
    }

    /* ============================================================
     * Payment Context
     * ========================================================== */

    public static function get_payment_context($booking): ?array
    {
        if (!$booking) return null;

        $booking_id = $booking->getId();
        $check_in   = $booking->getCheckInDate();
        $check_out  = $booking->getCheckOutDate();

        // Get configurable threshold (default 7 days)
        $threshold_days = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_scheduled_threshold_days() : 7;

        // Normalize to 16:00 check-in and compute charge date
        $check_in_datetime = clone $check_in;
        $check_in_datetime->setTime(16, 0, 0);
        $charge_date = (clone $check_in_datetime)->modify('-' . $threshold_days . ' days');

        // Deltas (days)
        $now               = new DateTime('now', $check_in_datetime->getTimezone());
        $days_until        = ($check_in_datetime->getTimestamp() - $now->getTimestamp()) / 86400;
        $days_until_charge = ($charge_date->getTimestamp() - $now->getTimestamp()) / 86400;

        // Get property-wide payment mode
        $property_mode = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_payment_mode() : 'scheduled';

        // Determine effective mode for this booking
        $stored_mode = get_post_meta($booking_id, '_shaped_payment_mode', true);
        if ($stored_mode) {
            $mode = $stored_mode;
        } elseif ($property_mode === 'deposit') {
            $mode = 'deposit';
        } else {
            $mode = ($days_until < $threshold_days) ? 'immediate' : 'delayed';
        }

        // Status & Amount
        $is_charged      = (bool) get_post_meta($booking_id, '_stripe_payment_charged', true);
        $payment_status  = get_post_meta($booking_id, '_shaped_payment_status', true);
        $payment_type    = get_post_meta($booking_id, '_shaped_payment_type', true); // 'full' | 'deposit'
        
        // Amounts
        $amount          = null;
        $paid_amount     = (float) get_post_meta($booking_id, '_mphb_paid_amount', true);
        $pending_amount  = (float) get_post_meta($booking_id, '_stripe_pending_amount', true);
        $stored_amount   = (float) get_post_meta($booking_id, '_shaped_payment_amount', true);
        $deposit_amount  = (float) get_post_meta($booking_id, '_shaped_deposit_amount', true);
        $balance_due     = (float) get_post_meta($booking_id, '_shaped_balance_due', true);
        $card_update_deadline_raw = (string) get_post_meta($booking_id, '_shaped_card_update_deadline_at', true);
        $card_update_deadline = null;
        if ($card_update_deadline_raw !== '') {
            try {
                $card_update_deadline = new DateTime($card_update_deadline_raw, new DateTimeZone('UTC'));
                $card_update_deadline->setTimezone(wp_timezone());
            } catch (\Throwable $e) {
                $card_update_deadline = null;
            }
        }

        if ($paid_amount > 0) {
            $amount = $paid_amount;
        } elseif ($pending_amount > 0) {
            $amount = $pending_amount;
        } elseif ($stored_amount > 0) {
            $amount = $stored_amount;
        }

        $is_immediate = ($mode === 'immediate' || $mode === 'deposit' || $days_until < $threshold_days || $is_charged);

        $actual_charge_status = 'pending';
        if ($is_charged || $payment_status === 'completed') {
            $actual_charge_status = 'paid';
        } elseif ($payment_status === 'deposit_paid') {
            $actual_charge_status = 'deposit_paid';
        } elseif ($payment_status === 'authorized') {
            $actual_charge_status = 'authorized';
        } elseif ($payment_status === 'card_update_required') {
            $actual_charge_status = 'card_update_required';
        } elseif ($payment_status === 'manual_review') {
            $actual_charge_status = 'manual_review';
        } elseif ($payment_status === 'charge_failed') {
            $actual_charge_status = 'failed';
        }

        return [
            'booking_id'        => $booking_id,
            'check_in'          => $check_in,
            'check_out'         => $check_out,
            'charge_date'       => $charge_date,
            'days_until'        => $days_until,
            'days_until_charge' => $days_until_charge,
            'mode'              => $mode,
            'property_mode'     => $property_mode,
            'is_charged'        => $is_charged,
            'amount'            => $amount,
            'is_immediate'      => $is_immediate,
            'payment_status'    => $actual_charge_status,
            'payment_type'      => $payment_type ?: 'full',
            'deposit_amount'    => $deposit_amount,
            'balance_due'       => $balance_due,
            'threshold_days'    => $threshold_days,
            'card_update_deadline' => $card_update_deadline,
        ];
    }

    private static function hash_card_update_token(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function create_card_update_token(): string
    {
        try {
            return bin2hex(random_bytes(24));
        } catch (\Throwable $e) {
            return wp_generate_password(48, false, false);
        }
    }

    private static function validate_card_update_token(int $booking_id, string $token): bool
    {
        $stored_hash = (string) get_post_meta($booking_id, '_shaped_card_update_token_hash', true);

        return $stored_hash !== '' && hash_equals($stored_hash, self::hash_card_update_token($token));
    }

    private static function get_card_update_deadline_timestamp(int $booking_id): int
    {
        $deadline = (string) get_post_meta($booking_id, '_shaped_card_update_deadline_at', true);

        return $deadline !== '' ? (int) strtotime($deadline) : 0;
    }

    private static function is_card_update_overdue(int $booking_id): bool
    {
        $deadline_ts = self::get_card_update_deadline_timestamp($booking_id);

        return $deadline_ts > 0 && $deadline_ts <= current_time('timestamp', true);
    }

    private static function get_manage_booking_url_for_booking($booking, array $args = []): string
    {
        return shaped_get_manage_booking_url(
            $booking->getId(),
            $booking->getCustomer()->getEmail(),
            $args
        );
    }

    private function get_card_update_cancel_url($booking, string $update_token): string
    {
        return self::get_manage_booking_url_for_booking($booking, [
            'card_update_result' => 'cancelled',
            'update_token'       => $update_token,
        ]);
    }

    private function attach_setup_payment_method_to_customer(\Stripe\StripeClient $stripe, string $setup_intent_id, string $customer_id): ?string
    {
        $setup_intent = $stripe->setupIntents->retrieve($setup_intent_id);
        $payment_method_id = is_string($setup_intent->payment_method) ? $setup_intent->payment_method : '';

        if ($payment_method_id === '') {
            return null;
        }

        $pm = $stripe->paymentMethods->retrieve($payment_method_id);
        if (empty($pm->customer)) {
            $stripe->paymentMethods->attach($payment_method_id, ['customer' => $customer_id]);
        }

        $stripe->customers->update($customer_id, [
            'invoice_settings' => ['default_payment_method' => $payment_method_id],
        ]);

        return $payment_method_id;
    }

    private function handle_scheduled_charge_card_failure(int $booking_id, string $error_message = ''): void
    {
        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) {
            return;
        }

        $current_status = (string) get_post_meta($booking_id, '_shaped_payment_status', true);
        if (in_array($current_status, ['completed', 'cancelled', 'manual_review'], true)) {
            return;
        }

        if ($current_status === 'card_update_required' && get_post_meta($booking_id, '_shaped_card_update_deadline_at', true)) {
            return;
        }

        $now_ts = current_time('timestamp', true);
        $failed_at = gmdate('Y-m-d H:i:s', $now_ts);
        $deadline_at = gmdate('Y-m-d H:i:s', $now_ts + (48 * HOUR_IN_SECONDS));
        $update_token = self::create_card_update_token();

        self::clear_scheduled_charge($booking_id);
        self::clear_scheduled_charge_state($booking_id);
        self::clear_card_update_recovery($booking_id);

        update_post_meta($booking_id, '_shaped_payment_status', 'card_update_required');
        update_post_meta($booking_id, '_shaped_card_update_required_at', $failed_at);
        update_post_meta($booking_id, '_shaped_card_update_deadline_at', $deadline_at);
        update_post_meta($booking_id, '_shaped_card_update_token_hash', self::hash_card_update_token($update_token));
        delete_post_meta($booking_id, '_shaped_card_updated_at');

        if (function_exists('shaped_send_payment_failed_email')) {
            shaped_send_payment_failed_email($booking_id, $update_token, $deadline_at);
        }
        if (function_exists('shaped_send_admin_payment_failed_email')) {
            shaped_send_admin_payment_failed_email($booking_id, $deadline_at);
        }

        error_log(sprintf(
            '[Shaped Charge] Booking #%d requires card update after failed charge%s',
            $booking_id,
            $error_message !== '' ? ': ' . $error_message : ''
        ));
    }

    /* ============================================================
     * Checkout Payment Notice (UI)
     * ========================================================== */

    public function render_checkout_payment_notice($booking, $roomDetails, $customer = null): void
    {
        $context = self::get_payment_context($booking);
        if (!$context) {
            return;
        }

        // Get property-wide mode
        $property_mode = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_payment_mode() : 'scheduled';

        // Base total
        $total_amount = (float) $booking->getTotalPrice();

        // Apply discount to accommodation
        $services_total  = 0;
        $price_breakdown = $booking->getPriceBreakdown();
        if (isset($price_breakdown['rooms']) && is_array($price_breakdown['rooms'])) {
            foreach ($price_breakdown['rooms'] as $room_data) {
                if (!empty($room_data['services']['total'])) {
                    $services_total += (float) $room_data['services']['total'];
                }
            }
        }
        $accommodation_total = $total_amount - $services_total;

        $reserved_rooms = $booking->getReservedRooms();
        if (!empty($reserved_rooms)) {
            $room       = reset($reserved_rooms);
            $room_type  = MPHB()->getRoomTypeRepository()->findById($room->getRoomTypeId());
            if ($room_type) {
                $room_slug = sanitize_title($room_type->getTitle());
                // Use range-aware discount lookup based on check-in and check-out dates
                $check_in_date = null;
                $check_out_date = null;
                if (method_exists($booking, 'getCheckInDate') && $booking->getCheckInDate()) {
                    $check_in_date = $booking->getCheckInDate()->format('Y-m-d');
                }
                if (method_exists($booking, 'getCheckOutDate') && $booking->getCheckOutDate()) {
                    $check_out_date = $booking->getCheckOutDate()->format('Y-m-d');
                }
                $discount_percent = class_exists('Shaped_Pricing')
                    ? (float) Shaped_Pricing::get_room_discount($room_slug, $check_in_date, $check_out_date)
                    : 0;
                if ($discount_percent > 0) {
                    $discount_amount  = round($accommodation_total * ($discount_percent / 100));
                    $total_amount     = $total_amount - $discount_amount;
                }
            }
        }

        // DEPOSIT MODE - Show deposit info
        if ($property_mode === 'deposit') {
            $deposit_data     = Shaped_Pricing::calculate_deposit($total_amount);
            $deposit_formatted = number_format($deposit_data['deposit'], 2);
            $balance_formatted = number_format($deposit_data['balance'], 2);
            $percent           = $deposit_data['percent'];
            ?>
            <style>.payment-methods{display:none!important;}</style>
            <div id="shaped-payment-note"
                 class="shaped-note shaped-note--deposit"
                 data-payment-mode="deposit"
                 data-deposit-amount="<?php echo esc_attr($deposit_data['deposit']); ?>"
                 data-balance-due="<?php echo esc_attr($deposit_data['balance']); ?>"
                 data-total="<?php echo esc_attr($total_amount); ?>"
                 data-deposit-percent="<?php echo esc_attr($percent); ?>">
                <div class="shaped-note__content">
                    <strong class="shaped-note__headline">Pay €<?php echo esc_html($deposit_formatted); ?> deposit today</strong>
                    <p class="shaped-note__body">
                        Secure your booking with a <strong><?php echo esc_html($percent); ?>% deposit</strong>.<br>
                        Remaining <strong>€<?php echo esc_html($balance_formatted); ?></strong> is due on arrival.
                    </p>
                </div>
            </div>
            <?php
            return;
        }


        // SCHEDULED MODE - Only show for delayed charges (≥7 days out)
        if ($context['is_immediate']) {
            return;
        }

        $charge_date_formatted = date_i18n('F j, Y', $context['charge_date']->getTimestamp());
        $amount_formatted      = number_format($total_amount, 2);

        ?>
        <style>.payment-methods{display:none!important;}</style>
        <div id="shaped-payment-note"
             class="shaped-note shaped-note--delayed"
             data-payment-mode="delayed"
             data-charge-date="<?php echo esc_attr($context['charge_date']->format('Y-m-d')); ?>"
             data-amount="<?php echo esc_attr($total_amount); ?>">
            <div class="shaped-note__content">
                <strong class="shaped-note__headline">Pay €0 today</strong>
                <p class="shaped-note__body">
                    We will charge <strong>€<?php echo esc_html($amount_formatted); ?></strong>
                    on <strong style="color:#141310"><?php echo esc_html($charge_date_formatted); ?></strong>
                    using the card you save now.
                </p>
            </div>
        </div>
        <?php
    }

    /* ============================================================
     * Checkout Redirect & Session Creation
     * ========================================================== */

    public function handle_card_update_redirect(): void
    {
        if (!isset($_GET['update_card'], $_GET['booking_id'], $_GET['update_token']) || $_GET['update_card'] != '1') {
            return;
        }

        $booking_id = absint($_GET['booking_id']);
        $update_token = sanitize_text_field((string) $_GET['update_token']);

        if (!$booking_id || $update_token === '') {
            wp_die('This card update link is invalid.');
        }

        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) {
            wp_die('This card update link is invalid.');
        }

        $payment_status = (string) get_post_meta($booking_id, '_shaped_payment_status', true);

        if ($booking->getStatus() === 'cancelled') {
            wp_safe_redirect(self::get_manage_booking_url_for_booking($booking, [
                'action' => 'cancelled',
            ]));
            exit;
        }

        if ($payment_status !== 'card_update_required') {
            if ($payment_status === 'manual_review') {
                wp_safe_redirect(self::get_manage_booking_url_for_booking($booking, [
                    'card_update_result' => 'success',
                ]));
                exit;
            }

            wp_die('This card update link is no longer active.');
        }

        if (self::is_card_update_overdue($booking_id)) {
            Shaped_Booking_Manager::cancel_booking($booking_id, [
                'reason'     => 'payment_update_expired',
                'email_type' => 'payment_update_expired',
            ]);
            wp_safe_redirect(self::get_manage_booking_url_for_booking($booking, [
                'action' => 'cancelled',
            ]));
            exit;
        }

        if (!self::validate_card_update_token($booking_id, $update_token)) {
            wp_die('This card update link is no longer valid.');
        }

        $customer_id = (string) get_post_meta($booking_id, '_stripe_customer_id', true);
        if ($customer_id === '') {
            wp_die('We could not start the card update flow for this booking.');
        }

        shaped_load_stripe_sdk();
        $stripe = new \Stripe\StripeClient(shaped_get_stripe_secret());

        $existing_session_id = (string) get_post_meta($booking_id, '_shaped_card_update_session_id', true);
        if ($existing_session_id !== '') {
            try {
                $session = $stripe->checkout->sessions->retrieve($existing_session_id);
                if ($session && $session->status === 'open') {
                    wp_safe_redirect($session->url);
                    exit;
                }
            } catch (\Throwable $e) {
                error_log('[Shaped Card Update] Existing session lookup failed: ' . $e->getMessage());
            }

            delete_post_meta($booking_id, '_shaped_card_update_session_id');
            delete_post_meta($booking_id, '_shaped_card_update_session_expires_at');
        }

        $pending_amount = (float) get_post_meta($booking_id, '_stripe_pending_amount', true);
        $amount_label = html_entity_decode(MPHB()->settings()->currency()->getCurrencySymbol(), ENT_QUOTES, 'UTF-8') . number_format($pending_amount, 2);

        try {
            $session = $stripe->checkout->sessions->create([
                'mode'                 => 'setup',
                'customer'             => $customer_id,
                'payment_method_types' => ['card'],
                'payment_method_options' => [
                    'card' => ['request_three_d_secure' => 'any'],
                ],
                'custom_text' => [
                    'submit' => ['message' => 'Save a new card to keep booking #' . $booking_id . ' active. No payment will be collected automatically.'],
                ],
                'metadata' => [
                    'flow'         => 'card_update',
                    'booking_id'   => $booking_id,
                    'payment_mode' => 'delayed',
                ],
                'success_url' => self::get_manage_booking_url_for_booking($booking, [
                    'card_update_result' => 'success',
                ]),
                'cancel_url' => $this->get_card_update_cancel_url($booking, $update_token),
            ]);

            update_post_meta($booking_id, '_shaped_card_update_session_id', $session->id);
            if (!empty($session->expires_at)) {
                update_post_meta($booking_id, '_shaped_card_update_session_expires_at', gmdate('Y-m-d H:i:s', (int) $session->expires_at));
            }

            error_log(sprintf('[Shaped Card Update] Redirecting booking #%d to Stripe card update session for %s', $booking_id, $amount_label));
            wp_safe_redirect($session->url);
            exit;
        } catch (\Throwable $e) {
            error_log('[Shaped Card Update] Session error: ' . $e->getMessage());
            wp_die('We could not start the card update flow right now. Please try again later.');
        }
    }

    public function schedule_card_update_expiry_sweep(): void
    {
        if (!wp_next_scheduled('shaped_expire_card_update_bookings')) {
            wp_schedule_event(time(), 'every_minute', 'shaped_expire_card_update_bookings');
        }
    }

    public function maybe_expire_overdue_card_updates(): void
    {
        if (wp_doing_cron()) {
            return;
        }

        static $processed = false;
        if ($processed) {
            return;
        }

        $processed = true;
        $this->expire_overdue_card_updates();
    }

    public function expire_overdue_card_updates(): void
    {
        $now = current_time('mysql', true);
        $bookings = get_posts([
            'post_type'      => 'mphb_booking',
            'post_status'    => ['confirmed', 'pending-payment'],
            'posts_per_page' => 50,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_shaped_payment_status',
                    'value' => 'card_update_required',
                ],
                [
                    'key'     => '_shaped_card_update_deadline_at',
                    'value'   => $now,
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ],
            ],
        ]);

        foreach ($bookings as $booking_post) {
            $booking_id = (int) $booking_post->ID;
            if (!self::is_card_update_overdue($booking_id)) {
                continue;
            }

            error_log('[Shaped Card Update] Auto-cancelling overdue booking #' . $booking_id);
            Shaped_Booking_Manager::cancel_booking($booking_id, [
                'reason'     => 'payment_update_expired',
                'email_type' => 'payment_update_expired',
            ]);
        }
    }

    public function intercept_checkout_redirect(): void
    {
        if (empty($_GET['payment_id']) || !str_contains($_SERVER['REQUEST_URI'], '/checkout/')) {
            return;
        }

        // If returning from Stripe success redirect
        if (isset($_GET['session_id'])) {
            return;
        }

        $payment_id = absint($_GET['payment_id']);
        $payment    = MPHB()->getPaymentRepository()->findById($payment_id, true);
        if (!$payment) return;

        $booking_id = $payment->getBookingId();
        $booking    = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) return;

        if ($payment->getStatus() === 'completed') {
            wp_safe_redirect(str_replace('{BOOKING_ID}', $booking_id, SHAPED_SUCCESS_URL));
            exit;
        }

        // Get property-wide payment mode
        $property_mode = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_payment_mode() : 'scheduled';

        // Get configurable threshold (default 7 days)
        $threshold_days = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_scheduled_threshold_days() : 7;

        // Determine payment mode
        $check_in           = $booking->getCheckInDate();
        $check_in_datetime  = clone $check_in;
        $check_in_datetime->setTime(16, 0, 0);
        $now = new DateTime('now', $check_in_datetime->getTimezone());
        $days_until_checkin = ($check_in_datetime->getTimestamp() - $now->getTimestamp()) / 86400;

        // Decide mode based on property setting
        if ($property_mode === 'deposit') {
            $payment_mode = 'deposit';
        } else {
            $payment_mode = ($days_until_checkin < $threshold_days) ? 'immediate' : 'delayed';
        }

        $charge_date = (clone $check_in_datetime)->modify('-' . $threshold_days . ' days')->format('Y-m-d');

        // Mark checkout started
        if (!get_post_meta($booking_id, '_shaped_checkout_started', true)) {
            update_post_meta($booking_id, '_shaped_checkout_started', current_time('mysql'));
            update_post_meta($booking_id, '_shaped_payment_status', 'pending');
            update_post_meta($booking_id, '_shaped_payment_mode', $payment_mode);
            set_transient('shaped_pending_' . $booking_id, true, 5 * MINUTE_IN_SECONDS);
        }

        // Stripe client
        shaped_load_stripe_sdk();
        $stripe = new \Stripe\StripeClient(shaped_get_stripe_secret());

        // Reuse existing open session
        $existing_session_id = get_post_meta($payment_id, '_shaped_stripe_session_id', true);
        if ($existing_session_id) {
            try {
                $session = $stripe->checkout->sessions->retrieve($existing_session_id);
                if ($session && $session->status === 'open') {
                    wp_safe_redirect($session->url);
                    exit;
                }
            } catch (\Throwable $e) { /* create new */ }
        }

        // Customer
        $customer_email = $booking->getCustomer()->getEmail();
        $customer_name  = trim($booking->getCustomer()->getFirstName() . ' ' . $booking->getCustomer()->getLastName());

        $stripe_customer_id = get_post_meta($booking_id, '_stripe_customer_id', true);
        if (!$stripe_customer_id) {
            try {
                $customers = $stripe->customers->all(['email' => $customer_email, 'limit' => 1]);
                if (!empty($customers->data)) {
                    $stripe_customer = $customers->data[0];
                } else {
                    $stripe_customer = $stripe->customers->create([
                        'email'    => $customer_email,
                        'name'     => $customer_name,
                        'metadata' => ['booking_id' => $booking_id],
                    ]);
                }
                $stripe_customer_id = $stripe_customer->id;
                update_post_meta($booking_id, '_stripe_customer_id', $stripe_customer_id);
            } catch (\Throwable $e) {
                error_log('[Shaped] Customer create/retrieve failed: ' . $e->getMessage());
            }
        }

        // Pricing / product name
        $room_titles = array_map('get_the_title', $booking->getReservedRoomTypeIds());
        $product     = sprintf('Booking #%d – %s', $booking_id, implode(', ', $room_titles));

        // Compute discounted total (canonical)
        $discounted_total = class_exists('Shaped_Pricing')
            ? Shaped_Pricing::calculate_final_amount($booking)
            : (float) $booking->getTotalPrice();

        $currency = strtolower(MPHB()->settings()->currency()->getCurrencyCode());

        try {
            // === DEPOSIT MODE ===
            if ($payment_mode === 'deposit') {
                $deposit_data = Shaped_Pricing::calculate_deposit($discounted_total);
                
                // Round deposit to 1 decimal place to match frontend display
                $deposit_rounded = round($deposit_data['deposit'], 1);
                $balance_due = round($discounted_total - $deposit_rounded, 2);
                
                $deposit_amount = (int) round($deposit_rounded * 100); // cents
                $percent = $deposit_data['percent'];
            
                $product_name = sprintf('%s – %d%% Deposit', $product, $percent);
            
                $session_params = [
                    'mode'      => 'payment',
                    'customer'  => $stripe_customer_id,
                    'line_items' => [[
                        'price_data' => [
                            'currency'     => $currency,
                            'product_data' => ['name' => $product_name],
                            'unit_amount'  => $deposit_amount,
                        ],
                        'quantity' => 1,
                    ]],
                    'payment_intent_data' => [
                        'description' => 'Booking #' . $booking_id . ' - Deposit (' . $percent . '%)',
                        'metadata' => [
                            'booking_id'     => $booking_id,
                            'payment_id'     => $payment_id,
                            'payment_mode'   => 'deposit',
                            'payment_type'   => 'deposit',
                            'deposit_percent'=> $percent,
                            'total_amount'   => $discounted_total,
                            'deposit_amount' => $deposit_rounded,  // Use rounded value
                            'balance_due'    => $balance_due,      // Use recalculated balance
                        ],
                    ],
                    'metadata' => [
                        'booking_id'     => $booking_id,
                        'payment_id'     => $payment_id,
                        'payment_mode'   => 'deposit',
                        'payment_type'   => 'deposit',
                        'deposit_percent'=> $percent,
                        'total_amount'   => $discounted_total,
                        'deposit_amount' => $deposit_rounded,
                        'balance_due'    => $balance_due,
                    ],
                    'success_url' => str_replace('{BOOKING_ID}', $booking_id, SHAPED_SUCCESS_URL) . '&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'  => SHAPED_CANCEL_URL . '?cancel=1&booking_id=' . $booking_id,
                ];
            
                // Store deposit meta before redirect (use rounded values)
                update_post_meta($booking_id, '_shaped_payment_type', 'deposit');
                update_post_meta($booking_id, '_shaped_deposit_percent', $percent);
                update_post_meta($booking_id, '_shaped_deposit_amount', $deposit_rounded);
                update_post_meta($booking_id, '_shaped_balance_due', $balance_due);
                update_post_meta($booking_id, '_shaped_payment_amount', $discounted_total);

            // === IMMEDIATE MODE (full payment) ===
            } elseif ($payment_mode === 'immediate') {
                $amount = (int) round($discounted_total * 100);

                $session_params = [
                    'mode'      => 'payment',
                    'customer'  => $stripe_customer_id,
                    'line_items' => [[
                        'price_data' => [
                            'currency'     => $currency,
                            'product_data' => ['name' => $product],
                            'unit_amount'  => $amount,
                        ],
                        'quantity' => 1,
                    ]],
                    'payment_intent_data' => [
                        'description' => 'Booking #' . $booking_id . ' - Direct charge',
                        'metadata' => [
                            'booking_id'   => $booking_id,
                            'payment_id'   => $payment_id,
                            'payment_mode' => 'immediate',
                            'payment_type' => 'full',
                        ],
                    ],
                    'metadata' => [
                        'booking_id'   => $booking_id,
                        'payment_id'   => $payment_id,
                        'payment_mode' => 'immediate',
                        'payment_type' => 'full',
                    ],
                    'success_url' => str_replace('{BOOKING_ID}', $booking_id, SHAPED_SUCCESS_URL) . '&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'  => SHAPED_CANCEL_URL . '?cancel=1&booking_id=' . $booking_id,
                ];

                update_post_meta($booking_id, '_shaped_payment_type', 'full');
                update_post_meta($booking_id, '_shaped_payment_amount', $discounted_total);

            // === DELAYED MODE (save card, charge later) ===
            } else {
                $amount = (int) round($discounted_total * 100);
                $charge_date_pretty = date_i18n('F j, Y', strtotime($charge_date));
                $amount_pretty      = number_format($discounted_total, 2);
                $currency_symbol    = html_entity_decode(MPHB()->settings()->currency()->getCurrencySymbol(), ENT_QUOTES, 'UTF-8');

                $session_params = [
                    'mode'                  => 'setup',
                    'customer'              => $stripe_customer_id,
                    'payment_method_types'  => ['card'],
                    'payment_method_options'=> [
                        'card' => ['request_three_d_secure' => 'any'],
                    ],
                    'currency'              => $currency,
                    'custom_text'           => [
                        'submit' => ['message' => 'We will charge ' . $currency_symbol . $amount_pretty . ' on ' . $charge_date_pretty . '.'],
                    ],
                    'metadata' => [
                        'booking_id'          => $booking_id,
                        'payment_id'          => $payment_id,
                        'total_amount'        => $amount,
                        'currency'            => $currency,
                        'product_description' => $product,
                        'payment_mode'        => 'delayed',
                        'payment_type'        => 'full',
                        'charge_date'         => $charge_date,
                    ],
                    'success_url' => str_replace('{BOOKING_ID}', $booking_id, SHAPED_SUCCESS_URL) . '&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'  => SHAPED_CANCEL_URL . '?cancel=1&booking_id=' . $booking_id,
                ];

                update_post_meta($booking_id, '_shaped_payment_type', 'full');
                update_post_meta($booking_id, '_shaped_payment_amount', $discounted_total);
                update_post_meta($booking_id, '_shaped_charge_date', $charge_date);
            }

            $session = $stripe->checkout->sessions->create($session_params);

            update_post_meta($payment_id, '_shaped_stripe_session_id', $session->id);

            // Set booking to abandoned before redirecting to Stripe
            if (!get_post_meta($booking_id, '_roomcloud_source', true)) {
                wp_update_post([
                    'ID' => $booking_id,
                    'post_status' => 'abandoned'
                ]);
                error_log('[Shaped] Booking #' . $booking_id . ' set to abandoned before Stripe redirect');
            }

            wp_safe_redirect($session->url);
            exit;
        } catch (\Throwable $e) {
            error_log('[Shaped] Stripe Session Error: ' . $e->getMessage());
            wp_die('Payment initialization failed. Please try again.');
        }
    }

    /* ============================================================
     * Webhook Route Registration
     * ========================================================== */

    public function register_webhook_route(): void
    {
        register_rest_route('shaped/v1', '/stripe-webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_stripe_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    /* ============================================================
     * Webhook Handler
     * ========================================================== */

    public function handle_stripe_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $payload    = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');

        shaped_load_stripe_sdk();

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, shaped_get_stripe_webhook());
            $event_id = isset($event->id) ? (string) $event->id : '';

            if (self::event_already_processed($event_id)) {
                return new WP_REST_Response(['received' => true, 'skipped' => 'duplicate_event'], 200);
            }
        } catch (\Throwable $e) {
            error_log('[Shaped Webhook] Invalid signature: ' . $e->getMessage());
            return new WP_REST_Response(['error' => 'Invalid signature'], 400);
        }

        // checkout.session.completed
        if ($event->type === 'checkout.session.completed') {
            $session    = $event->data->object;
            $session_id = isset($session->id) ? (string) $session->id : '';

            if ($session_id && self::session_already_processed($session_id)) {
                self::mark_event_processed($event_id);
                return new WP_REST_Response(['received' => true, 'skipped' => 'duplicate_session'], 200);
            }

            $flow         = isset($session->metadata->flow) ? (string) $session->metadata->flow : '';
            $booking_id   = isset($session->metadata->booking_id) ? absint($session->metadata->booking_id) : 0;
            $payment_mode = isset($session->metadata->payment_mode) ? (string) $session->metadata->payment_mode : '';
            $payment_type = isset($session->metadata->payment_type) ? (string) $session->metadata->payment_type : 'full';

            if ($flow === 'card_update') {
                if (!$booking_id) {
                    self::mark_session_processed($session_id);
                    self::mark_event_processed($event_id);
                    return new WP_REST_Response(['received' => true, 'note' => 'no_booking_id'], 200);
                }

                try {
                    $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
                    if (!$booking) {
                        self::mark_session_processed($session_id);
                        self::mark_event_processed($event_id);
                        return new WP_REST_Response(['received' => true, 'note' => 'booking_missing'], 200);
                    }

                    $current_status = (string) get_post_meta($booking_id, '_shaped_payment_status', true);
                    if ($booking->getStatus() === 'cancelled' || in_array($current_status, ['cancelled', 'completed'], true)) {
                        self::mark_session_processed($session_id);
                        self::mark_event_processed($event_id);
                        return new WP_REST_Response(['received' => true, 'note' => 'booking_closed'], 200);
                    }

                    if ($current_status !== 'card_update_required' || self::is_card_update_overdue($booking_id)) {
                        if (self::is_card_update_overdue($booking_id)) {
                            Shaped_Booking_Manager::cancel_booking($booking_id, [
                                'reason'     => 'payment_update_expired',
                                'email_type' => 'payment_update_expired',
                            ]);
                        }

                        self::mark_session_processed($session_id);
                        self::mark_event_processed($event_id);
                        return new WP_REST_Response(['received' => true, 'note' => 'card_update_not_required'], 200);
                    }

                    $customer_id = (string) ($session->customer ?: get_post_meta($booking_id, '_stripe_customer_id', true));
                    if ($customer_id === '' || empty($session->setup_intent)) {
                        throw new \RuntimeException('Missing customer or setup_intent on card update session.');
                    }

                    $stripe = new \Stripe\StripeClient(shaped_get_stripe_secret());
                    $previous_payment_method_id = (string) get_post_meta($booking_id, '_stripe_payment_method_id', true);
                    $payment_method_id = $this->attach_setup_payment_method_to_customer(
                        $stripe,
                        (string) $session->setup_intent,
                        $customer_id
                    );

                    if ($payment_method_id === null) {
                        throw new \RuntimeException('Card update session did not return a payment method.');
                    }

                    if ($previous_payment_method_id !== '' && $previous_payment_method_id !== $payment_method_id) {
                        try {
                            $stripe->paymentMethods->detach($previous_payment_method_id);
                        } catch (\Throwable $e) {
                            error_log('[Shaped Card Update] Previous payment method detach failed: ' . $e->getMessage());
                        }
                    }

                    self::clear_card_update_recovery($booking_id);
                    update_post_meta($booking_id, '_stripe_customer_id', $customer_id);
                    update_post_meta($booking_id, '_stripe_payment_method_id', $payment_method_id);
                    update_post_meta($booking_id, '_stripe_setup_intent_id', $session->setup_intent);
                    update_post_meta($booking_id, '_shaped_payment_status', 'manual_review');
                    update_post_meta($booking_id, '_shaped_card_updated_at', current_time('mysql'));
                    delete_post_meta($booking_id, '_shaped_cancellation_reason');

                    if ($booking->getStatus() !== 'confirmed') {
                        try {
                            $booking->setStatus('confirmed');
                            MPHB()->getBookingRepository()->save($booking);
                        } catch (\Throwable $e) {
                            error_log('[Shaped Card Update] Booking confirm failed: ' . $e->getMessage());
                        }
                    }

                    if (function_exists('shaped_send_admin_card_updated_email')) {
                        shaped_send_admin_card_updated_email($booking_id);
                    }

                    self::mark_session_processed($session_id);
                    self::mark_event_processed($event_id);
                    return new WP_REST_Response(['received' => true], 200);
                } catch (\Throwable $e) {
                    error_log('[Shaped Card Update] Webhook processing error: ' . $e->getMessage());
                    return new WP_REST_Response(['error' => 'card_update_processing_error'], 500);
                }
            }

            $payment_id = isset($session->metadata->payment_id) ? absint($session->metadata->payment_id) : 0;
            if (!$booking_id || !$payment_id) {
                error_log('[Shaped Webhook] Missing booking_id or payment_id.');
                return new WP_REST_Response(['error' => 'Missing metadata'], 400);
            }

            try {
                $booking = null;
                $payment = null;

                try { $booking = MPHB()->getBookingRepository()->findById($booking_id, true); } catch (\Throwable $e) {}
                try { $payment = MPHB()->getPaymentRepository()->findById($payment_id, true); } catch (\Throwable $e) {}

                if (!$booking) {
                    self::mark_session_processed($session_id);
                    self::mark_event_processed($event_id);
                    return new WP_REST_Response(['received' => true, 'note' => 'booking_missing'], 200);
                }

                // === PAYMENT MODE (immediate or deposit) ===
                if ($session->mode === 'payment') {
                    $paid_amount = (float)($session->amount_total / 100);

                    // Check if this is a deposit payment
                    if ($payment_type === 'deposit' || $payment_mode === 'deposit') {
                        // DEPOSIT PAYMENT
                        $total_amount    = isset($session->metadata->total_amount) ? (float) $session->metadata->total_amount : 0;
                        $deposit_amount  = isset($session->metadata->deposit_amount) ? (float) $session->metadata->deposit_amount : $paid_amount;
                        $balance_due     = isset($session->metadata->balance_due) ? (float) $session->metadata->balance_due : ($total_amount - $paid_amount);
                        $deposit_percent = isset($session->metadata->deposit_percent) ? (int) $session->metadata->deposit_percent : 0;

                        update_post_meta($booking_id, '_stripe_payment_charged', true);
                        update_post_meta($booking_id, '_shaped_payment_mode', 'deposit');
                        update_post_meta($booking_id, '_shaped_payment_type', 'deposit');
                        update_post_meta($booking_id, '_mphb_paid_amount', $paid_amount);
                        update_post_meta($booking_id, '_shaped_deposit_amount', $deposit_amount);
                        update_post_meta($booking_id, '_shaped_balance_due', $balance_due);
                        update_post_meta($booking_id, '_shaped_deposit_percent', $deposit_percent);
                        update_post_meta($booking_id, '_shaped_payment_amount', $total_amount);
                        update_post_meta($booking_id, '_shaped_payment_status', 'deposit_paid');
                        update_post_meta($booking_id, '_stripe_payment_intent_id', $session->payment_intent);
                        update_post_meta($booking_id, '_stripe_checkout_session_id', $session->id);

                        do_action('shaped_deposit_paid', $booking_id, $deposit_amount, $balance_due);
                        do_action('shaped_payment_completed', $booking_id, 'deposit');

                        error_log(sprintf(
                            '[Shaped Webhook] Deposit paid for booking #%d: €%.2f deposit, €%.2f balance due',
                            $booking_id, $deposit_amount, $balance_due
                        ));
                    } else {
                        // FULL IMMEDIATE PAYMENT
                        update_post_meta($booking_id, '_stripe_payment_charged', true);
                        update_post_meta($booking_id, '_shaped_payment_mode', 'immediate');
                        update_post_meta($booking_id, '_shaped_payment_type', 'full');
                        update_post_meta($booking_id, '_mphb_paid_amount', $paid_amount);
                        update_post_meta($booking_id, '_shaped_payment_status', 'completed');
                        update_post_meta($booking_id, '_stripe_payment_intent_id', $session->payment_intent);
                        update_post_meta($booking_id, '_stripe_checkout_session_id', $session->id);

                        do_action('shaped_payment_completed', $booking_id, 'immediate');
                    }

                    // Payment object (best-effort)
                    if ($payment) {
                        try {
                            if (method_exists($payment, 'setStatus')) $payment->setStatus('completed');
                            if (method_exists($payment, 'setAmount')) $payment->setAmount($paid_amount);
                            elseif (method_exists($payment, 'setTotal')) $payment->setTotal($paid_amount);
                            MPHB()->getPaymentRepository()->save($payment);
                        } catch (\Throwable $e) {
                            error_log('[Shaped Webhook] Payment update failed: ' . $e->getMessage());
                        }
                    }

                    // Confirm booking
                    if ($booking && $booking->getStatus() !== 'confirmed') {
                        try {
                            $booking->setStatus('confirmed');
                            MPHB()->getBookingRepository()->save($booking);
                        } catch (\Throwable $e) {}
                    }

                    // Send appropriate emails based on payment mode
                    if ($payment_type === 'deposit' || $payment_mode === 'deposit') {
                        // Deposit payment - send deposit confirmation emails
                        try { if (function_exists('shaped_send_deposit_confirmation_email')) shaped_send_deposit_confirmation_email($booking_id); } catch (\Throwable $e) {}
                        try { if (function_exists('shaped_send_admin_deposit_email')) shaped_send_admin_deposit_email($booking_id); } catch (\Throwable $e) {}
                    } else {
                        // Full payment - send regular confirmation emails
                        try { if (function_exists('shaped_send_confirmation_email')) shaped_send_confirmation_email($booking_id); } catch (\Throwable $e) {}
                        try { if (function_exists('shaped_send_admin_confirmation_email')) shaped_send_admin_confirmation_email($booking_id); } catch (\Throwable $e) {}
                    }

                    self::mark_session_processed($session_id);

                // === SETUP MODE (delayed charge) ===
                } elseif ($session->mode === 'setup') {
                    $stripe = new \Stripe\StripeClient(shaped_get_stripe_secret());

                    try {
                        $setup_intent      = $stripe->setupIntents->retrieve($session->setup_intent);
                        $payment_method_id = $setup_intent->payment_method;

                        if ($payment_method_id && $session->customer) {
                            try {
                                $pm = $stripe->paymentMethods->retrieve($payment_method_id);
                                if (empty($pm->customer)) {
                                    $stripe->paymentMethods->attach($payment_method_id, ['customer' => $session->customer]);
                                }
                                $stripe->customers->update($session->customer, [
                                    'invoice_settings' => ['default_payment_method' => $payment_method_id],
                                ]);
                            } catch (\Throwable $e) {
                                error_log('[Shaped] PM attach/set-default skipped/failed: ' . $e->getMessage());
                            }
                        }

                        $final_amount = class_exists('Shaped_Pricing')
                            ? Shaped_Pricing::calculate_final_amount($booking)
                            : (float) $booking->getTotalPrice();

                        // Get configurable threshold (default 7 days)
                        $threshold_days = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_scheduled_threshold_days() : 7;

                        // Charge date (threshold days before, 16:00 Europe/Zagreb => convert to UTC)
                        $tz               = new DateTimeZone('Europe/Zagreb');
                        $check_in         = $booking->getCheckInDate();
                        $check_in_dt      = clone $check_in; $check_in_dt->setTimezone($tz); $check_in_dt->setTime(16, 0, 0);
                        $charge_dt        = clone $check_in_dt; $charge_dt->modify('-' . $threshold_days . ' days'); $charge_dt->setTime(16, 0, 0);
                        $charge_dt->setTimezone(new DateTimeZone('UTC'));
                        $charge_ts  = $charge_dt->getTimestamp();
                        $charge_iso = $charge_dt->format('Y-m-d\TH:i:s\Z');

                        $idempotency_key = 'charge_' . $booking_id . '_' . md5($charge_iso);

                        // Store meta
                        update_post_meta($booking_id, '_shaped_payment_mode', 'delayed');
                        update_post_meta($booking_id, '_shaped_payment_type', 'full');
                        update_post_meta($booking_id, '_stripe_customer_id', $session->customer);
                        update_post_meta($booking_id, '_stripe_payment_method_id', $payment_method_id);
                        update_post_meta($booking_id, '_stripe_currency', $session->metadata->currency ?? 'eur');
                        update_post_meta($booking_id, '_stripe_pending_amount', $final_amount);
                        update_post_meta($booking_id, '_shaped_payment_status', 'authorized');
                        update_post_meta($booking_id, '_stripe_setup_intent_id', $session->setup_intent);
                        update_post_meta($booking_id, '_shaped_charge_at', $charge_iso);
                        update_post_meta($booking_id, '_shaped_idempotency_key', $idempotency_key);

                        // Emails (reservation)
                        try { if (function_exists('shaped_send_reservation_email')) shaped_send_reservation_email($booking_id); } catch (\Throwable $e) {}
                        try { if (function_exists('shaped_send_admin_reservation_email')) shaped_send_admin_reservation_email($booking_id); } catch (\Throwable $e) {}

                        // Persist the scheduled-charge flag independently from cron deduplication.
                        update_post_meta($booking_id, '_shaped_charge_scheduled', true);

                        // Schedule charge
                        $verify = wp_next_scheduled('shaped_charge_single_booking', [$booking_id, $idempotency_key]);
                        if (!$verify) {
                            $result = wp_schedule_single_event($charge_ts, 'shaped_charge_single_booking', [$booking_id, $idempotency_key]);

                            if ($result === false || is_wp_error($result)) {
                                $err_msg = is_wp_error($result) ? $result->get_error_message() : 'returned false';
                                error_log(sprintf('[Shaped] WARNING: wp_schedule_single_event failed for booking #%d: %s', $booking_id, $err_msg));
                            }

                            // Verify event is actually in the cron array
                            $verify = wp_next_scheduled('shaped_charge_single_booking', [$booking_id, $idempotency_key]);
                            if (!$verify) {
                                error_log(sprintf('[Shaped] WARNING: Per-booking cron NOT found after scheduling for booking #%d', $booking_id));
                            }

                            $log_dt = clone $charge_dt;
                            $log_dt->setTimezone(new DateTimeZone('Europe/Zagreb'));
                            error_log(sprintf('[Shaped] Scheduled charge for booking #%d at %s Zagreb time (ts=%d, verified=%s)', $booking_id, $log_dt->format('Y-m-d H:i:s'), $charge_ts, $verify ? 'yes' : 'NO'));
                        }
                    } catch (\Throwable $e) {
                        error_log('[Shaped] Setup processing error: ' . $e->getMessage());
                    }

                    // Confirm booking
                    if ($booking && $booking->getStatus() !== 'confirmed') {
                        try { $booking->setStatus('confirmed'); MPHB()->getBookingRepository()->save($booking); } catch (\Throwable $e) {}
                    }

                    // Notify RoomCloud immediately so it blocks dates for authorized bookings
                    do_action('shaped_booking_authorized', $booking_id);

                    self::mark_session_processed($session_id);
                }

                delete_transient('shaped_pending_' . $booking_id);
            } catch (\Throwable $e) {
                error_log('[Shaped Webhook] Processing error: ' . $e->getMessage());
                return new WP_REST_Response(['received' => true, 'error' => 'Processing error'], 200);
            }
        }

        // payment_intent.succeeded (scheduled charges)
        if ($event->type === 'payment_intent.succeeded') {
            if (self::event_already_processed($event_id)) {
                return new WP_REST_Response(['received' => true, 'skipped' => 'duplicate_event'], 200);
            }

            try {
                $pi         = $event->data->object;
                $booking_id = isset($pi->metadata->booking_id) ? absint($pi->metadata->booking_id) : 0;

                if (!$booking_id) {
                    self::mark_event_processed($event_id);
                    return new WP_REST_Response(['received' => true, 'note' => 'no_booking_id'], 200);
                }

                $booking = MPHB()->getBookingRepository()->findById($booking_id);
                if (!$booking) {
                    error_log('[Shaped Webhook] PI succeeded for missing booking ' . $booking_id);
                    self::mark_event_processed($event_id);
                    return new WP_REST_Response(['received' => true, 'note' => 'booking_missing'], 200);
                }

                update_post_meta($booking_id, '_stripe_payment_charged', true);
                update_post_meta($booking_id, '_mphb_paid_amount', $pi->amount / 100);
                update_post_meta($booking_id, '_shaped_payment_status', 'completed');
                do_action('shaped_payment_completed', $booking_id, 'delayed');

                if (isset($pi->metadata->payment_mode) && $pi->metadata->payment_mode === 'delayed') {
                    update_post_meta($booking_id, '_shaped_payment_mode', 'delayed');
                }

                self::mark_event_processed($event_id);
            } catch (\Throwable $e) {
                error_log('[Shaped Webhook] PI processing error: ' . $e->getMessage());
            }
        }

        if ($event->type === 'payment_intent.payment_failed') {
            if (self::event_already_processed($event_id)) {
                return new WP_REST_Response(['received' => true, 'skipped' => 'duplicate_event'], 200);
            }

            try {
                $pi = $event->data->object;
                $booking_id = isset($pi->metadata->booking_id) ? absint($pi->metadata->booking_id) : 0;
                $payment_mode = isset($pi->metadata->payment_mode) ? (string) $pi->metadata->payment_mode : '';

                if ($booking_id && $payment_mode === 'delayed') {
                    $current_status = (string) get_post_meta($booking_id, '_shaped_payment_status', true);
                    if ($current_status === 'authorized') {
                        $message = '';
                        if (!empty($pi->last_payment_error) && !empty($pi->last_payment_error->message)) {
                            $message = (string) $pi->last_payment_error->message;
                        }
                        $this->handle_scheduled_charge_card_failure($booking_id, $message);
                    }
                }

                self::mark_event_processed($event_id);
            } catch (\Throwable $e) {
                error_log('[Shaped Webhook] PI failed processing error: ' . $e->getMessage());
            }
        }

        self::mark_event_processed($event_id);
        return new WP_REST_Response(['received' => true], 200);
    }

    /* ============================================================
     * Scheduled Charge Executor
     * ========================================================== */

    public function charge_single_booking(int $booking_id, string $idempotency_key): void
    {
        error_log('[Shaped Charge] Processing booking #' . $booking_id);

        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking || $booking->getStatus() === 'cancelled') {
            error_log('[Shaped Charge] Booking missing/cancelled, skip');
            return;
        }

        // Guards
        $charge_processed = get_post_meta($booking_id, '_shaped_charge_processed', true);
        $payment_status   = get_post_meta($booking_id, '_shaped_payment_status', true);
        if ($charge_processed || in_array($payment_status, ['completed', 'cancelled'], true)) {
            error_log('[Shaped Charge] Already processed or cancelled, skip');
            return;
        }

        // Time guard
        $charge_at = get_post_meta($booking_id, '_shaped_charge_at', true);
        if ($charge_at && strtotime($charge_at) > time()) {
            error_log('[Shaped Charge] Not time yet, skip');
            return;
        }

        $final_amount = class_exists('Shaped_Pricing')
            ? Shaped_Pricing::calculate_final_amount($booking)
            : (float) $booking->getTotalPrice();

        $amount_cents      = (int) round($final_amount * 100);
        $customer_id       = get_post_meta($booking_id, '_stripe_customer_id', true);
        $payment_method_id = get_post_meta($booking_id, '_stripe_payment_method_id', true);
        $currency          = get_post_meta($booking_id, '_stripe_currency', true) ?: 'eur';

        if (!$customer_id) {
            error_log('[Shaped Charge] No customer ID');
            update_post_meta($booking_id, '_shaped_payment_status', 'charge_failed');
            return;
        }

        shaped_load_stripe_sdk();
        $stripe = new \Stripe\StripeClient(shaped_get_stripe_secret());

        // ── Stripe charge (the only part that can legitimately fail) ──
        try {
            if (!$payment_method_id) {
                $customer          = $stripe->customers->retrieve($customer_id);
                $payment_method_id = $customer->invoice_settings->default_payment_method;
                if (!$payment_method_id) {
                    throw new \Exception('No payment method available');
                }
            }

            $pi = $stripe->paymentIntents->create([
                'amount'         => $amount_cents,
                'currency'       => $currency,
                'customer'       => $customer_id,
                'payment_method' => $payment_method_id,
                'off_session'    => true,
                'confirm'        => true,
                'description'    => 'Booking #' . $booking_id . ' - Scheduled charge',
                'metadata'       => [
                    'booking_id'   => $booking_id,
                    'payment_mode' => 'delayed',
                    'payment_type' => 'full',
                ],
            ], [
                'idempotency_key' => $idempotency_key,
            ]);

            update_post_meta($booking_id, '_stripe_payment_intent_id', $pi->id);
            update_post_meta($booking_id, '_stripe_payment_charged', true);
            update_post_meta($booking_id, '_mphb_paid_amount', $final_amount);
            update_post_meta($booking_id, '_shaped_payment_status', 'completed');
            update_post_meta($booking_id, '_shaped_charge_processed', true);
        } catch (\Stripe\Exception\CardException $e) {
            error_log('[Shaped Charge] Payment failed: ' . $e->getMessage());
            $this->handle_scheduled_charge_card_failure($booking_id, $e->getMessage());
            return;
        } catch (\Throwable $e) {
            error_log('[Shaped Charge] Error: ' . $e->getMessage());
            update_post_meta($booking_id, '_shaped_payment_status', 'charge_failed');
            return;
        }

        // ── Post-charge side effects (charge already succeeded, these must not revert status) ──
        try { do_action('shaped_payment_completed', $booking_id, 'delayed'); }
        catch (\Throwable $e) { error_log('[Shaped Charge] Post-charge action error: ' . $e->getMessage()); }

        try {
            if ($booking->getStatus() !== 'confirmed') {
                $booking->setStatus('confirmed');
                MPHB()->getBookingRepository()->save($booking);
            }
        } catch (\Throwable $e) { error_log('[Shaped Charge] Status update error: ' . $e->getMessage()); }

        try {
            if (function_exists('shaped_send_confirmation_email')) {
                shaped_send_confirmation_email($booking_id);
            }
            if (function_exists('shaped_send_admin_confirmation_email')) {
                shaped_send_admin_confirmation_email($booking_id);
            }
        } catch (\Throwable $e) { error_log('[Shaped Charge] Confirmation email error: ' . $e->getMessage()); }

        try { $this->detach_payment_method($booking_id); }
        catch (\Throwable $e) { error_log('[Shaped Charge] PM detach error: ' . $e->getMessage()); }

        error_log('[Shaped Charge] Successfully charged booking #' . $booking_id);
    }

    /* ============================================================
     * PM Detach
     * ========================================================== */

    public function detach_payment_method(int $booking_id): bool
    {
        return self::detach_saved_payment_method($booking_id);
    }

    /* ============================================================
     * Charge Cleanup Hooks
     * ========================================================== */

    public function on_booking_delete_clear_schedule(?int $post_id): void
    {
        $post_id = absint($post_id ?? 0);
        if (!$post_id) return;

        if (get_post_type($post_id) !== 'mphb_booking') return;

        self::clear_scheduled_charge($post_id);
    }

    public function on_booking_status_changed($booking, $new_status): void
    {
        if (in_array($new_status, ['cancelled', 'abandoned', 'trash'], true)) {
            self::clear_scheduled_charge($booking->getId());
            self::clear_scheduled_charge_state($booking->getId());
            self::clear_card_update_recovery($booking->getId());
        }
    }

    /* ============================================================
     * Daily Fallback Scheduler
     * ========================================================== */

    public function ensure_daily_fallback_schedule(): void
    {
        $hook          = 'shaped_daily_charge_fallback';
        $tz            = new \DateTimeZone('Europe/Zagreb');
        $target_hour   = 16;
        $target_minute = 30;

        $existing_ts = wp_next_scheduled($hook);

        if ($existing_ts) {
            // Verify existing schedule fires at the correct Zagreb time-of-day
            $existing_dt = new \DateTime('@' . $existing_ts);
            $existing_dt->setTimezone($tz);
            $existing_h = (int) $existing_dt->format('G');
            $existing_m = (int) $existing_dt->format('i');

            if ($existing_h === $target_hour && abs($existing_m - $target_minute) <= 5) {
                return; // Schedule is correct
            }

            // Schedule drifted or was set at wrong time – clear and reschedule
            wp_clear_scheduled_hook($hook);
            error_log(sprintf(
                '[Shaped] Fallback cron was at %02d:%02d Zagreb, expected %02d:%02d – rescheduling',
                $existing_h, $existing_m, $target_hour, $target_minute
            ));
        }

        // Schedule for next occurrence of 16:30 Zagreb
        $now = new \DateTime('now', $tz);
        $run = clone $now;
        $run->setTime($target_hour, $target_minute, 0);

        if ($run <= $now) {
            $run->modify('+1 day');
        }

        wp_schedule_event($run->getTimestamp(), 'daily', $hook);
        error_log(sprintf('[Shaped] Scheduled daily fallback at %s Zagreb time', $run->format('Y-m-d H:i:s')));
    }

    public function daily_charge_fallback(): void
    {
        // Only run in scheduled mode
        if (class_exists('Shaped_Pricing') && Shaped_Pricing::is_deposit_mode()) {
            return;
        }

        error_log('[Shaped Fallback] Running daily charge check');
        $now = current_time('timestamp', true);

        $args = [
            'post_type'      => 'mphb_booking',
            'post_status'    => 'confirmed',
            'posts_per_page' => 50,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_shaped_payment_status',
                    'value' => 'authorized',
                ],
                [
                    'key'     => '_shaped_charge_processed',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];

        $bookings = get_posts($args);

        foreach ($bookings as $booking_post) {
            $booking_id = $booking_post->ID;
            $charge_at  = get_post_meta($booking_id, '_shaped_charge_at', true);

            if ($charge_at && strtotime($charge_at) <= $now) {
                $idempotency_key = get_post_meta($booking_id, '_shaped_idempotency_key', true)
                    ?: 'fallback_' . $booking_id . '_' . $charge_at;
                error_log('[Shaped Fallback] Processing missed charge for booking #' . $booking_id);
                try {
                    $this->charge_single_booking($booking_id, $idempotency_key);
                } catch (\Throwable $e) {
                    error_log('[Shaped Fallback] Unhandled error for booking #' . $booking_id . ': ' . $e->getMessage());
                }
            }
        }
    }
}
