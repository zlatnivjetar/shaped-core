<?php
/**
 * Pricing engine override contract test.
 *
 * Self-contained: no WordPress, no PHPUnit, no network.
 * Run: php tests/pricing-engine-overrides-contract.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}

$_test_options = [
    'shaped_discounts' => [
        'suite' => 5,
        'apartment' => 0,
    ],
    'shaped_discount_seasons' => [
        'recurring' => [
            [
                'start_day' => '05-01',
                'end_day'   => '05-31',
                'label'     => 'May',
                'discounts' => [
                    'suite' => 10,
                    'apartment' => 0,
                ],
            ],
        ],
        'overrides' => [
            [
                'start_date' => '2026-05-10',
                'end_date'   => '2026-05-10',
                'label'      => 'Manual lock',
                'discounts'  => [
                    'suite' => 20,
                    'apartment' => 0,
                ],
            ],
        ],
    ],
    'shaped_engine_discount_overrides' => [
        'version' => 1,
        'generated_at' => '2026-04-20T08:00:00Z',
        'run_id' => '22222222-2222-2222-2222-222222222222',
        'overrides' => [
            'suite' => [
                '2026-05-11' => 12,
                '2026-05-12' => 0,
            ],
            'apartment' => [
                '2026-07-01' => 30,
            ],
        ],
    ],
];

function get_option(string $key, mixed $default = false): mixed {
    global $_test_options;
    return array_key_exists($key, $_test_options) ? $_test_options[$key] : $default;
}

function update_option(string $key, mixed $value): bool {
    global $_test_options;
    $_test_options[$key] = $value;
    return true;
}

function wp_parse_args(mixed $args, array $defaults = []): array {
    return array_merge($defaults, is_array($args) ? $args : []);
}

function sanitize_title(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function sanitize_text_field(string $value): string {
    return trim(strip_tags($value));
}

function apply_filters(string $hook, mixed $value): mixed {
    return $value;
}

function get_posts(array $args): array {
    if (($args['post_type'] ?? '') !== 'mphb_room_type') {
        return [];
    }

    return [
        (object) [
            'post_title' => 'Suite',
        ],
        (object) [
            'post_title' => 'Apartment',
        ],
    ];
}

class WP_REST_Response {
    public function __construct(public readonly mixed $data, public readonly int $status = 200) {}
}

class WP_Error {
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly array $data = []
    ) {}
}

class WP_REST_Request {
    public function __construct(private readonly mixed $json_params) {}

    public function get_json_params(): mixed {
        return $this->json_params;
    }
}

require_once __DIR__ . '/../core/class-pricing.php';
require_once __DIR__ . '/../includes/class-dashboard-pricing-api.php';

$pass = 0;
$fail = 0;

function ok(string $label, bool $condition, mixed $actual = null): void {
    global $pass, $fail;
    if ($condition) {
        echo "  \e[32mPASS\e[0m {$label}\n";
        $pass++;
        return;
    }

    echo "  \e[31mFAIL\e[0m {$label}";
    if ($actual !== null) {
        echo ' got ' . json_encode($actual);
    }
    echo "\n";
    $fail++;
}

function eq(string $label, mixed $actual, mixed $expected): void {
    ok($label, $actual === $expected, $actual);
}

echo "\nPricing precedence with engine overrides\n";

eq(
    'manual date override wins over engine/defaults',
    Shaped_Pricing::get_room_discount_for_date('suite', '2026-05-10'),
    20
);

eq(
    'engine date override wins over recurring season',
    Shaped_Pricing::get_room_discount_for_date('suite', '2026-05-11'),
    12
);

eq(
    'engine zero override is honored as an explicit override',
    Shaped_Pricing::get_room_discount_for_date('suite', '2026-05-12'),
    0
);

eq(
    'recurring season applies when there is no manual or engine override',
    Shaped_Pricing::get_room_discount_for_date('suite', '2026-05-13'),
    10
);

eq(
    'default applies outside date-specific and recurring rules',
    Shaped_Pricing::get_room_discount_for_date('suite', '2026-07-01'),
    5
);

eq(
    'range lookup preserves manual date override precedence',
    Shaped_Pricing::get_room_discount_for_range('suite', '2026-05-10', '2026-05-12'),
    20
);

eq(
    'range lookup applies engine override precedence before recurring/defaults',
    Shaped_Pricing::get_room_discount_for_range('suite', '2026-05-11', '2026-05-13'),
    0
);

eq(
    'range lookup does not extend an engine override over a lower default night',
    Shaped_Pricing::get_room_discount_for_range('apartment', '2026-07-01', '2026-07-03'),
    0
);

echo "\nEngine override REST contract\n";

$update = Shaped_Dashboard_Pricing_Api::update_engine_discount_overrides(
    new WP_REST_Request([
        'version' => 1,
        'generated_at' => '2026-04-20T09:00:00Z',
        'run_id' => '33333333-3333-3333-3333-333333333333',
        'overrides' => [
            'Suite' => [
                '2026-05-14' => 7,
                'not-a-date' => 99,
            ],
        ],
    ])
);

ok('PUT endpoint returns response', $update instanceof WP_REST_Response);
eq('PUT endpoint status = 200', $update->status, 200);
eq(
    'PUT endpoint sanitizes room/date map',
    get_option('shaped_engine_discount_overrides')['overrides'],
    ['suite' => ['2026-05-14' => 7]]
);

$get = Shaped_Dashboard_Pricing_Api::get_engine_discount_overrides();
eq(
    'GET endpoint returns current option',
    $get->data['engine_discount_overrides']['overrides'],
    ['suite' => ['2026-05-14' => 7]]
);

echo "\n";
if ($fail === 0) {
    echo "\e[32mPASS\e[0m - {$pass} assertions\n\n";
    exit(0);
}

echo "\e[31mFAIL\e[0m - {$fail} failed, {$pass} passed\n\n";
exit(1);
