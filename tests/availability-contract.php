<?php
/**
 * Availability API contract test.
 *
 * Self-contained — no WordPress, no PHPUnit, no network.
 * Provides minimal stubs, exercises the real service class.
 *
 * Run:  php tests/availability-contract.php
 * Exit: 0 = all pass, 1 = any fail
 *
 * Coverage:
 *   1. All four cell states — available / low / full / no_data
 *   2. KPI computation when the range has complete data
 *   3. KPI fallback to null when any range cell is no_data
 *   4. Room types are sorted consistently by name regardless of DB return order
 */

declare(strict_types=1);

// ── WordPress stubs ────────────────────────────────────────────────────────

if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}

function wp_timezone(): DateTimeZone {
    return new DateTimeZone('Europe/Zagreb');
}

class WP_Error {
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly array  $data = []
    ) {}
}

function is_wp_error(mixed $v): bool {
    return $v instanceof WP_Error;
}

class WP_Post {
    public int    $ID;
    public string $post_name;
    public string $post_title;

    public function __construct(int $id, string $slug, string $title) {
        $this->ID         = $id;
        $this->post_name  = $slug;
        $this->post_title = $title;
    }
}

// ── Test state (swapped between cases) ────────────────────────────────────

$_test_room_specs  = [];  // [['id', 'slug', 'title', 'rc_id', 'unit_count'], ...]
$_test_inventory   = [];  // [rc_id => [date => int], ...]

function get_posts(array $args): array {
    global $_test_room_specs;

    if ($args['post_type'] === 'mphb_room_type') {
        $out = [];
        foreach ($_test_room_specs as $s) {
            $out[] = new WP_Post($s['id'], $s['slug'], $s['title']);
        }
        return $out;
    }

    if ($args['post_type'] === 'mphb_room') {
        $rt_id = (int) ($args['meta_query'][0]['value'] ?? 0);
        foreach ($_test_room_specs as $s) {
            if ($s['id'] === $rt_id) {
                return range(1, $s['unit_count']);
            }
        }
    }

    return [];
}

class Shaped_RC_Availability_Manager {
    public static function get_inventory(): array {
        global $_test_inventory;
        return $_test_inventory;
    }

    public static function get_roomcloud_id_for_room_type(int $id): ?string {
        global $_test_room_specs;
        foreach ($_test_room_specs as $s) {
            if ($s['id'] === $id) {
                return $s['rc_id'];
            }
        }
        return null;
    }

    public static function get_last_inventory_update(): ?string {
        return '2026-04-01 08:00:00';
    }

    public static function is_stale(): bool {
        return false;
    }

    public static function get_inventory_coverage(array $window_dates = []): array {
        global $_test_room_specs, $_test_inventory;

        $coverage = [];
        sort($window_dates);

        foreach ($_test_room_specs as $s) {
            $room_inventory = isset($_test_inventory[$s['rc_id']]) && is_array($_test_inventory[$s['rc_id']])
                ? $_test_inventory[$s['rc_id']]
                : [];

            $stored_dates = array_keys($room_inventory);
            sort($stored_dates);

            $coverage[$s['slug']] = [
                'room_type_id' => $s['id'],
                'roomcloud_id' => $s['rc_id'],
                'mapped' => $s['rc_id'] !== null,
                'first_stored_date' => $stored_dates[0] ?? null,
                'last_stored_date' => !empty($stored_dates) ? $stored_dates[count($stored_dates) - 1] : null,
                'stored_dates_count' => count($stored_dates),
                'window_start_date' => $window_dates[0] ?? null,
                'window_end_date' => !empty($window_dates) ? $window_dates[count($window_dates) - 1] : null,
                'window_dates_count' => count($window_dates),
                'window_covered_dates_count' => 0,
                'window_missing_dates_count' => 0,
                'window_has_gaps' => false,
            ];

            foreach ($window_dates as $date_str) {
                if (array_key_exists($date_str, $room_inventory)) {
                    $coverage[$s['slug']]['window_covered_dates_count']++;
                } else {
                    $coverage[$s['slug']]['window_missing_dates_count']++;
                    $coverage[$s['slug']]['window_has_gaps'] = true;
                }
            }
        }

        return $coverage;
    }
}

// ── Load the real service ──────────────────────────────────────────────────

require_once __DIR__ . '/../includes/class-dashboard-availability-service.php';

// ── Assertion harness ──────────────────────────────────────────────────────

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
        echo " → got " . json_encode($actual);
    }
    echo "\n";
    $fail++;
}

function eq(string $label, mixed $actual, mixed $expected): void {
    ok($label, $actual === $expected, $actual);
}

// ── Build date-relative fixtures ──────────────────────────────────────────
//
// All fixtures are pinned relative to today so the next-7-days window always
// has complete data regardless of when this script runs.

$tz    = new DateTimeZone('Europe/Zagreb');
$today = new DateTime('today', $tz);

$d = static function (int $offset) use ($today, $tz): string {
    return (clone $today)->modify("{$offset} days")->format('Y-m-d');
};

$month = $today->format('Y-m');

//
// COMPLETE_INVENTORY: today+0..+9 with four distinct availability values.
//   today+0 → 2  (available)
//   today+1 → 1  (low)
//   today+2 → 0  (full)
//   today+3..+9 → 2  (available — keeps next-7-days fully covered)
//
$complete_inv = ['RC001' => []];
for ($i = 0; $i <= 9; $i++) {
    $complete_inv['RC001'][$d($i)] = match ($i) {
        1 => 1,
        2 => 0,
        default => 2,
    };
}

//
// PARTIAL_INVENTORY: today+0..+2 only — today+3 is intentionally absent.
//
$partial_inv = ['RC001' => [
    $d(0) => 2,
    $d(1) => 1,
    $d(2) => 0,
]];

// ── Case 1: Four cell states + complete KPI computation ───────────────────

echo "\nCase 1: four cell states + KPI computation (complete data)\n";

$_test_room_specs = [[
    'id'         => 1,
    'slug'       => 'alpha-room',
    'title'      => 'Alpha Room',
    'rc_id'      => 'RC001',
    'unit_count' => 2,
]];
$_test_inventory = $complete_inv;

// Range: today to today+6 (7 days, all in complete_inv)
$r = Shaped_Dashboard_Availability_Service::get_availability($month, $d(0), $d(6));

ok('no WP_Error', !is_wp_error($r));
ok('meta.is_stale key exists', array_key_exists('is_stale', $r['meta']));
eq('meta.is_stale = false (stub)', $r['meta']['is_stale'], false);

$by_date = array_column($r['room_types'][0]['dates'], null, 'date');

eq("state available  (units=2, date {$d(0)})", $by_date[$d(0)]['state'],            'available');
eq("state low        (units=1, date {$d(1)})", $by_date[$d(1)]['state'],            'low');
eq("state full       (units=0, date {$d(2)})", $by_date[$d(2)]['state'],            'full');
eq("available_units preserved → 2",             $by_date[$d(0)]['available_units'], 2);
eq("available_units preserved → 1",             $by_date[$d(1)]['available_units'], 1);
eq("available_units preserved → 0",             $by_date[$d(2)]['available_units'], 0);

// has_no_data must be false — complete_inv covers the range and next-7 window
eq('has_no_data = false', $r['summary']['has_no_data'], false);
eq('coverage window starts at month start', $r['coverage']['visible_window']['start_date'], (new DateTime($month . '-01', $tz))->format('Y-m-d'));
eq('coverage window ends at month + 2 end', $r['coverage']['visible_window']['end_date'], (new DateTime($month . '-01', $tz))->modify('+2 months')->modify('last day of this month')->format('Y-m-d'));
$visible_start = new DateTime($month . '-01', $tz);
$visible_end = (clone $visible_start)->modify('+2 months')->modify('last day of this month');
eq('coverage total dates = visible matrix length', $r['coverage']['visible_window']['total_dates'], $visible_start->diff($visible_end)->days + 1);
eq('room coverage first stored date = today', $r['room_types'][0]['coverage']['first_stored_date'], $d(0));
eq('room coverage last stored date = today+9', $r['room_types'][0]['coverage']['last_stored_date'], $d(9));
eq('room coverage stored dates count = 10', $r['room_types'][0]['coverage']['stored_dates_count'], 10);
eq('room coverage has gaps in visible window = true', $r['room_types'][0]['coverage']['window_has_gaps'], true);

// KPI 1: occupancy in range
// total = 2 units × 7 days = 14 unit-nights
// open  = 2+1+0+2+2+2+2   = 11 unit-nights
// occ   = (14−11)/14 × 100 = 21.4 %
eq('occupancy_percent_in_range = 21.4', $r['summary']['occupancy_percent_in_range'], 21.4);

// KPI 2: open next-7-days = 2+1+0+2+2+2+2 = 11
eq('open_inventory_next_7_days = 11', $r['summary']['open_inventory_next_7_days'], 11);

// KPI 3: weakest room type (only one)
$wrt = $r['summary']['weakest_room_type'];
ok('weakest_room_type not null',             $wrt !== null);
eq('weakest_room_type slug = alpha-room',    $wrt['room_type_slug'],    'alpha-room');
eq('weakest_room_type occ  = 21.4',         $wrt['occupancy_percent'], 21.4);

// KPI 4: weakest 7-day stretch (7-day range → exactly one window = same as overall)
$w7 = $r['summary']['weakest_seven_day_window'];
ok('weakest_seven_day_window not null',    $w7 !== null);
eq('weakest_7day start = today+0',         $w7['start_date'],        $d(0));
eq('weakest_7day end   = today+6',         $w7['end_date'],          $d(6));
eq('weakest_7day occ   = 21.4',            $w7['occupancy_percent'], 21.4);

// Dates in month matrix must be ascending
$dates   = array_column($r['room_types'][0]['dates'], 'date');
$sorted  = $dates;
sort($sorted);
eq('month dates are ascending', $dates, $sorted);
eq('visible matrix starts at month start', $dates[0], (new DateTime($month . '-01', $tz))->format('Y-m-d'));
eq(
    'visible matrix ends at month + 2 end',
    $dates[count($dates) - 1],
    (new DateTime($month . '-01', $tz))->modify('+2 months')->modify('last day of this month')->format('Y-m-d')
);

// ── Case 2: no_data cell state + KPI fallback ─────────────────────────────

echo "\nCase 2: no_data cell state + KPI fallback (incomplete range)\n";

$_test_inventory = $partial_inv;

// Range: today to today+3 — today+3 is absent from partial_inv → no_data
$r2      = Shaped_Dashboard_Availability_Service::get_availability($month, $d(0), $d(3));
$by2     = array_column($r2['room_types'][0]['dates'], null, 'date');

eq("state no_data (missing date {$d(3)})",     $by2[$d(3)]['state'],            'no_data');
eq("available_units = null for no_data cell",  $by2[$d(3)]['available_units'],  null);

eq('has_no_data = true',                       $r2['summary']['has_no_data'],                   true);
eq('coverage window has gaps = true for partial data', $r2['room_types'][0]['coverage']['window_has_gaps'], true);
eq('occupancy_percent_in_range = null',        $r2['summary']['occupancy_percent_in_range'],    null);
eq('open_inventory_next_7_days = null',        $r2['summary']['open_inventory_next_7_days'],    null);
eq('weakest_room_type = null',                 $r2['summary']['weakest_room_type'],             null);
eq('weakest_seven_day_window = null',          $r2['summary']['weakest_seven_day_window'],      null);

// Cell states still render correctly for the dates that do have data
eq("state available (d+0 in partial data)",    $by2[$d(0)]['state'], 'available');
eq("state low       (d+1 in partial data)",    $by2[$d(1)]['state'], 'low');
eq("state full      (d+2 in partial data)",    $by2[$d(2)]['state'], 'full');

// ── Case 3: deterministic sort order ──────────────────────────────────────

echo "\nCase 3: room types sorted by name regardless of get_posts return order\n";

// Specs provided in reverse alphabetical order — service must sort them.
$_test_room_specs = [
    ['id' => 2, 'slug' => 'zeta-room',  'title' => 'Zeta Room',  'rc_id' => null,   'unit_count' => 1],
    ['id' => 1, 'slug' => 'alpha-room', 'title' => 'Alpha Room', 'rc_id' => 'RC001', 'unit_count' => 2],
];
$_test_inventory = $complete_inv;

$r3 = Shaped_Dashboard_Availability_Service::get_availability($month, $d(0), $d(6));

eq('first room  = Alpha Room', $r3['room_types'][0]['name'], 'Alpha Room');
eq('second room = Zeta Room',  $r3['room_types'][1]['name'], 'Zeta Room');

// Unmapped room (Zeta) has no_data everywhere
$zeta_by_date = array_column($r3['room_types'][1]['dates'], null, 'date');
eq("unmapped room → no_data (date {$d(0)})",   $zeta_by_date[$d(0)]['state'],           'no_data');
eq("unmapped room → roomcloud_id null",         $r3['room_types'][1]['roomcloud_id'],    null);
eq('unmapped room coverage = null',             $r3['room_types'][1]['coverage'],        null);

// Presence of unmapped room causes has_no_data = true
eq('has_no_data = true when any room unmapped', $r3['summary']['has_no_data'], true);

// ── Summary ───────────────────────────────────────────────────────────────

echo "\n";
if ($fail === 0) {
    echo "\e[32mPASS\e[0m — {$pass} assertions\n\n";
    exit(0);
}
echo "\e[31mFAIL\e[0m — {$fail} failed, {$pass} passed\n\n";
exit(1);
