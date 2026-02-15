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
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => trailingslashit(home_url()) . 'book/?mphb_check_in_date={check_in_date}&mphb_check_out_date={check_out_date}',
                ],
                'query-input' => 'required name=check_in_date required name=check_out_date',
            ],
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
            'potentialAction'    => [
                '@type'  => 'ReserveAction',
                'target' => [
                    '@type'          => 'EntryPoint',
                    'urlTemplate'    => apply_filters('shaped_booking_url', trailingslashit(home_url()) . 'book/'),
                    'actionPlatform' => [
                        'http://schema.org/DesktopWebPlatform',
                        'http://schema.org/MobilePlatform',
                    ],
                ],
                'result' => [
                    '@type' => 'LodgingReservation',
                    'name'  => 'Book Direct',
                ],
            ],
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

        if (is_page('book')) {
            $webpage['potentialAction'] = [
                '@type'  => 'ReserveAction',
                'target' => [
                    '@type'          => 'EntryPoint',
                    'urlTemplate'    => $page_url,
                    'actionPlatform' => [
                        'http://schema.org/DesktopWebPlatform',
                        'http://schema.org/MobilePlatform',
                    ],
                ],
                'result' => [
                    '@type' => 'LodgingReservation',
                    'name'  => 'Book Now',
                ],
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

        // Build room price offer from discounted base price
        $offer = null;
        if (function_exists('shaped_get_room_pricing_data')) {
            $slug    = get_post_field('post_name', $post_id);
            $pricing = shaped_get_room_pricing_data($post_id, $slug);

            if ($pricing['base_price'] > 0) {
                $schema_config = function_exists('shaped_brand') ? shaped_brand('schema') : [];
                $currency      = $schema_config['currency'] ?? 'EUR';
                $price         = $pricing['has_discount'] ? $pricing['discount_price'] : $pricing['base_price'];

                $offer = [
                    '@type'           => 'Offer',
                    'price'           => round($price, 2),
                    'priceCurrency'   => $currency,
                    'priceValidUntil' => (new DateTime('+30 days'))->format('Y-m-d'),
                    'availability'    => 'https://schema.org/InStock',
                    'url'             => $url,
                ];
            }
        }

        return array_filter([
            '@type' => 'Accommodation',
            '@id'   => $url . '#unit',
            'name'  => get_the_title($post_id),
            'url'   => $url,
            'containedInPlace' => [
                '@id' => $lodging_id,
            ],
            'offers' => $offer,
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
        // Pull configuration from brand config (single source of truth)
        $company_name = function_exists('shaped_brand') ? shaped_brand('company.name') : get_bloginfo('name');
        $phone = function_exists('shaped_brand') ? shaped_brand('contact.phone') : '';
        $email = function_exists('shaped_brand') ? shaped_brand('contact.email') : '';
        $address = function_exists('shaped_brand') ? shaped_brand('contact.address') : [];
        $coords = function_exists('shaped_brand') ? shaped_brand('contact.coordinates') : [];
        $schema = function_exists('shaped_brand') ? shaped_brand('schema') : [];

        // Build amenities from brand config
        $amenities = [];
        $brand_amenities = $schema['amenities'] ?? [];
        foreach ($brand_amenities as $amenity) {
            $amenities[] = [
                '@type' => 'LocationFeatureSpecification',
                'name'  => $amenity['name'] ?? '',
                'value' => $amenity['value'] ?? true,
            ];
        }

        return [
            'site_name'        => get_bloginfo('name'),
            'lodging_type'     => $schema['lodgingType'] ?? 'LodgingBusiness',
            'name'             => $company_name,
            'telephone'        => str_replace(' ', '', $phone),
            'email'            => $email,
            'currency'         => $schema['currency'] ?? 'EUR',
            'payment_accepted' => $schema['paymentAccepted'] ?? ['Credit Card', 'Debit Card'],
            'price_range'      => $schema['priceRange'] ?? '€€',
            'checkin_time'     => $schema['checkinTime'] ?? '14:00',
            'checkout_time'    => $schema['checkoutTime'] ?? '10:00',
            'pets_allowed'     => $schema['petsAllowed'] ?? false,
            'images'           => [], // absolute URLs only
            'same_as'          => $schema['sameAs'] ?? [],
            'address' => is_array($address) ? [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $address['street'] ?? '',
                'addressLocality' => $address['city'] ?? '',
                'postalCode'      => $address['postalCode'] ?? '',
                'addressCountry'  => $address['countryCode'] ?? '',
            ] : null,
            'geo' => !empty($coords) ? [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $coords['latitude'] ?? 0,
                'longitude' => $coords['longitude'] ?? 0,
            ] : null,
            'amenities' => $amenities,
        ];
    }
}

new Shaped_Schema();
