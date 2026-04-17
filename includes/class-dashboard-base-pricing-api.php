<?php
/**
 * Dashboard REST API - base pricing endpoints.
 *
 * Exposes a MotoPress-backed base pricing snapshot for the external dashboard.
 *
 * Namespace : shaped/v1
 * Auth      : X-Shaped-API-Key header via shaped_dashboard_auth()
 *
 * Endpoints:
 *   GET /dashboard/base-pricing - full base-pricing snapshot
 *   POST /dashboard/base-pricing/rates - create a supported rate
 *   PUT /dashboard/base-pricing/rates/{rate_id} - update a supported rate
 *   POST /dashboard/base-pricing/rates/{rate_id}/duplicate - duplicate a supported rate
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Dashboard_Base_Pricing_Api
{
    const NAMESPACE = 'shaped/v1';

    /**
     * Register hooks.
     */
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register routes.
     */
    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/dashboard/base-pricing', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_base_pricing'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);

        register_rest_route(self::NAMESPACE, '/dashboard/base-pricing/rates', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'create_rate'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);

        register_rest_route(self::NAMESPACE, '/dashboard/base-pricing/rates/(?P<rate_id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'update_rate'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);

        register_rest_route(self::NAMESPACE, '/dashboard/base-pricing/rates/(?P<rate_id>\d+)/duplicate', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'duplicate_rate'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);
    }

    /**
     * GET /dashboard/base-pricing
     *
     * Returns accommodation types, seasons, and rates normalized into a
     * dashboard-friendly snapshot. Unsupported rate structures are included but
     * flagged read-only instead of being flattened.
     *
     * @return WP_REST_Response|WP_Error
     */
    public static function get_base_pricing()
    {
        if (!function_exists('MPHB')) {
            return new WP_Error(
                'motopress_unavailable',
                'MotoPress Hotel Booking plugin is required.',
                ['status' => 503]
            );
        }

        $room_types = self::get_room_types();
        $rates_by_room_type = self::get_rates_grouped_by_room_type();

        $accommodation_types = array_map(
            static function ($room_type) use ($rates_by_room_type) {
                $room_type_id = (int) $room_type->getId();

                return [
                    'id'            => $room_type_id,
                    'title'         => $room_type->getTitle(),
                    'status'        => $room_type->getStatus(),
                    'base_adults'   => (int) $room_type->getBaseAdultsCapacity(),
                    'base_children' => (int) $room_type->getBaseChildrenCapacity(),
                    'rates'         => $rates_by_room_type[$room_type_id] ?? [],
                ];
            },
            $room_types
        );

        $seasons = array_map(
            [__CLASS__, 'build_season_payload'],
            self::get_seasons()
        );

        return new WP_REST_Response([
            'accommodation_types' => array_values($accommodation_types),
            'seasons'             => array_values($seasons),
            'generated_at'        => gmdate('c'),
        ], 200);
    }

    /**
     * POST /dashboard/base-pricing/rates
     *
     * Create a new supported rate for an accommodation type.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function create_rate(WP_REST_Request $request)
    {
        if (!function_exists('mphb_prices_facade')) {
            return new WP_Error(
                'motopress_unavailable',
                'MotoPress Hotel Booking plugin is required.',
                ['status' => 503]
            );
        }

        return self::save_rate_from_request($request, null);
    }

    /**
     * PUT /dashboard/base-pricing/rates/{rate_id}
     *
     * Update an existing supported rate.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function update_rate(WP_REST_Request $request)
    {
        if (!function_exists('mphb_prices_facade')) {
            return new WP_Error(
                'motopress_unavailable',
                'MotoPress Hotel Booking plugin is required.',
                ['status' => 503]
            );
        }

        $rate_id = (int) $request['rate_id'];

        return self::save_rate_from_request($request, $rate_id);
    }

    /**
     * POST /dashboard/base-pricing/rates/{rate_id}/duplicate
     *
     * Duplicate a supported rate without using the unsafe MotoPress repository
     * duplicate helper, which clears the linked room type.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function duplicate_rate(WP_REST_Request $request)
    {
        if (!function_exists('mphb_prices_facade')) {
            return new WP_Error(
                'motopress_unavailable',
                'MotoPress Hotel Booking plugin is required.',
                ['status' => 503]
            );
        }

        $source = self::get_rate_by_id((int) $request['rate_id']);
        if (is_wp_error($source)) {
            return $source;
        }

        $unsupported_reason = self::get_rate_unsupported_reason($source);
        if ($unsupported_reason !== null) {
            return new WP_Error(
                'unsupported_rate',
                $unsupported_reason,
                ['status' => 409]
            );
        }

        $body = $request->get_json_params();
        $body = is_array($body) ? $body : [];

        $title = isset($body['title']) && is_string($body['title'])
            ? sanitize_text_field($body['title'])
            : sprintf(__('%s - copy', 'motopress-hotel-booking'), $source->getTitle());

        $duplicate = self::build_rate_entity([
            'description'   => isset($body['description']) && is_string($body['description'])
                ? sanitize_textarea_field($body['description'])
                : $source->getDescription(),
            'id'            => null,
            'is_active'     => array_key_exists('is_active', $body)
                ? rest_sanitize_boolean($body['is_active'])
                : (bool) $source->isActive(),
            'room_type_id'  => (int) $source->getRoomTypeId(),
            'season_prices' => self::get_supported_rate_input($source),
            'title'         => $title,
        ]);

        if (is_wp_error($duplicate)) {
            return $duplicate;
        }

        $saved = mphb_prices_facade()->saveRate($duplicate);
        if (!$saved) {
            return new WP_Error(
                'rate_not_saved',
                'The rate could not be duplicated.',
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'duplicated_from_rate_id' => (int) $source->getId(),
            'rate'                    => self::build_rate_payload($duplicate),
            'updated_at'              => gmdate('c'),
        ], 201);
    }

    /**
     * Fetch all room types that should be visible to the dashboard.
     *
     * @return array
     */
    private static function get_room_types(): array
    {
        $room_types = MPHB()->getRoomTypeRepository()->findAll([
            'post_status' => self::get_editable_post_statuses(),
        ]);

        return is_array($room_types) ? $room_types : [];
    }

    /**
     * Fetch all seasons that should be visible to the dashboard.
     *
     * @return array
     */
    private static function get_seasons(): array
    {
        $seasons = MPHB()->getSeasonRepository()->findAll([
            'post_status' => self::get_editable_post_statuses(),
            'orderby'     => 'ID',
            'order'       => 'ASC',
        ]);

        return is_array($seasons) ? $seasons : [];
    }

    /**
     * Group normalized rates by room type ID.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function get_rates_grouped_by_room_type(): array
    {
        $rates = MPHB()->getRateRepository()->findAll([
            'post_status' => self::get_editable_post_statuses(),
        ]);

        if (!is_array($rates)) {
            return [];
        }

        $grouped = [];

        foreach ($rates as $rate) {
            $room_type_id = (int) $rate->getRoomTypeId();
            if ($room_type_id <= 0) {
                continue;
            }

            $grouped[$room_type_id][] = self::build_rate_payload($rate);
        }

        return $grouped;
    }

    /**
     * Create or update a supported rate from dashboard JSON input.
     *
     * @param WP_REST_Request $request
     * @param int|null        $existing_rate_id
     * @return WP_REST_Response|WP_Error
     */
    private static function save_rate_from_request(WP_REST_Request $request, ?int $existing_rate_id)
    {
        $existing_rate = null;

        if ($existing_rate_id !== null) {
            $existing_rate = self::get_rate_by_id($existing_rate_id);
            if (is_wp_error($existing_rate)) {
                return $existing_rate;
            }

            $unsupported_reason = self::get_rate_unsupported_reason($existing_rate);
            if ($unsupported_reason !== null) {
                return new WP_Error(
                    'unsupported_rate',
                    $unsupported_reason,
                    ['status' => 409]
                );
            }
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error(
                'bad_request',
                'Expected a JSON object in the request body.',
                ['status' => 400]
            );
        }

        $title = isset($body['title']) && is_string($body['title'])
            ? sanitize_text_field($body['title'])
            : ($existing_rate ? $existing_rate->getTitle() : '');

        if ($title === '') {
            return new WP_Error(
                'bad_request',
                'A non-empty "title" field is required.',
                ['status' => 400]
            );
        }

        $room_type_id = array_key_exists('room_type_id', $body)
            ? (int) $body['room_type_id']
            : ($existing_rate ? (int) $existing_rate->getRoomTypeId() : 0);

        if ($room_type_id <= 0 || !MPHB()->getRoomTypeRepository()->findById($room_type_id)) {
            return new WP_Error(
                'bad_request',
                'A valid "room_type_id" field is required.',
                ['status' => 400]
            );
        }

        if (!isset($body['season_prices']) || !is_array($body['season_prices']) || empty($body['season_prices'])) {
            return new WP_Error(
                'bad_request',
                'A non-empty "season_prices" array is required.',
                ['status' => 400]
            );
        }

        $rate = self::build_rate_entity([
            'description'   => isset($body['description']) && is_string($body['description'])
                ? sanitize_textarea_field($body['description'])
                : ($existing_rate ? $existing_rate->getDescription() : ''),
            'id'            => $existing_rate ? (int) $existing_rate->getId() : null,
            'is_active'     => array_key_exists('is_active', $body)
                ? rest_sanitize_boolean($body['is_active'])
                : ($existing_rate ? (bool) $existing_rate->isActive() : true),
            'room_type_id'  => $room_type_id,
            'season_prices' => $body['season_prices'],
            'title'         => $title,
        ]);

        if (is_wp_error($rate)) {
            return $rate;
        }

        $saved = mphb_prices_facade()->saveRate($rate);
        if (!$saved) {
            return new WP_Error(
                'rate_not_saved',
                'The rate could not be saved.',
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'rate'       => self::build_rate_payload($rate),
            'updated_at' => gmdate('c'),
        ], $existing_rate ? 200 : 201);
    }

    /**
     * Normalize a season entity for the dashboard contract.
     *
     * @param object $season MotoPress season entity.
     * @return array<string, mixed>
     */
    private static function build_season_payload($season): array
    {
        $repeat_until_date = $season->getRepeatUntilDate();

        return [
            'id'                => (int) $season->getId(),
            'title'             => $season->getTitle(),
            'description'       => $season->getDescription(),
            'start_date'        => self::format_date($season->getStartDate()),
            'end_date'          => self::format_date($season->getEndDate()),
            'repeat_period'     => (string) $season->getRepeatPeriod(),
            'repeat_until_date' => $repeat_until_date instanceof DateTimeInterface
                ? $repeat_until_date->format('Y-m-d')
                : null,
            'days'              => array_values(array_map('intval', $season->getDays())),
            'is_recurring'      => (bool) $season->isRecurring(),
        ];
    }

    /**
     * Normalize a rate entity for the dashboard contract.
     *
     * @param object $rate MotoPress rate entity.
     * @return array<string, mixed>
     */
    private static function build_rate_payload($rate): array
    {
        $unsupported_reason = self::get_rate_unsupported_reason($rate);
        $season_prices = array_map(
            [__CLASS__, 'build_season_price_payload'],
            array_reverse($rate->getSeasonPrices())
        );

        return [
            'id'                 => (int) $rate->getId(),
            'title'              => $rate->getTitle(),
            'description'        => $rate->getDescription(),
            'room_type_id'       => (int) $rate->getRoomTypeId(),
            'is_active'          => (bool) $rate->isActive(),
            'is_editable'        => $unsupported_reason === null,
            'unsupported_reason' => $unsupported_reason,
            'season_prices'      => array_values($season_prices),
        ];
    }

    /**
     * Build a supported input payload from an existing editable rate entity.
     *
     * @param object $rate MotoPress rate entity.
     * @return array<int, array<string, mixed>>
     */
    private static function get_supported_rate_input($rate): array
    {
        return array_values(array_map(
            [__CLASS__, 'build_season_price_payload'],
            array_reverse($rate->getSeasonPrices())
        ));
    }

    /**
     * Normalize a season-price row for the dashboard contract.
     *
     * @param object $season_price MotoPress season-price entity.
     * @return array<string, mixed>
     */
    private static function build_season_price_payload($season_price): array
    {
        $season = $season_price->getSeason();
        $pricing = $season_price->getPricesAndVariations();

        return [
            'priority'          => (int) $season_price->getId(),
            'season_id'         => (int) $season_price->getSeasonId(),
            'season_name'       => $season ? $season->getTitle() : null,
            'base_price'        => self::get_first_numeric_value($pricing['prices'] ?? [], 0.0),
            'base_adults'       => (int) $season_price->getBaseAdults(),
            'base_children'     => (int) $season_price->getBaseChildren(),
            'extra_adult_price' => self::get_first_numeric_value($pricing['extra_adult_prices'] ?? [], 0.0),
            'extra_child_price' => self::get_first_numeric_value($pricing['extra_child_prices'] ?? [], 0.0),
        ];
    }

    /**
     * Return a human-readable reason when a rate cannot be safely edited by the
     * dashboard. Null means the rate matches the supported v1 shape.
     *
     * @param object $rate MotoPress rate entity.
     * @return string|null
     */
    private static function get_rate_unsupported_reason($rate): ?string
    {
        $raw_season_prices = get_post_meta($rate->getId(), 'mphb_season_prices', true);
        if (!is_array($raw_season_prices) || empty($raw_season_prices)) {
            return 'This rate has no season-price rows.';
        }

        $season_prices = $rate->getSeasonPrices();
        if (count($raw_season_prices) !== count($season_prices)) {
            return 'This rate contains invalid or unsupported season-price rows.';
        }

        foreach ($season_prices as $season_price) {
            $season = $season_price->getSeason();
            if (!$season) {
                return 'One or more linked seasons could not be resolved.';
            }

            $pricing = $season_price->getPricesAndVariations();
            if (!is_array($pricing)) {
                return 'One or more season-price rows could not be normalized.';
            }

            if (!empty($pricing['enable_variations']) || !empty($pricing['variations'])) {
                return 'Variable pricing is enabled on this rate.';
            }

            if (!self::is_single_flat_price_list($pricing['periods'] ?? [], false)) {
                return 'This rate uses multi-period pricing.';
            }

            if (!self::is_single_flat_price_list($pricing['prices'] ?? [], true)) {
                return 'This rate uses multiple base prices for a season row.';
            }

            if (!self::is_single_flat_price_list($pricing['extra_adult_prices'] ?? [], true)) {
                return 'This rate uses multi-period extra adult pricing.';
            }

            if (!self::is_single_flat_price_list($pricing['extra_child_prices'] ?? [], true)) {
                return 'This rate uses multi-period extra child pricing.';
            }

            if (!is_numeric($season_price->getBaseAdults()) || !is_numeric($season_price->getBaseChildren())) {
                return 'This rate has unsupported base occupancy values.';
            }
        }

        return null;
    }

    /**
     * Load one rate entity or return a REST-friendly error.
     *
     * @param int $rate_id
     * @return object|WP_Error
     */
    private static function get_rate_by_id(int $rate_id)
    {
        if ($rate_id <= 0) {
            return new WP_Error(
                'bad_request',
                'A valid numeric rate ID is required.',
                ['status' => 400]
            );
        }

        $rate = mphb_prices_facade()->getRateById($rate_id);
        if (!$rate) {
            return new WP_Error(
                'not_found',
                'The requested rate could not be found.',
                ['status' => 404]
            );
        }

        return $rate;
    }

    /**
     * Build a supported MotoPress rate entity from dashboard JSON input.
     *
     * @param array<string, mixed> $input
     * @return \MPHB\Entities\Rate|WP_Error
     */
    private static function build_rate_entity(array $input)
    {
        $season_prices = self::build_supported_season_price_entities(
            $input['season_prices'] ?? [],
            (int) ($input['room_type_id'] ?? 0)
        );

        if (is_wp_error($season_prices)) {
            return $season_prices;
        }

        return new \MPHB\Entities\Rate([
            'active'        => (bool) ($input['is_active'] ?? true),
            'description'   => (string) ($input['description'] ?? ''),
            'id'            => $input['id'] ?? null,
            'room_type_id'  => (int) ($input['room_type_id'] ?? 0),
            'season_prices' => $season_prices,
            'title'         => (string) ($input['title'] ?? ''),
        ]);
    }

    /**
     * Turn the supported dashboard JSON shape into MotoPress season-price
     * entities, keeping the array ordered by ascending priority.
     *
     * @param mixed $season_prices
     * @param int   $room_type_id
     * @return array<int, \MPHB\Entities\SeasonPrice>|WP_Error
     */
    private static function build_supported_season_price_entities($season_prices, int $room_type_id)
    {
        if (!is_array($season_prices) || empty($season_prices)) {
            return new WP_Error(
                'bad_request',
                'A non-empty "season_prices" array is required.',
                ['status' => 400]
            );
        }

        $normalized = [];

        foreach ($season_prices as $index => $season_price) {
            if (!is_array($season_price)) {
                return new WP_Error(
                    'bad_request',
                    sprintf('season_prices[%d] must be an object.', $index),
                    ['status' => 400]
                );
            }

            $season_id = isset($season_price['season_id']) ? (int) $season_price['season_id'] : 0;
            if ($season_id <= 0 || !MPHB()->getSeasonRepository()->findById($season_id)) {
                return new WP_Error(
                    'bad_request',
                    sprintf('season_prices[%d].season_id must reference an existing season.', $index),
                    ['status' => 400]
                );
            }

            $base_price = self::get_required_numeric_request_value($season_price, 'base_price', $index);
            if (is_wp_error($base_price)) {
                return $base_price;
            }

            $base_adults = self::get_required_integer_request_value($season_price, 'base_adults', $index);
            if (is_wp_error($base_adults)) {
                return $base_adults;
            }

            $base_children = self::get_required_integer_request_value($season_price, 'base_children', $index);
            if (is_wp_error($base_children)) {
                return $base_children;
            }

            $extra_adult_price = self::get_optional_numeric_request_value(
                $season_price,
                'extra_adult_price',
                $index,
                0.0
            );
            if (is_wp_error($extra_adult_price)) {
                return $extra_adult_price;
            }

            $extra_child_price = self::get_optional_numeric_request_value(
                $season_price,
                'extra_child_price',
                $index,
                0.0
            );
            if (is_wp_error($extra_child_price)) {
                return $extra_child_price;
            }

            $priority = isset($season_price['priority']) && is_numeric($season_price['priority'])
                ? (int) $season_price['priority']
                : $index;

            $normalized[] = [
                'base_adults'       => $base_adults,
                'base_children'     => $base_children,
                'base_price'        => round((float) $base_price, 2),
                'extra_adult_price' => round((float) $extra_adult_price, 2),
                'extra_child_price' => round((float) $extra_child_price, 2),
                'priority'          => $priority,
                'season_id'         => $season_id,
            ];
        }

        usort($normalized, static function ($left, $right) {
            return $left['priority'] <=> $right['priority'];
        });

        $entities = [];

        foreach (array_values($normalized) as $index => $season_price) {
            $entity = \MPHB\Entities\SeasonPrice::create([
                'id'           => $index,
                'price'        => [
                    'base_adults'        => $season_price['base_adults'],
                    'base_children'      => $season_price['base_children'],
                    'enable_variations'  => false,
                    'extra_adult_prices' => [$season_price['extra_adult_price']],
                    'extra_child_prices' => [$season_price['extra_child_price']],
                    'periods'            => [1],
                    'prices'             => [$season_price['base_price']],
                    'variations'         => [],
                ],
                'room_type_id' => $room_type_id,
                'season_id'    => $season_price['season_id'],
            ]);

            if (!$entity) {
                return new WP_Error(
                    'bad_request',
                    sprintf('season_prices[%d] could not be normalized into a supported MotoPress row.', $index),
                    ['status' => 422]
                );
            }

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * Read a required numeric request value.
     *
     * @param array<string, mixed> $season_price
     * @param string               $field
     * @param int                  $index
     * @return float|WP_Error
     */
    private static function get_required_numeric_request_value(array $season_price, string $field, int $index)
    {
        if (!array_key_exists($field, $season_price) || !is_numeric($season_price[$field])) {
            return new WP_Error(
                'bad_request',
                sprintf('season_prices[%d].%s must be numeric.', $index, $field),
                ['status' => 400]
            );
        }

        return (float) $season_price[$field];
    }

    /**
     * Read a required integer request value.
     *
     * @param array<string, mixed> $season_price
     * @param string               $field
     * @param int                  $index
     * @return int|WP_Error
     */
    private static function get_required_integer_request_value(array $season_price, string $field, int $index)
    {
        if (!array_key_exists($field, $season_price) || !is_numeric($season_price[$field])) {
            return new WP_Error(
                'bad_request',
                sprintf('season_prices[%d].%s must be an integer.', $index, $field),
                ['status' => 400]
            );
        }

        return (int) $season_price[$field];
    }

    /**
     * Read an optional numeric request value with a default fallback.
     *
     * @param array<string, mixed> $season_price
     * @param string               $field
     * @param int                  $index
     * @param float                $default
     * @return float|WP_Error
     */
    private static function get_optional_numeric_request_value(
        array $season_price,
        string $field,
        int $index,
        float $default
    ) {
        if (!array_key_exists($field, $season_price) || $season_price[$field] === null || $season_price[$field] === '') {
            return $default;
        }

        if (!is_numeric($season_price[$field])) {
            return new WP_Error(
                'bad_request',
                sprintf('season_prices[%d].%s must be numeric.', $index, $field),
                ['status' => 400]
            );
        }

        return (float) $season_price[$field];
    }

    /**
     * Check that a price-like list can be represented as a single flat value.
     *
     * For periods, the single allowed value is 1. For price arrays, one numeric
     * value is allowed and the remaining entries must be empty.
     *
     * @param mixed $values
     * @param bool  $allow_zero_numeric
     * @return bool
     */
    private static function is_single_flat_price_list($values, bool $allow_zero_numeric): bool
    {
        if (!is_array($values) || $values === []) {
            return false;
        }

        $values = array_values($values);
        $first = $values[0] ?? null;

        if ($allow_zero_numeric) {
            if (!is_numeric($first)) {
                return false;
            }
        } else {
            if ((string) $first !== '1') {
                return false;
            }
        }

        if (count($values) === 1) {
            return true;
        }

        foreach (array_slice($values, 1) as $value) {
            if ($value !== '' && $value !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the first numeric value from a price list, falling back to a
     * provided default when the value is empty or missing.
     *
     * @param mixed $values
     * @param float $default
     * @return float
     */
    private static function get_first_numeric_value($values, float $default): float
    {
        if (!is_array($values) || $values === []) {
            return $default;
        }

        $first = $values[0] ?? null;

        return is_numeric($first) ? round((float) $first, 2) : $default;
    }

    /**
     * Format a DateTime-like value as YYYY-MM-DD.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function format_date($value): ?string
    {
        return $value instanceof DateTimeInterface
            ? $value->format('Y-m-d')
            : null;
    }

    /**
     * Post statuses that should remain visible to pricing operators.
     *
     * @return string[]
     */
    private static function get_editable_post_statuses(): array
    {
        return ['publish', 'pending', 'draft', 'future', 'private'];
    }
}
