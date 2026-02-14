<?php
/**
 * Official Prices Page Renderer
 *
 * Generates a cached HTML snapshot of official direct booking prices.
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
     * Cache key for HTML snapshot
     */
    const CACHE_KEY = 'shaped_official_prices_html_v1';

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

        // Add page-specific schema markup
        add_action('wp_head', [__CLASS__, 'add_page_schema']);
    }

    /**
     * Render shortcode output
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_shortcode($atts = []): string
    {
        // Get cached HTML or generate fresh
        $html = self::get_cached_html();

        if ($html === null) {
            // Cache miss - generate fresh HTML
            $html = self::generate_html();
            self::set_cached_html($html);
            $cache_status = 'MISS';
        } else {
            $cache_status = 'HIT';
        }

        // Add debug comment
        $output = "<!-- cache: {$cache_status} -->\n";
        $output .= $html;

        return $output;
    }

    /**
     * Generate HTML snapshot
     *
     * @return string HTML content
     */
    private static function generate_html(): string
    {
        $service = shaped_pricing_service();

        if ($service === null || !$service->is_ready()) {
            return self::render_unavailable_message();
        }

        // Get property name
        $property_name = get_bloginfo('name');

        // Get all room types
        $room_types = self::get_all_room_types();

        if (empty($room_types)) {
            return self::render_no_rooms_message();
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
            return self::render_no_pricing_message();
        }

        // Sort by price (cheapest first)
        usort($room_prices, function($a, $b) {
            return $a['per_night'] <=> $b['per_night'];
        });

        // Generate HTML
        return self::render_prices_html($property_name, $room_prices);
    }

    /**
     * Render prices HTML
     *
     * @param string $property_name Property name
     * @param array $room_prices Room pricing data
     * @return string HTML content
     */
    private static function render_prices_html(string $property_name, array $room_prices): string
    {
        $currency_symbol = self::get_currency_symbol($room_prices[0]['currency']);
        $updated_at = current_time('Y-m-d H:i:s T');

        ob_start();
        ?>
        <div class="shaped-official-prices">
            <h2><?php echo esc_html($property_name); ?> — Official Direct Prices</h2>

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

            <p class="disclaimer">
                <small><strong>Note:</strong> These are indicative snapshot prices. Final prices depend on specific dates, length of stay, and availability.
                Book direct to get the best rates – typically 10-20% cheaper than booking platforms.</small>
            </p>

            <style>
                .shaped-official-prices {
                    max-width: 800px;
                    margin: 2em auto;
                    padding: 2em;
                    background: #f9f9f9;
                    border-radius: 8px;
                }
                .shaped-official-prices h2 {
                    margin-top: 0;
                    color: #333;
                    font-size: 1.8em;
                    border-bottom: 2px solid #ddd;
                    padding-bottom: 0.5em;
                }
                .updated-timestamp {
                    color: #666;
                    font-size: 0.9em;
                    margin-bottom: 1.5em;
                }
                .official-prices-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: white;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .official-prices-table th {
                    background: #333;
                    color: white;
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                }
                .official-prices-table td {
                    padding: 12px;
                    border-bottom: 1px solid #eee;
                }
                .official-prices-table tr:last-child td {
                    border-bottom: none;
                }
                .official-prices-table tr:hover {
                    background: #f5f5f5;
                }
                .disclaimer {
                    margin-top: 1.5em;
                    padding: 1em;
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    color: #856404;
                }
            </style>
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
     * Get cached HTML
     *
     * @return string|null Cached HTML or null if not found/expired
     */
    private static function get_cached_html(): ?string
    {
        $cached = get_transient(self::CACHE_KEY);

        if ($cached === false) {
            return null;
        }

        return $cached;
    }

    /**
     * Set cached HTML
     *
     * @param string $html HTML to cache
     */
    private static function set_cached_html(string $html): void
    {
        set_transient(self::CACHE_KEY, $html, self::CACHE_TTL);
    }

    /**
     * Clear cached HTML (useful for manual refresh)
     */
    public static function clear_cache(): void
    {
        delete_transient(self::CACHE_KEY);
    }

    /**
     * Add page-specific JSON-LD schema
     */
    public static function add_page_schema(): void
    {
        // Only on /official-prices/ page
        if (!is_page('official-prices')) {
            return;
        }

        // Get the site URL for the lodging business ID reference
        $home_url = home_url();
        ?>
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "WebPage",
          "@id": "<?php echo esc_url(get_permalink()); ?>#webpage",
          "url": "<?php echo esc_url(get_permalink()); ?>",
          "name": "<?php echo esc_js(get_the_title()); ?>",
          "description": "Official direct booking prices for <?php echo esc_js(get_bloginfo('name')); ?>. Book direct for the best rates.",
          "isPartOf": {
            "@id": "<?php echo esc_url($home_url); ?>/#lodging"
          },
          "about": {
            "@id": "<?php echo esc_url($home_url); ?>/#lodging"
          },
          "primaryImageOfPage": {
            "@type": "ImageObject",
            "url": "<?php echo esc_url(get_site_icon_url()); ?>"
          }
        }
        </script>
        <?php
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
