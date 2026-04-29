<?php
/**
 * Dashboard revenue contract test.
 *
 * Self-contained: no WordPress, no PHPUnit, no network.
 * Run: php tests/dashboard-revenue-contract.php
 */

define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');

class WP_Error
{
}

class WP_REST_Request
{
}

class WP_REST_Response
{
}

class Fake_Dashboard_WPDB
{
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $last_query = '';
    public array $last_args = [];

    public function prepare(string $query, $args = []): string
    {
        $this->last_args = is_array($args) ? $args : array_slice(func_get_args(), 1);

        return $query;
    }

    public function get_var(string $query): string
    {
        $this->last_query = $query;

        return '0';
    }

    public function get_results(string $query, $output = null): array
    {
        $this->last_query = $query;

        return [];
    }

    public function get_row(string $query, $output = null): ?array
    {
        $this->last_query = $query;

        return null;
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }
}

function wp_cache_get($key, $group = '')
{
    return false;
}

function wp_cache_set($key, $value, $group = '', $expiration = 0): bool
{
    return true;
}

function wp_timezone(): DateTimeZone
{
    return new DateTimeZone('Europe/Warsaw');
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

function esc_sql(string $value): string
{
    return addslashes($value);
}

global $wpdb;
$wpdb = new Fake_Dashboard_WPDB();

require_once __DIR__ . '/../includes/class-dashboard-data-service.php';

function invoke_dashboard_private(string $method, array $args = [])
{
    $reflection = new ReflectionMethod('Shaped_Dashboard_Data_Service', $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs(null, $args);
}

function assert_contains_fragment(string $needle, string $haystack, string $label): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: {$label}\nMissing fragment: {$needle}\n");
        exit(1);
    }
}

function assert_not_contains_fragment(string $needle, string $haystack, string $label): void
{
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "Assertion failed: {$label}\nUnexpected fragment: {$needle}\n");
        exit(1);
    }
}

function assert_same_value($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: {$label}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

invoke_dashboard_private('get_revenue_total', ['collected', '2026-04-01', '2026-04-30']);
$collected_query = $wpdb->last_query;
assert_contains_fragment("_shaped_payment_collected_date", $collected_query, 'collected revenue reads collection date meta');
assert_contains_fragment("bookings.payment_collected_date >= %s", $collected_query, 'collected revenue lower bound uses collection date');
assert_contains_fragment("bookings.payment_collected_date <= %s", $collected_query, 'collected revenue upper bound uses collection date');
assert_contains_fragment("bookings.payment_collected_date IS NOT NULL", $collected_query, 'collected revenue requires known collection date');
assert_contains_fragment("bookings.payment_status IN ('completed', 'deposit_paid')", $collected_query, 'collected revenue includes completed and deposit-paid statuses');
assert_contains_fragment("_mphb_paid_amount", $collected_query, 'collected revenue can use actual paid amount');
assert_contains_fragment("_shaped_deposit_amount", $collected_query, 'collected revenue can use deposit amount');
assert_not_contains_fragment("DATE(p.post_date)", $collected_query, 'collected revenue does not fall back to booked date');

invoke_dashboard_private('get_revenue_total', ['pending', '2026-04-01', '2026-04-30']);
$pending_query = $wpdb->last_query;
assert_contains_fragment("DATE(COALESCE(NULLIF(charge_date.meta_value, ''), charge_at.meta_value)) >= %s", $pending_query, 'pending revenue lower bound uses charge date');
assert_contains_fragment("DATE(COALESCE(NULLIF(charge_date.meta_value, ''), charge_at.meta_value)) <= %s", $pending_query, 'pending revenue upper bound uses charge date');
assert_not_contains_fragment("DATE(p.post_date) >= %s", $pending_query, 'pending revenue does not filter by booked date');

invoke_dashboard_private('query_collected_revenue_trend', [[
    'collected_from' => '2026-04-01',
    'collected_to' => '2026-04-30',
]]);
$trend_query = $wpdb->last_query;
assert_contains_fragment("bookings.payment_collected_date AS collected_date", $trend_query, 'collected trend groups on collection date');
assert_contains_fragment("COALESCE(SUM(", $trend_query, 'collected trend sums collected amount');
assert_contains_fragment("GROUP BY bookings.payment_collected_date", $trend_query, 'collected trend group by collection date');
assert_contains_fragment("bookings.payment_collected_date IS NOT NULL", $trend_query, 'collected trend requires known collection date');
assert_not_contains_fragment("DATE(p.post_date)", $trend_query, 'collected trend does not fall back to booked date');

$item = invoke_dashboard_private('build_booking_list_item', [[
    'ID' => 123,
    'guest_first_name' => 'Ada',
    'guest_last_name' => 'Lovelace',
    'guest_email' => 'ada@example.test',
    'check_in' => '2026-05-01',
    'check_out' => '2026-05-03',
    'booking_status_raw' => 'confirmed',
    'payment_status_raw' => 'completed',
    'payment_type' => 'full',
    'payment_mode_raw' => 'delayed',
    'total_amount' => '3903.20',
    'deposit_amount' => '0',
    'balance_due' => '0',
    'paid_amount' => '3903.20',
    'stripe_pending_amount' => '3903.20',
    'charge_scheduled' => '',
    'charge_date' => '2026-04-26',
    'charge_at' => '2026-04-26T14:00:00Z',
    'charge_processed' => '1',
    'stripe_charged' => '1',
    'payment_collected_at' => '2026-04-28 10:34:56',
    'payment_collected_date' => '2026-04-28',
    'booked_at' => '2026-04-16 09:00:00',
    'booked_at_gmt' => '2026-04-16 07:00:00',
]]);

assert_same_value('2026-04-28', $item['payment_collected_date'], 'booking list exposes collection date');
assert_same_value('2026-04-28T12:34:56+02:00', $item['payment_collected_at'], 'booking list exposes collection timestamp in site timezone');

$missing_delayed_collection_item = invoke_dashboard_private('build_booking_list_item', [[
    'ID' => 124,
    'guest_first_name' => 'Bruce',
    'guest_last_name' => 'Brightman',
    'guest_email' => 'bruce@example.test',
    'check_in' => '2026-05-02',
    'check_out' => '2026-05-30',
    'booking_status_raw' => 'confirmed',
    'payment_status_raw' => 'completed',
    'payment_type' => 'full',
    'payment_mode_raw' => 'delayed',
    'total_amount' => '3903.20',
    'deposit_amount' => '0',
    'balance_due' => '0',
    'paid_amount' => '3903.20',
    'stripe_pending_amount' => '3903.20',
    'charge_scheduled' => '',
    'charge_date' => '2026-04-25',
    'charge_at' => '2026-04-25T14:00:00Z',
    'charge_processed' => '1',
    'stripe_charged' => '1',
    'payment_collected_at' => '',
    'payment_collected_date' => '',
    'booked_at' => '2026-04-16 09:00:00',
    'booked_at_gmt' => '2026-04-16 07:00:00',
]]);

assert_same_value(null, $missing_delayed_collection_item['payment_collected_date'], 'delayed booking without collection metadata does not fall back to booked date');
assert_same_value(null, $missing_delayed_collection_item['payment_collected_at'], 'delayed booking without collection metadata does not expose booked time as collection time');

echo "Dashboard revenue contract tests passed.\n";
