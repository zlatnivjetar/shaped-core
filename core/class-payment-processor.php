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

    /* ============================================================
     * Payment Context
     * ========================================================== */

    public static function get_payment_context($booking): ?array
    {
        if (!$booking) return null;

        $booking_id = $booking->getId();
        $check_in   = $booking->getCheckInDate();
        $check_out  = $booking->getCheckOutDate();

        // Normalize to 16:00 check-in and compute charge date
        $check_in_datetime = clone $check_in;
        $check_in_datetime->setTime(16, 0, 0);
        $charge_date = (clone $check_in_datetime)->modify('-7 days');

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
            $mode = ($days_until < 7) ? 'immediate' : 'delayed';
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

        if ($paid_amount > 0) {
            $amount = $paid_amount;
        } elseif ($pending_amount > 0) {
            $amount = $pending_amount;
        } elseif ($stored_amount > 0) {
            $amount = $stored_amount;
        }

        $is_immediate = ($mode === 'immediate' || $mode === 'deposit' || $days_until < 7 || $is_charged);

        $actual_charge_status = 'pending';
        if ($is_charged || $payment_status === 'completed') {
            $actual_charge_status = 'paid';
        } elseif ($payment_status === 'deposit_paid') {
            $actual_charge_status = 'deposit_paid';
        } elseif ($payment_status === 'authorized') {
            $actual_charge_status = 'authorized';
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
        ];
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
                $cfg       = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_discounts_config() : [];
                if (isset($cfg[$room_slug])) {
                    $discount_percent = (float) $cfg[$room_slug];
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
            $payment_mode = ($days_until_checkin < 7) ? 'immediate' : 'delayed';
        }

        $charge_date = (clone $check_in_datetime)->modify('-7 days')->format('Y-m-d');

        // Mark checkout started
        if (!get_post_meta($booking_id, '_shaped_checkout_started', true)) {
            update_post_meta($booking_id, '_shaped_checkout_started', current_time('mysql'));
            update_post_meta($booking_id, '_shaped_payment_status', 'pending');
            update_post_meta($booking_id, '_shaped_payment_mode', $payment_mode);
            set_transient('shaped_pending_' . $booking_id, true, 5 * MINUTE_IN_SECONDS);
        }

        // Stripe client
        shaped_load_stripe_sdk();
        $stripe = new \Stripe\StripeClient(SHAPED_STRIPE_SECRET);

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
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, SHAPED_STRIPE_WEBHOOK);
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

            $booking_id   = isset($session->metadata->booking_id) ? absint($session->metadata->booking_id) : 0;
            $payment_id   = isset($session->metadata->payment_id) ? absint($session->metadata->payment_id) : 0;
            $payment_mode = isset($session->metadata->payment_mode) ? (string) $session->metadata->payment_mode : '';
            $payment_type = isset($session->metadata->payment_type) ? (string) $session->metadata->payment_type : 'full';

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
                    $stripe = new \Stripe\StripeClient(SHAPED_STRIPE_SECRET);

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

                        // Charge date (7 days before, 16:00 Europe/Zagreb => convert to UTC)
                        $tz               = new DateTimeZone('Europe/Zagreb');
                        $check_in         = $booking->getCheckInDate();
                        $check_in_dt      = clone $check_in; $check_in_dt->setTimezone($tz); $check_in_dt->setTime(16, 0, 0);
                        $charge_dt        = clone $check_in_dt; $charge_dt->modify('-7 days'); $charge_dt->setTime(16, 0, 0);
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

                        // Schedule charge
                        if (!get_post_meta($booking_id, '_shaped_charge_scheduled', true)) {
                            wp_schedule_single_event($charge_ts, 'shaped_charge_single_booking', [$booking_id, $idempotency_key]);
                            update_post_meta($booking_id, '_shaped_charge_scheduled', true);

                            $log_dt = clone $charge_dt;
                            $log_dt->setTimezone(new DateTimeZone('Europe/Zagreb'));
                            error_log(sprintf('[Shaped] Scheduled charge for booking #%d at %s Zagreb time', $booking_id, $log_dt->format('Y-m-d H:i:s')));
                        }
                    } catch (\Throwable $e) {
                        error_log('[Shaped] Setup processing error: ' . $e->getMessage());
                    }

                    // Confirm booking
                    if ($booking && $booking->getStatus() !== 'confirmed') {
                        try { $booking->setStatus('confirmed'); MPHB()->getBookingRepository()->save($booking); } catch (\Throwable $e) {}
                    }

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
        $stripe = new \Stripe\StripeClient(SHAPED_STRIPE_SECRET);

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

            do_action('shaped_payment_completed', $booking_id, 'delayed');

            if ($booking->getStatus() !== 'confirmed') {
                $booking->setStatus('confirmed');
                MPHB()->getBookingRepository()->save($booking);
            }

            if (function_exists('shaped_send_confirmation_email')) {
                shaped_send_confirmation_email($booking_id);
            }
            if (function_exists('shaped_send_admin_confirmation_email')) {
                shaped_send_admin_confirmation_email($booking_id);
            }

            $this->detach_payment_method($booking_id);

            error_log('[Shaped Charge] Successfully charged booking #' . $booking_id);
        } catch (\Stripe\Exception\CardException $e) {
            error_log('[Shaped Charge] Payment failed: ' . $e->getMessage());
            update_post_meta($booking_id, '_shaped_payment_status', 'charge_failed');
            // Send failure notifications to guest and admin
            if (function_exists('shaped_send_payment_failed_email')) {
                shaped_send_payment_failed_email($booking_id);
            }
            if (function_exists('shaped_send_admin_payment_failed_email')) {
                shaped_send_admin_payment_failed_email($booking_id);
            }
        } catch (\Throwable $e) {
            error_log('[Shaped Charge] Error: ' . $e->getMessage());
            update_post_meta($booking_id, '_shaped_payment_status', 'charge_failed');
        }
    }

    /* ============================================================
     * PM Detach
     * ========================================================== */

    public function detach_payment_method(int $booking_id): bool
    {
        $customer_id       = get_post_meta($booking_id, '_stripe_customer_id', true);
        $payment_method_id = get_post_meta($booking_id, '_stripe_payment_method_id', true);

        if (!$customer_id || !$payment_method_id) {
            error_log('[Shaped] Cannot detach - missing customer or PM for booking #' . $booking_id);
            return false;
        }

        try {
            shaped_load_stripe_sdk();
            $stripe = new \Stripe\StripeClient(SHAPED_STRIPE_SECRET);
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
        }
    }

    /* ============================================================
     * Daily Fallback Scheduler
     * ========================================================== */

    public function ensure_daily_fallback_schedule(): void
    {
        if (!wp_next_scheduled('shaped_daily_charge_fallback')) {
            wp_schedule_event(time(), 'daily', 'shaped_daily_charge_fallback');
        }
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
                $idempotency_key = 'fallback_' . $booking_id . '_' . time();
                error_log('[Shaped Fallback] Processing missed charge for booking #' . $booking_id);
                $this->charge_single_booking($booking_id, $idempotency_key);
            }
        }
    }
}