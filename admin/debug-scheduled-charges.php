<?php
/**
 * Scheduled Charge Diagnostic Tool
 *
 * Run via WP-CLI: wp eval-file admin/debug-scheduled-charges.php
 * Or add temporary admin page to load this file
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

echo "=== SCHEDULED CHARGE DIAGNOSTIC TOOL ===\n\n";

// Current time
$now_utc = new DateTime('now', new DateTimeZone('UTC'));
$now_zagreb = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
echo "Current time (UTC): " . $now_utc->format('Y-m-d H:i:s') . "\n";
echo "Current time (Zagreb): " . $now_zagreb->format('Y-m-d H:i:s') . "\n\n";

// Find bookings with authorized status
$args = [
    'post_type'      => 'mphb_booking',
    'post_status'    => ['publish', 'confirmed'],
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'   => '_shaped_payment_status',
            'value' => 'authorized',
        ],
    ],
];

$bookings = get_posts($args);

if (empty($bookings)) {
    echo "No bookings found with status 'authorized'.\n";
    exit;
}

echo "Found " . count($bookings) . " booking(s) with 'authorized' status:\n\n";

foreach ($bookings as $booking_post) {
    $booking_id = $booking_post->ID;
    echo "--- BOOKING #$booking_id ---\n";

    // Get booking object
    $booking = MPHB()->getBookingRepository()->findById($booking_id);
    if (!$booking) {
        echo "ERROR: Could not load booking object\n\n";
        continue;
    }

    // Basic info
    $check_in = $booking->getCheckInDate();
    $check_out = $booking->getCheckOutDate();
    echo "Status: " . $booking->getStatus() . "\n";
    echo "Check-in: " . $check_in->format('Y-m-d') . "\n";
    echo "Check-out: " . $check_out->format('Y-m-d') . "\n";
    echo "Total: " . $booking->getTotalPrice() . " EUR\n\n";

    // Payment metadata
    $payment_mode = get_post_meta($booking_id, '_shaped_payment_mode', true);
    $payment_status = get_post_meta($booking_id, '_shaped_payment_status', true);
    $charge_at = get_post_meta($booking_id, '_shaped_charge_at', true);
    $charge_scheduled = get_post_meta($booking_id, '_shaped_charge_scheduled', true);
    $charge_processed = get_post_meta($booking_id, '_shaped_charge_processed', true);
    $customer_id = get_post_meta($booking_id, '_stripe_customer_id', true);
    $payment_method_id = get_post_meta($booking_id, '_stripe_payment_method_id', true);
    $pending_amount = get_post_meta($booking_id, '_stripe_pending_amount', true);
    $idempotency_key = get_post_meta($booking_id, '_shaped_idempotency_key', true);

    echo "Payment Metadata:\n";
    echo "  Mode: " . ($payment_mode ?: 'N/A') . "\n";
    echo "  Status: " . ($payment_status ?: 'N/A') . "\n";
    echo "  Charge at (UTC): " . ($charge_at ?: 'N/A') . "\n";
    echo "  Charge scheduled: " . ($charge_scheduled ? 'Yes' : 'No') . "\n";
    echo "  Charge processed: " . ($charge_processed ? 'Yes' : 'No') . "\n";
    echo "  Stripe customer: " . ($customer_id ?: 'N/A') . "\n";
    echo "  Payment method: " . ($payment_method_id ?: 'N/A') . "\n";
    echo "  Pending amount: " . ($pending_amount ?: 'N/A') . " EUR\n";
    echo "  Idempotency key: " . ($idempotency_key ?: 'N/A') . "\n\n";

    // Parse charge time
    if ($charge_at) {
        $charge_dt_utc = new DateTime($charge_at, new DateTimeZone('UTC'));
        $charge_dt_zagreb = clone $charge_dt_utc;
        $charge_dt_zagreb->setTimezone(new DateTimeZone('Europe/Zagreb'));

        echo "Scheduled Charge Time:\n";
        echo "  UTC: " . $charge_dt_utc->format('Y-m-d H:i:s') . "\n";
        echo "  Zagreb: " . $charge_dt_zagreb->format('Y-m-d H:i:s') . "\n";

        $time_diff = $now_utc->getTimestamp() - $charge_dt_utc->getTimestamp();
        $hours_diff = round($time_diff / 3600, 1);

        if ($time_diff > 0) {
            echo "  Status: PAST DUE by " . abs($hours_diff) . " hours\n";
        } else {
            echo "  Status: Future (in " . abs($hours_diff) . " hours)\n";
        }
        echo "\n";
    }

    // Check WP-Cron schedule
    $cron_scheduled = wp_next_scheduled('shaped_charge_single_booking', [$booking_id, $idempotency_key]);
    echo "WP-Cron Status:\n";
    if ($cron_scheduled) {
        $cron_dt = new DateTime('@' . $cron_scheduled);
        $cron_dt->setTimezone(new DateTimeZone('Europe/Zagreb'));
        echo "  Event scheduled: Yes\n";
        echo "  Scheduled for: " . $cron_dt->format('Y-m-d H:i:s') . " Zagreb\n";

        if ($cron_scheduled <= time()) {
            echo "  Status: SHOULD HAVE RUN (WP-Cron may not be executing)\n";
        } else {
            echo "  Status: Waiting for scheduled time\n";
        }
    } else {
        echo "  Event scheduled: No (NOT FOUND IN WP-CRON)\n";
    }
    echo "\n";

    // Recommendations
    echo "RECOMMENDATIONS:\n";

    if (!$charge_processed && $payment_status === 'authorized') {
        if ($charge_at && strtotime($charge_at) <= time()) {
            echo "  ⚠ Charge is past due and should be processed immediately\n";
            echo "  💡 Run: wp eval-file admin/trigger-scheduled-charge.php $booking_id\n";

            if (!$cron_scheduled) {
                echo "  ⚠ WARNING: WP-Cron event is missing! This explains why charge didn't run.\n";
                echo "  💡 The daily fallback should catch this, or manually trigger the charge.\n";
            } else {
                echo "  ⚠ WARNING: WP-Cron event exists but hasn't executed.\n";
                echo "  💡 Check if WP-Cron is working: wp cron test\n";
                echo "  💡 Or set up real cron: wp cron event run --due-now\n";
            }
        } else {
            echo "  ✓ Charge is scheduled for future, waiting for scheduled time\n";
        }
    } elseif ($charge_processed) {
        echo "  ✓ Charge already processed\n";
    } else {
        echo "  ℹ Payment status: $payment_status\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n\n";
}

// Check if daily fallback is scheduled
echo "=== DAILY FALLBACK STATUS ===\n";
$fallback_scheduled = wp_next_scheduled('shaped_daily_charge_fallback');
if ($fallback_scheduled) {
    $fallback_dt = new DateTime('@' . $fallback_scheduled);
    $fallback_dt->setTimezone(new DateTimeZone('Europe/Zagreb'));
    echo "Daily fallback cron: Scheduled\n";
    echo "Next run: " . $fallback_dt->format('Y-m-d H:i:s') . " Zagreb\n";
} else {
    echo "Daily fallback cron: NOT SCHEDULED\n";
    echo "💡 This should be automatically scheduled. Check initialization.\n";
}
echo "\n";

echo "=== DIAGNOSTIC COMPLETE ===\n";
