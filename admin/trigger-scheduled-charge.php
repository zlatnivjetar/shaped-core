<?php
/**
 * Manual Trigger for Scheduled Charge
 *
 * Usage: wp eval-file admin/trigger-scheduled-charge.php <booking_id>
 *
 * This script manually triggers the scheduled charge for a booking.
 * Use this when WP-Cron fails to execute on time.
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

// Get booking ID from command line args or GET parameter
$booking_id = null;
if (defined('WP_CLI') && WP_CLI) {
    global $argv;
    $booking_id = isset($argv[1]) ? intval($argv[1]) : null;
} else {
    $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : null;
}

if (!$booking_id) {
    die("ERROR: Please provide a booking ID\n\nUsage: wp eval-file admin/trigger-scheduled-charge.php <booking_id>\n");
}

echo "=== MANUAL CHARGE TRIGGER ===\n\n";
echo "Booking ID: #$booking_id\n";

// Load booking
$booking = MPHB()->getBookingRepository()->findById($booking_id);
if (!$booking) {
    die("ERROR: Booking #$booking_id not found\n");
}

echo "Status: " . $booking->getStatus() . "\n";
echo "Check-in: " . $booking->getCheckInDate()->format('Y-m-d') . "\n\n";

// Check payment status
$payment_status = get_post_meta($booking_id, '_shaped_payment_status', true);
$charge_processed = get_post_meta($booking_id, '_shaped_charge_processed', true);
$charge_at = get_post_meta($booking_id, '_shaped_charge_at', true);

echo "Current payment status: " . ($payment_status ?: 'N/A') . "\n";
echo "Charge processed: " . ($charge_processed ? 'Yes' : 'No') . "\n";
echo "Scheduled charge time: " . ($charge_at ?: 'N/A') . "\n\n";

// Validation
if ($payment_status !== 'authorized') {
    die("ERROR: Payment status is '$payment_status', expected 'authorized'. Cannot charge.\n");
}

if ($charge_processed) {
    die("ERROR: Charge already processed. Nothing to do.\n");
}

if ($charge_at && strtotime($charge_at) > time()) {
    $charge_dt = new DateTime($charge_at);
    $charge_dt->setTimezone(new DateTimeZone('Europe/Zagreb'));
    die("ERROR: Scheduled charge time hasn't arrived yet.\nScheduled for: " . $charge_dt->format('Y-m-d H:i:s') . " Zagreb\n");
}

// Get idempotency key
$idempotency_key = get_post_meta($booking_id, '_shaped_idempotency_key', true);
if (!$idempotency_key) {
    $idempotency_key = 'manual_' . $booking_id . '_' . time();
    echo "⚠ No idempotency key found, generating: $idempotency_key\n";
}

echo "Idempotency key: $idempotency_key\n\n";

// Confirm
echo "Ready to charge booking #$booking_id\n";
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::confirm("Proceed with charge?");
} else {
    echo "\n⚠ Manual confirmation required in CLI mode\n";
    echo "Add ?confirm=yes to URL to proceed\n";
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        exit;
    }
}

echo "\n--- EXECUTING CHARGE ---\n\n";

// Get the payment processor
$processor = Shaped_Payment_Processor::get_instance();

if (!$processor || !method_exists($processor, 'charge_single_booking')) {
    die("ERROR: Payment processor not available\n");
}

// Execute charge
try {
    $processor->charge_single_booking($booking_id, $idempotency_key);
    echo "\n✓ Charge execution completed\n\n";
} catch (\Throwable $e) {
    die("\n✗ ERROR: " . $e->getMessage() . "\n");
}

// Check result
$charge_processed_after = get_post_meta($booking_id, '_shaped_charge_processed', true);
$payment_status_after = get_post_meta($booking_id, '_shaped_payment_status', true);
$payment_intent_id = get_post_meta($booking_id, '_stripe_payment_intent_id', true);

echo "Result:\n";
echo "  Payment status: " . ($payment_status_after ?: 'N/A') . "\n";
echo "  Charge processed: " . ($charge_processed_after ? 'Yes' : 'No') . "\n";
echo "  Payment Intent ID: " . ($payment_intent_id ?: 'N/A') . "\n\n";

if ($payment_status_after === 'completed' && $charge_processed_after) {
    echo "✓✓✓ SUCCESS! Charge completed successfully.\n";
} elseif ($payment_status_after === 'charge_failed') {
    echo "✗✗✗ FAILED! Charge was declined or failed. Check error logs.\n";
} else {
    echo "⚠ Unexpected status. Check error logs for details.\n";
}

echo "\n=== CHECK STRIPE DASHBOARD ===\n";
echo "Verify the charge in your Stripe dashboard.\n\n";
