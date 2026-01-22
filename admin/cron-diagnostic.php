<?php
/**
 * Real Cron Diagnostic Tool
 *
 * For sites with DISABLE_WP_CRON = true and real cron running
 *
 * Usage:
 *   Via browser: yourdomain.com/wp-admin/admin.php?page=cron-diagnostic
 *   Or create admin page to load this
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

echo "<pre>\n";
echo "=== REAL CRON + SCHEDULED CHARGES DIAGNOSTIC ===\n\n";

// Check WP-Cron status
echo "--- WP-CRON CONFIGURATION ---\n";
echo "DISABLE_WP_CRON: " . (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'TRUE (correct for real cron)' : 'FALSE') . "\n";
echo "doing_cron: " . (defined('DOING_CRON') && DOING_CRON ? 'TRUE (cron is running now)' : 'FALSE') . "\n\n";

// Current time
$now = time();
$now_dt = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
echo "Current time: " . date('Y-m-d H:i:s', $now) . " UTC\n";
echo "Current time: " . $now_dt->format('Y-m-d H:i:s') . " Zagreb\n\n";

// Check all scheduled events
echo "--- ALL SHAPED CRON EVENTS ---\n";
$cron_array = _get_cron_array();

$shaped_events = [];
foreach ($cron_array as $timestamp => $cron) {
    foreach ($cron as $hook => $events) {
        if (strpos($hook, 'shaped_') === 0) {
            $shaped_events[$hook][] = [
                'timestamp' => $timestamp,
                'events' => $events,
            ];
        }
    }
}

if (empty($shaped_events)) {
    echo "⚠ WARNING: No 'shaped_*' cron events found!\n\n";
} else {
    foreach ($shaped_events as $hook => $occurrences) {
        echo "Hook: $hook\n";
        foreach ($occurrences as $occurrence) {
            $ts = $occurrence['timestamp'];
            $dt = new DateTime('@' . $ts);
            $dt->setTimezone(new DateTimeZone('Europe/Zagreb'));

            $status = ($ts <= $now) ? '⚠ PAST DUE' : '✓ Scheduled';
            $diff_hours = round(($ts - $now) / 3600, 1);

            echo "  Scheduled: " . $dt->format('Y-m-d H:i:s') . " Zagreb";
            echo " ($status, " . ($diff_hours > 0 ? "in $diff_hours hours" : abs($diff_hours) . " hours ago") . ")\n";

            foreach ($occurrence['events'] as $event) {
                if (!empty($event['args'])) {
                    echo "    Args: " . json_encode($event['args']) . "\n";
                }
            }
        }
        echo "\n";
    }
}

// Check authorized bookings
echo "--- BOOKINGS WITH AUTHORIZED STATUS ---\n";
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
    echo "No authorized bookings found.\n\n";
} else {
    echo "Found " . count($bookings) . " booking(s):\n\n";

    foreach ($bookings as $booking_post) {
        $booking_id = $booking_post->ID;
        $booking = MPHB()->getBookingRepository()->findById($booking_id);

        if (!$booking) continue;

        $check_in = $booking->getCheckInDate();
        $charge_at = get_post_meta($booking_id, '_shaped_charge_at', true);
        $charge_scheduled = get_post_meta($booking_id, '_shaped_charge_scheduled', true);
        $charge_processed = get_post_meta($booking_id, '_shaped_charge_processed', true);
        $idempotency_key = get_post_meta($booking_id, '_shaped_idempotency_key', true);

        echo "Booking #$booking_id:\n";
        echo "  Check-in: " . $check_in->format('Y-m-d') . "\n";
        echo "  Charge scheduled meta: " . ($charge_scheduled ? 'Yes' : 'No') . "\n";
        echo "  Charge processed: " . ($charge_processed ? 'Yes' : 'No') . "\n";

        if ($charge_at) {
            $charge_dt = new DateTime($charge_at, new DateTimeZone('UTC'));
            $charge_dt_zagreb = clone $charge_dt;
            $charge_dt_zagreb->setTimezone(new DateTimeZone('Europe/Zagreb'));
            $charge_ts = $charge_dt->getTimestamp();

            echo "  Charge at: " . $charge_dt_zagreb->format('Y-m-d H:i:s') . " Zagreb\n";

            if ($charge_ts <= $now) {
                $hours_late = round(($now - $charge_ts) / 3600, 1);
                echo "  ⚠ PAST DUE by $hours_late hours\n";
            } else {
                $hours_until = round(($charge_ts - $now) / 3600, 1);
                echo "  ✓ Future (in $hours_until hours)\n";
            }

            // Check if cron event exists
            $cron_exists = wp_next_scheduled('shaped_charge_single_booking', [$booking_id, $idempotency_key]);
            if ($cron_exists) {
                echo "  Cron event: EXISTS (scheduled for " . date('Y-m-d H:i:s', $cron_exists) . ")\n";
            } else {
                echo "  ⚠ Cron event: MISSING! This is the problem!\n";
            }
        } else {
            echo "  ⚠ No charge time set\n";
        }

        echo "\n";
    }
}

// Check daily fallback
echo "--- DAILY FALLBACK STATUS ---\n";
$fallback_next = wp_next_scheduled('shaped_daily_charge_fallback');
if ($fallback_next) {
    $fb_dt = new DateTime('@' . $fallback_next);
    $fb_dt->setTimezone(new DateTimeZone('Europe/Zagreb'));
    echo "✓ Scheduled for: " . $fb_dt->format('Y-m-d H:i:s') . " Zagreb\n";

    if ($fallback_next <= $now) {
        echo "⚠ Should have run already\n";
    }
} else {
    echo "⚠ NOT SCHEDULED! This is a problem - no safety net.\n";
    echo "💡 FIX: Visit any page on your site to trigger init hooks\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
echo "</pre>";
