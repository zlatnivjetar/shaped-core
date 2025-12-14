<?php
/**
 * Shaped Core — Schema Markup
 * Centralized JSON-LD schema output for hospitality projects
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Shaped_Schema {

    public function __construct() {
        add_action('wp_head', [$this, 'render'], 5);
    }

    public function render(): void {
        if (is_admin()) {
            return;
        }

        $graph = $this->build_graph();
        if (empty($graph)) {
            return;
        }

        echo "\n<script type=\"application/ld+json\">\n";
        echo wp_json_encode(
            [
                '@context' => 'https://schema.org',
                '@graph'   => $graph,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        echo "\n</script>\n";
    }

    private function build_graph(): array {
        $config = apply_filters('shaped_schema_config', $this->default_config());

        $website_id  = home_url('/#website');
        $lodging_id  = trailingslashit(home_url()) . '#lodging';
        $page_url    = $this->current_url();
        $page_id     = $page_url . '#webpage';

        $graph = [];

        /* ─────────────────────────
         * WebSite
         * ───────────────────────── */
        $graph[] = [
            '@type' => 'WebSite',
            '@id'   => $website_id,
            'url'   => trailingslashit(home_url()),
            'name'  => $config['site_name'],
        ];

        /* ─────────────────────────
         * LodgingBusiness (property-level)
         * ───────────────────────── */
        $graph[] = array_filter([
            '@type' => $config['lodging_type'], // LodgingBusiness | Hotel
            '@id'   => $lodging_id,
            'name'  => $config['name'],
            'url'   => trailingslashit(home_url()),
            'image' => $config['images'],
            'telephone' => $config['telephone'],
            'email'     => $config['email'],
            'address'   => $config['address'],
            'geo'       => $config['geo'],
            'priceRange'         => $config['price_range'],
            'currenciesAccepted' => $config['currency'],
            'paymentAccepted'    => $config['payment_accepted'],
            'checkinTime'        => $config['checkin_time'],
            'checkoutTime'       => $config['checkout_time'],
            'petsAllowed'        => $config['pets_allowed'],
            'amenityFeature'     => $config['amenities'],
            'sameAs'             => $config['same_as'],
        ]);

        /* ─────────────────────────
         * WebPage (current page)
         * ───────────────────────── */
        $webpage = [
            '@type' => 'WebPage',
            '@id'   => $page_id,
            'url'   => $page_url,
            'name'  => wp_get_document_title(),
            'isPartOf' => [
                '@id' => $website_id,
            ],
        ];

        if (is_front_page()) {
            $webpage['mainEntity'] = [
                '@id' => $lodging_id,
            ];
        }

        /* ─────────────────────────
         * Accommodation unit (room pages only)
         * ───────────────────────── */
        if (is_singular('mphb_room_type')) {
            $unit = $this->unit_node(get_the_ID(), $lodging_id);
            if ($unit) {
                $graph[] = $unit;
                $webpage['mainEntity'] = [
                    '@id' => $unit['@id'],
                ];
            }
        }

        $graph[] = $webpage;

        return array_values(array_filter($graph));
    }

    private function unit_node(int $post_id, string $lodging_id): ?array {
        $url = get_permalink($post_id);
        if (!$url) {
            return null;
        }

        // TODO: map these from MotoPress / meta when ready
        $max_occupancy = null;
        $floor_m2      = null;

        return array_filter([
            '@type' => 'Accommodation',
            '@id'   => $url . '#unit',
            'name'  => get_the_title($post_id),
            'url'   => $url,
            'containedInPlace' => [
                '@id' => $lodging_id,
            ],
            'occupancy' => $max_occupancy ? [
                '@type' => 'QuantitativeValue',
                'maxValue' => (int) $max_occupancy,
                'unitCode' => 'C62', // persons
            ] : null,
            'floorSize' => $floor_m2 ? [
                '@type' => 'QuantitativeValue',
                'value' => (float) $floor_m2,
                'unitCode' => 'MTK',
            ] : null,
        ]);
    }

    private function current_url(): string {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        return strtok($scheme . $host . $uri, '?');
    }

    private function default_config(): array {
        return [
            'site_name'   => get_bloginfo('name'),
            'lodging_type' => 'LodgingBusiness',
            'name'        => 'Preelook Apartments & Rooms',
            'telephone'   => '+385916125689',
            'email'       => 'info@preelook.com',
            'currency'    => 'EUR',
            'payment_accepted' => ['Credit Card', 'Debit Card'],
            'price_range' => '€€',
            'checkin_time'  => '14:00',
            'checkout_time' => '10:00',
            'pets_allowed'  => false,
            'images' => [], // absolute URLs only
            'same_as' => [], // Google Maps, Instagram, Facebook, etc.
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress'   => 'Preluk 4',
                'addressLocality' => 'Rijeka',
                'postalCode'      => '51000',
                'addressCountry'  => 'HR',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude'  => 45.3438,
                'longitude' => 14.3360,
            ],
            'amenities' => [
                [
                    '@type' => 'LocationFeatureSpecification',
                    'name'  => 'Free Parking',
                    'value' => true,
                ],
                [
                    '@type' => 'LocationFeatureSpecification',
                    'name'  => 'Free WiFi',
                    'value' => true,
                ],
            ],
        ];
    }
}

new Shaped_Schema();
