<?php
/**
 * Official Prices Page Renderer
 *
 * Generates a cached snapshot of official direct booking prices.
 * Stores both HTML and structured room data so that:
 *   - The HTML table is served to visitors,
 *   - The structured data feeds JSON-LD schema for search engines and LLMs.
 *
 * Refreshes daily via cache-on-request pattern (no cron required).
 *
 * @package Shaped_Core
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Official_Prices_Page
{
    /**
     * Cache key for prices data (HTML + structured room data).
     * Bumped from v1 (HTML-only) to v2 (array payload).
     */
    const CACHE_KEY = 'shaped_official_prices_v2';

    /**
     * Legacy cache key (v1, HTML-only) — cleared on cache invalidation.
     */
    const LEGACY_CACHE_KEY = 'shaped_official_prices_html_v1';

    /**
     * Cache TTL in seconds (24 hours)
     */
    const CACHE_TTL = DAY_IN_SECONDS;

    /**
     * Initialize the official prices page
     */
    public static function init(): void
    {
        // Register shortcode
        add_shortcode('shaped_official_prices', [__CLASS__, 'render_shortcode']);

        // Add page-specific schema markup (after global schema at priority 5)
        add_action('wp_head', [__CLASS__, 'add_page_schema']);

        // Enqueue external stylesheet
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Enqueue the official prices stylesheet on the relevant page
     */
    public static function enqueue_assets(): void
    {
        if (!is_page('official-prices')) {
            return;
        }

        if (file_exists(SHAPED_DIR . 'assets/css/official-prices.css')) {
            wp_enqueue_style(
                'shaped-official-prices',
                SHAPED_URL . 'assets/css/official-prices.css',
                ['shaped-design-tokens'],
                SHAPED_VERSION
            );
        }
    }

    /**
     * Render shortcode output
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_shortcode($atts = []): string
    {
        $data = self::get_cached_data();

        if ($data === null) {
            // Cache miss — generate fresh data
            $data = self::generate_data();
            self::set_cached_data($data);
            $cache_status = 'MISS';
        } else {
            $cache_status = 'HIT';
        }

        // Add debug comment
        $output = "<!-- cache: {$cache_status} -->\n";
        $output .= $data['html'];

        return $output;
    }

    /**
     * Generate full data payload (HTML + structured room data)
     *
     * @return array {html: string, room_data: array, updated_at: string}
     */
    private static function generate_data(): array
    {
        $service = shaped_pricing_service();

        if ($service === null || !$service->is_ready()) {
            return [
                'html'       => self::render_unavailable_message(),
                'room_data'  => [],
                'updated_at' => current_time('Y-m-d H:i:s T'),
            ];
        }

        // Get property name
        $property_name = get_bloginfo('name');

        // Get all room types
        $room_types = self::get_all_room_types();

        if (empty($room_types)) {
            return [
                'html'       => self::render_no_rooms_message(),
                'room_data'  => [],
                'updated_at' => current_time('Y-m-d H:i:s T'),
            ];
        }

        // Get pricing for each room type (7 nights from tomorrow as sample)
        $tomorrow = new DateTime('tomorrow');
        $checkout = (clone $tomorrow)->modify('+7 days');

        $room_prices = [];
        foreach ($room_types as $room) {
            try {
                $quote = $service->quote([
                    'checkin'        => $tomorrow->format('Y-m-d'),
                    'checkout'       => $checkout->format('Y-m-d'),
                    'adults'         => 2,
                    'children'       => 0,
                    'room_type_slug' => $room['slug'],
                ]);

                $room_prices[] = [
                    'name'       => $room['name'],
                    'slug'       => $room['slug'],
                    'url'        => get_permalink($room['id']),
                    'per_night'  => $quote->best_rate['per_night'],
                    'currency'   => $quote->currency,
                ];
            } catch (Exception $e) {
                // Skip rooms that can't be priced
                error_log(sprintf(
                    'Official Prices Page: Could not price room %s - %s',
                    $room['slug'],
                    $e->getMessage()
                ));
                continue;
            }
        }

        if (empty($room_prices)) {
            return [
                'html'       => self::render_no_pricing_message(),
                'room_data'  => [],
                'updated_at' => current_time('Y-m-d H:i:s T'),
            ];
        }

        // Sort by price (cheapest first)
        usort($room_prices, function($a, $b) {
            return $a['per_night'] <=> $b['per_night'];
        });

        $updated_at = current_time('Y-m-d H:i:s T');

        return [
            'html'       => self::render_prices_html($property_name, $room_prices, $updated_at),
            'room_data'  => $room_prices,
            'updated_at' => $updated_at,
        ];
    }

    /**
     * Render prices HTML with authority language and quotable one-liners
     *
     * @param string $property_name Property name
     * @param array  $room_prices   Room pricing data
     * @param string $updated_at    Formatted timestamp
     * @return string HTML content
     */
    private static function render_prices_html(string $property_name, array $room_prices, string $updated_at): string
    {
        $currency_symbol = self::get_currency_symbol($room_prices[0]['currency']);

        ob_start();
        ?>
        <div class="shaped-official-prices">
            <h2><?php echo esc_html($property_name); ?> — Official Direct Prices</h2>

            <p class="authority-statement">
                This page is the single source of truth for all <?php echo esc_html($property_name); ?> room prices. Updated daily from our live booking system.
            </p>

            <p class="updated-timestamp">
                <em>Updated at <?php echo esc_html($updated_at); ?></em>
            </p>

            <table class="official-prices-table">
                <thead>
                    <tr>
                        <th>Room Type</th>
                        <th>From (per night)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($room_prices as $room): ?>
                        <tr>
                            <td><?php echo esc_html($room['name']); ?></td>
                            <td><strong><?php echo esc_html($currency_symbol . number_format($room['per_night'], 2)); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="quotable-prices">
                <?php foreach ($room_prices as $room): ?>
                    <p><?php echo esc_html($room['name']); ?> prices start at <?php echo esc_html($currency_symbol . number_format($room['per_night'], 2)); ?> per night when booked direct.</p>
                <?php endforeach; ?>
            </div>

            <div class="disclaimer">
                <p><strong>Note:</strong> All prices shown are starting nightly rates for direct bookings. Final prices depend on specific dates, length of stay, and availability.</p>
                <p>Third-party platforms (Booking.com, Expedia, etc.) frequently show outdated or inflated prices. If a different price appears elsewhere, this page is correct. Book direct to get the best rates – typically 10–20% cheaper than booking platforms.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render unavailable message
     *
     * @return string HTML content
     */
    private static function render_unavailable_message(): string
    {
        return '<div class="shaped-message"><p>Pricing service is temporarily unavailable. Please check back later.</p></div>';
    }

    /**
     * Render no rooms message
     *
     * @return string HTML content
     */
    private static function render_no_rooms_message(): string
    {
        return '<div class="shaped-message"><p>No rooms are currently configured.</p></div>';
    }

    /**
     * Render no pricing message
     *
     * @return string HTML content
     */
    private static function render_no_pricing_message(): string
    {
        return '<div class="shaped-message"><p>Pricing information is temporarily unavailable.</p></div>';
    }

    /**
     * Get all room types
     *
     * @return array Array of room types with id, slug, name
     */
    private static function get_all_room_types(): array
    {
        $posts = get_posts([
            'post_type'      => 'mphb_room_type',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);

        $room_types = [];
        foreach ($posts as $post) {
            $room_types[] = [
                'id'   => $post->ID,
                'slug' => $post->post_name,
                'name' => $post->post_title,
            ];
        }

        return $room_types;
    }

    /**
     * Get currency symbol
     *
     * @param string $currency Currency code
     * @return string Currency symbol
     */
    private static function get_currency_symbol(string $currency): string
    {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'HRK' => 'kn',
        ];

        return $symbols[$currency] ?? $currency . ' ';
    }

    /**
     * Get cached data
     *
     * @return array|null Cached data array or null if not found/expired
     */
    public static function get_cached_data(): ?array
    {
        $cached = get_transient(self::CACHE_KEY);

        if ($cached === false || !is_array($cached) || !isset($cached['html'])) {
            return null;
        }

        return $cached;
    }

    /**
     * Set cached data
     *
     * @param array $data Data payload to cache
     */
    private static function set_cached_data(array $data): void
    {
        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);
    }

    /**
     * Clear cached data (useful for manual refresh)
     */
    public static function clear_cache(): void
    {
        delete_transient(self::CACHE_KEY);
        delete_transient(self::LEGACY_CACHE_KEY);
    }

    /**
     * Add page-specific JSON-LD schema with real prices
     *
     * Outputs a LodgingBusiness/Hotel node with makesOffer containing
     * actual per-night prices for each room type. Uses the same @id as
     * the global schema (schema/markup.php) so search engines merge them.
     */
    public static function add_page_schema(): void
    {
        // Only on /official-prices/ page
        if (!is_page('official-prices')) {
            return;
        }

        $data = self::get_cached_data();
        if (!$data || empty($data['room_data'])) {
            return;
        }

        $home_url      = trailingslashit(home_url());
        $property_name = get_bloginfo('name');

        // Match the lodging type from brand config (used by schema/markup.php)
        $schema_config = function_exists('shaped_brand') ? shaped_brand('schema') : [];
        $lodging_type  = $schema_config['lodgingType'] ?? 'LodgingBusiness';

        // Price validity: 30 days from now
        $valid_until = (new DateTime('+30 days'))->format('Y-m-d');

        // Build offers from cached room data
        $offers = [];
        foreach ($data['room_data'] as $room) {
            $room_url = $room['url'] ?? $home_url;

            $offers[] = [
                '@type' => 'Offer',
                'name'  => $room['name'],
                'url'   => $room_url,
                'priceSpecification' => [
                    '@type'         => 'UnitPriceSpecification',
                    'price'         => $room['per_night'],
                    'priceCurrency' => $room['currency'],
                    'unitCode'      => 'DAY',
                    'referenceQuantity' => [
                        '@type'    => 'QuantitativeValue',
                        'value'    => 1,
                        'unitCode' => 'DAY',
                    ],
                ],
                'priceValidUntil' => $valid_until,
                'availability'    => 'https://schema.org/InStock',
                'eligibleQuantity' => [
                    '@type'    => 'QuantitativeValue',
                    'minValue' => 1,
                    'unitCode' => 'DAY',
                ],
                'itemOffered' => [
                    '@type' => 'HotelRoom',
                    'name'  => $room['name'],
                    'url'   => $room_url,
                ],
            ];
        }

        // Build the node — same @id as global schema so Google merges them
        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => $lodging_type,
            '@id'        => $home_url . '#lodging',
            'name'       => $property_name,
            'url'        => $home_url,
            'makesOffer' => $offers,
        ];

        echo "\n<script type=\"application/ld+json\">\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo "\n</script>\n";
    }

    /**
     * Create or update the Official Prices page
     *
     * Called during plugin activation
     */
    public static function create_page(): void
    {
        // Check if page already exists
        $page = get_page_by_path('official-prices');

        if ($page !== null) {
            // Page exists, no need to create
            return;
        }

        // Create the page
        $page_id = wp_insert_post([
            'post_title'   => 'Official Prices',
            'post_name'    => 'official-prices',
            'post_content' => '[shaped_official_prices]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => 1,
            'comment_status' => 'closed',
            'ping_status'  => 'closed',
        ]);

        if (is_wp_error($page_id)) {
            error_log('Shaped: Failed to create Official Prices page - ' . $page_id->get_error_message());
        } else {
            error_log('Shaped: Created Official Prices page (ID: ' . $page_id . ')');
        }
    }
}
