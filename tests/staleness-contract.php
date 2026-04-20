<?php
/**
 * Staleness guard contract test.
 *
 * Tests Shaped_RC_Availability_Manager::is_stale() with the real implementation.
 * No WordPress, no PHPUnit — minimal stubs only.
 *
 * Run:  php tests/staleness-contract.php
 * Exit: 0 = all pass, 1 = any fail
 *
 * Coverage:
 *   1. last_update null  → stale
 *   2. last_update 1h ago → fresh
 *   3. last_update 25h ago → stale
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

// ── Option stub ───────────────────────────────────────────────────────────────

$_test_inventory_meta = [];

function get_option(string $option, $default = false): mixed {
    global $_test_inventory_meta;
    if ($option === 'shaped_rc_inventory_meta') {
        return $_test_inventory_meta;
    }
    return $default;
}

function update_option(): void {}

// ── WP function stubs (only needed so the class file parses cleanly) ──────────

function add_action(): void {}
function add_filter(): void {}
function sanitize_title(string $s): string { return $s; }
function wp_timezone(): DateTimeZone { return new DateTimeZone('UTC'); }
function current_time(): string { return date('Y-m-d H:i:s'); }
function get_posts(): array { return []; }
function get_post_field(): mixed { return ''; }
function get_the_title(): string { return ''; }
function is_admin(): bool { return false; }

class WP_Post {}

// Stub logger so log_critical calls don't crash if ever reached.
class Shaped_RC_Error_Logger {
    public static function log_critical(): void {}
    public static function log_info(): void {}
}

// ── Load the real availability manager ────────────────────────────────────────

require_once __DIR__ . '/../modules/roomcloud/includes/class-availability-manager.php';

// ── Assertion harness ─────────────────────────────────────────────────────────

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond, mixed $actual = null): void {
    global $pass, $fail;
    if ($cond) {
        echo "  \e[32m✓\e[0m {$label}\n";
        $pass++;
        return;
    }
    echo "  \e[31m✗\e[0m {$label}";
    if ($actual !== null) {
        echo ' → got ' . json_encode($actual);
    }
    echo "\n";
    $fail++;
}

function eq(string $label, mixed $actual, mixed $expected): void {
    ok($label, $actual === $expected, $actual);
}

// ── Case 1: null last_update → stale ─────────────────────────────────────────

echo "\nCase 1: null last_update → is_stale() = true\n";

$_test_inventory_meta = [];
eq('is_stale() with no meta = true', Shaped_RC_Availability_Manager::is_stale(), true);

// ── Case 2: last_update 1h ago → fresh ────────────────────────────────────────

echo "\nCase 2: last_update 1h ago → is_stale() = false\n";

$_test_inventory_meta = ['last_update' => date('Y-m-d H:i:s', time() - HOUR_IN_SECONDS + 60)];
eq('is_stale() with recent update = false', Shaped_RC_Availability_Manager::is_stale(), false);

// ── Case 3: last_update 25h ago → stale ──────────────────────────────────────

echo "\nCase 3: last_update 25h ago → is_stale() = true\n";

$_test_inventory_meta = ['last_update' => date('Y-m-d H:i:s', time() - 25 * HOUR_IN_SECONDS)];
eq('is_stale() with 25h-old update = true', Shaped_RC_Availability_Manager::is_stale(), true);

// ── Summary ───────────────────────────────────────────────────────────────────

echo "\n";
if ($fail === 0) {
    echo "\e[32mPASS\e[0m — {$pass} assertions\n\n";
    exit(0);
}
echo "\e[31mFAIL\e[0m — {$fail} failed, {$pass} passed\n\n";
exit(1);
