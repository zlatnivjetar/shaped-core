<?php
/**
 * Shaped Core — Schema Markup
 * Centralized JSON-LD schema output for hospitality projects.
 *
 * Goals:
 * - Stay property agnostic.
 * - Keep legacy compatibility with existing shaped_brand('schema') values.
 * - Improve semantic quality for lodging sites without inventing facts.
 * - Only emit data that is either configured or derivable from visible content.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Shaped_Schema {

    private const DEFAULT_ROOM_POST_TYPE = 'mphb_room_type';

    public function __construct() {
        add_action('wp_head', [$this, 'render'], 5);
    }

    public function render(): void {
        if (!$this->should_render()) {
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

    private function should_render(): bool {
        if (is_admin() || is_feed() || is_robots() || is_trackback() || is_404()) {
            return false;
        }

        return (bool) apply_filters('shaped_schema_should_render', true);
    }

    private function build_graph(): array {
        $config = $this->normalize_config(
            apply_filters('shaped_schema_config', $this->default_config())
        );

        $website_id = trailingslashit(home_url()) . '#website';
        $lodging_id = trailingslashit(home_url()) . '#lodging';
        $page_url   = $this->current_url();
        $page_id    = $page_url . '#webpage';

        $units = $this->collect_context_units($config, $lodging_id, $page_id);

        $graph = [];

        $website = $this->build_website_node($config, $website_id);
        if (!empty($website)) {
            $graph[] = $website;
        }

        $lodging = $this->build_lodging_node($config, $lodging_id, $page_id, $units);
        if (!empty($lodging)) {
            $graph[] = $lodging;
        }

        foreach ($units as $unit) {
            $graph[] = $unit;
        }

        $item_list = $this->build_item_list_node($units, $page_url);
        if (!empty($item_list)) {
            $graph[] = $item_list;
        }

        $webpage = $this->build_webpage_node($config, $website_id, $lodging_id, $page_id, $page_url, $units, $item_list);
        if (!empty($webpage)) {
            $graph[] = $webpage;
        }

        $extra_graph = apply_filters('shaped_schema_extra_graph', [], $config, $page_id, $page_url);
        if (is_array($extra_graph)) {
            foreach ($extra_graph as $node) {
                if (is_array($node) && !empty($node)) {
                    $graph[] = $node;
                }
            }
        }

        $graph = array_map([$this, 'clean_node'], $graph);
        $graph = array_values(array_filter($graph, static fn($node) => is_array($node) && !empty($node)));

        return $graph;
    }

    private function build_website_node(array $config, string $website_id): array {
        $node = [
            '@type' => 'WebSite',
            '@id'   => $website_id,
            'url'   => trailingslashit(home_url()),
            'name'  => $config['site_name'],
        ];

        if (!empty($config['site_description'])) {
            $node['description'] = $config['site_description'];
        }

        if (!empty($config['website_search']['urlTemplate']) && !empty($config['website_search']['queryInput'])) {
            $node['potentialAction'] = [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $config['website_search']['urlTemplate'],
                ],
                'query-input' => $config['website_search']['queryInput'],
            ];
        }

        return (array) apply_filters('shaped_schema_website_node', $this->clean_node($node), $config);
    }

    private function build_lodging_node(array $config, string $lodging_id, string $page_id, array $units): array {
        $node = [
            '@type'              => $config['lodging_type'],
            '@id'                => $lodging_id,
            'name'               => $config['name'],
            'alternateName'      => $config['alternate_name'],
            'legalName'          => $config['legal_name'],
            'description'        => $config['description'],
            'url'                => trailingslashit(home_url()),
            'mainEntityOfPage'   => is_front_page() ? ['@id' => $page_id] : null,
            'image'              => $config['images'],
            'logo'               => $config['logo'],
            'telephone'          => $config['telephone'],
            'email'              => $config['email'],
            'address'            => $config['address'],
            'geo'                => $config['geo'],
            'hasMap'             => $config['has_map'],
            'priceRange'         => $config['price_range'],
            'currenciesAccepted' => $config['currency'],
            'paymentAccepted'    => $config['payment_accepted'],
            'checkinTime'        => $this->normalize_time($config['checkin_time']),
            'checkoutTime'       => $this->normalize_time($config['checkout_time']),
            'petsAllowed'        => $config['pets_allowed'],
            'amenityFeature'     => $config['amenities'],
            'sameAs'             => $config['same_as'],
            'numberOfRooms'      => $this->build_number_of_rooms($config['number_of_rooms']),
            'starRating'         => $config['star_rating'],
            'vatID'              => $config['vat_id'],
            'taxID'              => $config['tax_id'],
            'contactPoint'       => $config['contact_point'],
            'containsPlace'      => !empty($units) ? array_map(static fn($unit) => ['@id' => $unit['@id']], $units) : null,
        ];

        if ($config['enable_reserve_action'] && !empty($config['booking_url'])) {
            $node['potentialAction'] = [
                '@type'  => 'ReserveAction',
                'target' => [
                    '@type'          => 'EntryPoint',
                    'urlTemplate'    => $config['booking_url'],
                    'actionPlatform' => [
                        'http://schema.org/DesktopWebPlatform',
                        'http://schema.org/MobileWebPlatform',
                    ],
                ],
                'result' => [
                    '@type' => 'LodgingReservation',
                ],
            ];
        }

        return (array) apply_filters('shaped_schema_lodging_node', $this->clean_node($node), $config, $units);
    }

    private function build_webpage_node(
        array $config,
        string $website_id,
        string $lodging_id,
        string $page_id,
        string $page_url,
        array $units,
        array $item_list
    ): array {
        $node = [
            '@type'    => 'WebPage',
            '@id'      => $page_id,
            'url'      => $page_url,
            'name'     => wp_get_document_title(),
            'isPartOf' => ['@id' => $website_id],
        ];

        if (is_front_page()) {
            $node['mainEntity'] = ['@id' => $lodging_id];
            $node['about']      = ['@id' => $lodging_id];
        } elseif (is_singular($config['room_post_type']) && !empty($units[0]['@id'])) {
            $node['mainEntity'] = ['@id' => $units[0]['@id']];
            $node['about']      = ['@id' => $lodging_id];
        } elseif (!empty($item_list['@id'])) {
            $node['mainEntity'] = ['@id' => $item_list['@id']];
            $node['about']      = ['@id' => $lodging_id];
        } else {
            $node['about'] = ['@id' => $lodging_id];
        }

        if ($this->is_booking_page($config) && $config['enable_reserve_action'] && !empty($config['booking_url'])) {
            $node['potentialAction'] = [
                '@type'  => 'ReserveAction',
                'target' => [
                    '@type'          => 'EntryPoint',
                    'urlTemplate'    => $config['booking_url'],
                    'actionPlatform' => [
                        'http://schema.org/DesktopWebPlatform',
                        'http://schema.org/MobileWebPlatform',
                    ],
                ],
                'result' => [
                    '@type' => 'LodgingReservation',
                ],
            ];
        }

        return (array) apply_filters('shaped_schema_webpage_node', $this->clean_node($node), $config, $units, $item_list);
    }

    private function build_item_list_node(array $units, string $page_url): array {
        if (count($units) < 2) {
            return [];
        }

        $item_list = [
            '@type'           => 'ItemList',
            '@id'             => $page_url . '#itemlist',
            'name'            => wp_get_document_title(),
            'itemListElement' => [],
        ];

        $position = 1;
        foreach ($units as $unit) {
            if (empty($unit['@id'])) {
                continue;
            }

            $item_list['itemListElement'][] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'item'     => ['@id' => $unit['@id']],
            ];
            $position++;
        }

        return $this->clean_node($item_list);
    }

    private function collect_context_units(array $config, string $lodging_id, string $page_id): array {
        $post_type = $config['room_post_type'];
        $units     = [];

        if (is_singular($post_type)) {
            $unit = $this->build_unit_node((int) get_the_ID(), $config, $lodging_id, $page_id);
            if (!empty($unit)) {
                $units[] = $unit;
            }

            return $units;
        }

        if (is_post_type_archive($post_type) && $config['include_units_on_archive']) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => (int) get_query_var('posts_per_page') ?: get_option('posts_per_page'),
                'paged'          => max(1, (int) get_query_var('paged')),
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);

            foreach ($posts as $post_id) {
                $unit = $this->build_unit_node((int) $post_id, $config, $lodging_id, null);
                if (!empty($unit)) {
                    $units[] = $unit;
                }
            }

            return $units;
        }

        if (is_front_page() && $config['include_units_on_front_page']) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);

            foreach ($posts as $post_id) {
                $unit = $this->build_unit_node((int) $post_id, $config, $lodging_id, null);
                if (!empty($unit)) {
                    $units[] = $unit;
                }
            }
        }

        return $units;
    }

    private function build_unit_node(int $post_id, array $config, string $lodging_id, ?string $page_id): array {
        $url = get_permalink($post_id);
        if (!$url) {
            return [];
        }

        $unit_data = $this->get_unit_data($post_id, $config);
        $unit_type = $unit_data['type'] ?: $config['default_unit_type'];

        $types = [$unit_type];
        if (!empty($unit_data['offer'])) {
            $types[] = 'Product';
        }
        $types = array_values(array_unique(array_filter($types)));

        $node = [
            '@type'                 => count($types) === 1 ? $types[0] : $types,
            '@id'                   => $url . '#unit',
            'name'                  => $unit_data['name'],
            'description'           => $unit_data['description'],
            'url'                   => $url,
            'mainEntityOfPage'      => $page_id ? ['@id' => $page_id] : null,
            'image'                 => $unit_data['images'],
            'containedInPlace'      => ['@id' => $lodging_id],
            'additionalType'        => $unit_data['additional_type'],
            'accommodationCategory' => $unit_data['accommodation_category'],
            'amenityFeature'        => $unit_data['amenities'],
            'bed'                   => $unit_data['beds'],
            'occupancy'             => $unit_data['occupancy'],
            'floorSize'             => $unit_data['floor_size'],
            'numberOfRooms'         => $unit_data['number_of_rooms'],
            'offers'                => $unit_data['offer'],
        ];

        return (array) apply_filters('shaped_schema_unit_node', $this->clean_node($node), $post_id, $config, $unit_data);
    }

    private function get_unit_data(int $post_id, array $config): array {
        $slug      = (string) get_post_field('post_name', $post_id);
        $overrides = $this->find_unit_overrides($post_id, $slug, $config['units']);

        $offer = $this->build_unit_offer($post_id, $slug, $overrides, $config['currency']);

        $data = [
            'name'                   => $overrides['name'] ?? get_the_title($post_id),
            'type'                   => $overrides['type'] ?? null,
            'additional_type'        => $overrides['additionalType'] ?? null,
            'description'            => $this->resolve_unit_description($post_id, $overrides),
            'images'                 => $this->resolve_unit_images($post_id, $overrides),
            'accommodation_category' => $overrides['accommodationCategory'] ?? null,
            'amenities'              => $this->normalize_amenities($overrides['amenities'] ?? []),
            'beds'                   => $this->normalize_beds($overrides['beds'] ?? null),
            'occupancy'              => $this->normalize_occupancy($overrides['occupancy'] ?? null, $overrides['occupancyDetails'] ?? []),
            'floor_size'             => $this->normalize_floor_size($overrides['floorSize'] ?? null),
            'number_of_rooms'        => $this->build_number_of_rooms($overrides['numberOfRooms'] ?? null),
            'offer'                  => $offer,
        ];

        return (array) apply_filters('shaped_schema_unit_data', $data, $post_id, $slug, $config, $overrides);
    }

    private function build_unit_offer(int $post_id, string $slug, array $overrides, string $currency): ?array {
        $url = get_permalink($post_id);
        if (!$url) {
            return null;
        }

        if (!empty($overrides['offer']) && is_array($overrides['offer'])) {
            return $this->normalize_offer($overrides['offer'], $url, $currency);
        }

        if (!function_exists('shaped_get_room_pricing_data')) {
            return null;
        }

        $pricing = shaped_get_room_pricing_data($post_id, $slug);
        if (empty($pricing['base_price']) || (float) $pricing['base_price'] <= 0) {
            return null;
        }

        $price = !empty($pricing['has_discount'])
            ? (float) $pricing['discount_price']
            : (float) $pricing['base_price'];

        return [
            '@type'            => 'Offer',
            'url'              => $url,
            'businessFunction' => 'http://purl.org/goodrelations/v1#LeaseOut',
            'priceSpecification' => [
                '@type'         => 'UnitPriceSpecification',
                'price'         => round($price, 2),
                'priceCurrency' => $currency,
                'unitCode'      => 'DAY',
            ],
        ];
    }

    private function normalize_offer(array $offer, string $url, string $currency): array {
        $price_spec = $offer['priceSpecification'] ?? [];
        if (!empty($offer['price']) || !empty($offer['minPrice']) || !empty($offer['maxPrice'])) {
            $price_spec = array_merge($price_spec, [
                'price'         => $offer['price'] ?? null,
                'minPrice'      => $offer['minPrice'] ?? null,
                'maxPrice'      => $offer['maxPrice'] ?? null,
                'priceCurrency' => $offer['priceCurrency'] ?? $currency,
                'unitCode'      => $offer['unitCode'] ?? 'DAY',
                'validFrom'     => $offer['validFrom'] ?? null,
                'validThrough'  => $offer['validThrough'] ?? null,
            ]);
        }

        $normalized = [
            '@type'            => 'Offer',
            'url'              => $offer['url'] ?? $url,
            'businessFunction' => $offer['businessFunction'] ?? 'http://purl.org/goodrelations/v1#LeaseOut',
            'availability'     => $offer['availability'] ?? null,
            'priceSpecification' => !empty($price_spec)
                ? $this->clean_node(array_merge(['@type' => 'UnitPriceSpecification'], $price_spec))
                : null,
        ];

        return $this->clean_node($normalized);
    }

    private function resolve_unit_description(int $post_id, array $overrides): ?string {
        if (!empty($overrides['description']) && is_string($overrides['description'])) {
            return trim($overrides['description']);
        }

        $excerpt = trim((string) get_post_field('post_excerpt', $post_id));
        if ($excerpt !== '') {
            return wp_strip_all_tags($excerpt);
        }

        $content = trim((string) get_post_field('post_content', $post_id));
        if ($content === '') {
            return null;
        }

        return wp_strip_all_tags(wp_trim_words($content, 40, ''));
    }

    private function resolve_unit_images(int $post_id, array $overrides): array {
        if (!empty($overrides['images'])) {
            return $this->normalize_images($overrides['images']);
        }

        $image_id = get_post_thumbnail_id($post_id);
        if ($image_id) {
            $url = wp_get_attachment_image_url($image_id, 'full');
            if ($url) {
                return [$url];
            }
        }

        return [];
    }

    private function find_unit_overrides(int $post_id, string $slug, array $units): array {
        if (isset($units[$post_id]) && is_array($units[$post_id])) {
            return $units[$post_id];
        }

        if (isset($units[$slug]) && is_array($units[$slug])) {
            return $units[$slug];
        }

        foreach ($units as $unit) {
            if (!is_array($unit)) {
                continue;
            }

            if (!empty($unit['postId']) && (int) $unit['postId'] === $post_id) {
                return $unit;
            }

            if (!empty($unit['slug']) && (string) $unit['slug'] === $slug) {
                return $unit;
            }
        }

        return [];
    }

    private function normalize_config(array $config): array {
        $site_name = $config['site_name'] ?? get_bloginfo('name');

        $normalized = [
            'site_name'                   => $site_name,
            'site_description'            => $config['site_description'] ?? get_bloginfo('description'),
            'room_post_type'              => $config['room_post_type'] ?? self::DEFAULT_ROOM_POST_TYPE,
            'book_page_slug'              => $config['book_page_slug'] ?? 'book',
            'lodging_type'                => $this->normalize_type($config['lodging_type'] ?? 'LodgingBusiness'),
            'default_unit_type'           => $config['default_unit_type'] ?? 'Accommodation',
            'name'                        => $config['name'] ?? $site_name,
            'alternate_name'              => $config['alternate_name'] ?? null,
            'legal_name'                  => $config['legal_name'] ?? null,
            'description'                 => $config['description'] ?? null,
            'telephone'                   => $this->normalize_phone($config['telephone'] ?? ''),
            'email'                       => $config['email'] ?? null,
            'currency'                    => $this->normalize_currency($config['currency'] ?? 'EUR'),
            'payment_accepted'            => $this->normalize_text_list($config['payment_accepted'] ?? []),
            'price_range'                 => $config['price_range'] ?? null,
            'checkin_time'                => $config['checkin_time'] ?? null,
            'checkout_time'               => $config['checkout_time'] ?? null,
            'pets_allowed'                => $this->normalize_bool_or_text($config['pets_allowed'] ?? null),
            'images'                      => $this->normalize_images($config['images'] ?? []),
            'logo'                        => $this->resolve_logo($config['logo'] ?? null),
            'same_as'                     => $this->normalize_url_list($config['same_as'] ?? []),
            'address'                     => $this->normalize_address($config['address'] ?? null),
            'geo'                         => $this->normalize_geo($config['geo'] ?? null),
            'has_map'                     => $config['has_map'] ?? null,
            'amenities'                   => $this->normalize_amenities($config['amenities'] ?? []),
            'number_of_rooms'             => $config['number_of_rooms'] ?? null,
            'star_rating'                 => $this->normalize_star_rating($config['star_rating'] ?? null),
            'vat_id'                      => $config['vat_id'] ?? null,
            'tax_id'                      => $config['tax_id'] ?? null,
            'contact_point'               => $this->normalize_contact_point($config['contact_point'] ?? null),
            'booking_url'                 => !empty($config['booking_url']) ? $config['booking_url'] : trailingslashit(home_url()) . trailingslashit($config['book_page_slug'] ?? 'book'),
            'enable_reserve_action'       => array_key_exists('enable_reserve_action', $config) ? (bool) $config['enable_reserve_action'] : true,
            'website_search'              => $this->normalize_website_search($config['website_search'] ?? null),
            'include_units_on_archive'    => array_key_exists('include_units_on_archive', $config) ? (bool) $config['include_units_on_archive'] : true,
            'include_units_on_front_page' => array_key_exists('include_units_on_front_page', $config) ? (bool) $config['include_units_on_front_page'] : false,
            'units'                       => is_array($config['units'] ?? null) ? $config['units'] : [],
        ];

        return $normalized;
    }

    private function default_config(): array {
        $schema  = function_exists('shaped_brand') ? (array) shaped_brand('schema') : [];
        $company = function_exists('shaped_brand') ? (string) shaped_brand('company.name') : get_bloginfo('name');
        $phone   = function_exists('shaped_brand') ? (string) shaped_brand('contact.phone') : '';
        $email   = function_exists('shaped_brand') ? (string) shaped_brand('contact.email') : '';
        $address = function_exists('shaped_brand') ? shaped_brand('contact.address') : [];
        $coords  = function_exists('shaped_brand') ? shaped_brand('contact.coordinates') : [];

        return [
            'site_name'                   => get_bloginfo('name'),
            'site_description'            => get_bloginfo('description'),
            'room_post_type'              => $schema['roomPostType'] ?? self::DEFAULT_ROOM_POST_TYPE,
            'book_page_slug'              => $schema['bookPageSlug'] ?? 'book',
            'lodging_type'                => $schema['lodgingType'] ?? 'LodgingBusiness',
            'default_unit_type'           => $schema['defaultUnitType'] ?? 'Accommodation',
            'name'                        => $schema['propertyName'] ?? $schema['name'] ?? $company,
            'alternate_name'              => $schema['alternateName'] ?? null,
            'legal_name'                  => $schema['legalName'] ?? $company,
            'description'                 => $schema['description'] ?? null,
            'telephone'                   => $phone,
            'email'                       => $email,
            'currency'                    => $schema['currency'] ?? 'EUR',
            'payment_accepted'            => $schema['paymentAccepted'] ?? ['Credit Card', 'Debit Card'],
            'price_range'                 => $schema['priceRange'] ?? null,
            'checkin_time'                => $schema['checkinTime'] ?? null,
            'checkout_time'               => $schema['checkoutTime'] ?? null,
            'pets_allowed'                => $schema['petsAllowed'] ?? null,
            'images'                      => $schema['images'] ?? [],
            'logo'                        => $schema['logo'] ?? null,
            'same_as'                     => $schema['sameAs'] ?? [],
            'address'                     => is_array($address) ? [
                'streetAddress'   => $address['street'] ?? '',
                'addressLocality' => $address['city'] ?? '',
                'postalCode'      => $address['postalCode'] ?? '',
                'addressCountry'  => $address['countryCode'] ?? '',
            ] : null,
            'geo'                         => !empty($coords) ? [
                'latitude'  => $coords['latitude'] ?? null,
                'longitude' => $coords['longitude'] ?? null,
            ] : null,
            'has_map'                     => $schema['hasMap'] ?? null,
            'amenities'                   => $schema['amenities'] ?? [],
            'number_of_rooms'             => $schema['numberOfRooms'] ?? $schema['totalUnits'] ?? null,
            'star_rating'                 => $schema['starRating'] ?? null,
            'vat_id'                      => $schema['vatID'] ?? null,
            'tax_id'                      => $schema['taxID'] ?? null,
            'contact_point'               => $schema['contactPoint'] ?? null,
            'booking_url'                 => $schema['bookingUrl'] ?? null,
            'enable_reserve_action'       => array_key_exists('enableReserveAction', $schema) ? (bool) $schema['enableReserveAction'] : true,
            'website_search'              => $schema['websiteSearch'] ?? null,
            'include_units_on_archive'    => array_key_exists('includeUnitsOnArchive', $schema) ? (bool) $schema['includeUnitsOnArchive'] : true,
            'include_units_on_front_page' => array_key_exists('includeUnitsOnFrontPage', $schema) ? (bool) $schema['includeUnitsOnFrontPage'] : false,
            'units'                       => $schema['units'] ?? [],
        ];
    }

    private function normalize_type($type) {
        if (is_array($type)) {
            $type = array_values(array_filter(array_map('strval', $type)));
            return count($type) === 1 ? $type[0] : $type;
        }

        return is_string($type) && $type !== '' ? $type : 'LodgingBusiness';
    }

    private function normalize_currency($currency): string {
        if (is_array($currency)) {
            $currency = reset($currency);
        }

        return is_string($currency) && $currency !== '' ? strtoupper($currency) : 'EUR';
    }

    private function normalize_phone(string $phone): ?string {
        $phone = preg_replace('/\s+/', '', $phone);
        return $phone !== '' ? $phone : null;
    }

    private function normalize_text_list($value): array {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $items[] = trim($item);
            }
        }

        return array_values(array_unique($items));
    }

    private function normalize_url_list($value): array {
        if (is_string($value) && $value !== '') {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                continue;
            }

            $url = esc_url_raw($item);
            if ($url) {
                $items[] = $url;
            }
        }

        return array_values(array_unique($items));
    }

    private function normalize_images($images): array {
        if (is_string($images) && $images !== '') {
            $images = [$images];
        }

        if (!is_array($images)) {
            return [];
        }

        $normalized = [];
        foreach ($images as $image) {
            if (is_string($image) && trim($image) !== '') {
                $url = esc_url_raw($image);
                if ($url) {
                    $normalized[] = $url;
                }
                continue;
            }

            if (is_array($image) && !empty($image['url'])) {
                $url = esc_url_raw((string) $image['url']);
                if ($url) {
                    $normalized[] = ['@type' => 'ImageObject', 'url' => $url];
                }
            }
        }

        return $normalized;
    }

    private function resolve_logo($logo) {
        if (is_string($logo) && $logo !== '') {
            $url = esc_url_raw($logo);
            if ($url) {
                return $url;
            }
        }

        $logo_id = get_theme_mod('custom_logo');
        if ($logo_id) {
            $url = wp_get_attachment_image_url($logo_id, 'full');
            if ($url) {
                return $url;
            }
        }

        return null;
    }

    private function normalize_address($address): ?array {
        if (!is_array($address) || empty($address)) {
            return null;
        }

        $normalized = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $address['streetAddress'] ?? $address['street'] ?? null,
            'addressLocality' => $address['addressLocality'] ?? $address['city'] ?? null,
            'postalCode'      => $address['postalCode'] ?? null,
            'addressRegion'   => $address['addressRegion'] ?? $address['region'] ?? null,
            'addressCountry'  => $address['addressCountry'] ?? $address['countryCode'] ?? null,
        ];

        return $this->clean_node($normalized);
    }

    private function normalize_geo($geo): ?array {
        if (!is_array($geo) || empty($geo)) {
            return null;
        }

        $lat = $geo['latitude'] ?? $geo['lat'] ?? null;
        $lng = $geo['longitude'] ?? $geo['lng'] ?? null;

        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float) $lat,
            'longitude' => (float) $lng,
        ];
    }

    private function normalize_amenities($amenities): array {
        if (!is_array($amenities)) {
            return [];
        }

        $normalized = [];
        foreach ($amenities as $amenity) {
            if (is_string($amenity) && trim($amenity) !== '') {
                $normalized[] = [
                    '@type' => 'LocationFeatureSpecification',
                    'name'  => trim($amenity),
                    'value' => true,
                ];
                continue;
            }

            if (!is_array($amenity)) {
                continue;
            }

            $name = $amenity['name'] ?? null;
            if (!$name) {
                continue;
            }

            $normalized[] = $this->clean_node([
                '@type' => 'LocationFeatureSpecification',
                'name'  => (string) $name,
                'value' => array_key_exists('value', $amenity) ? $amenity['value'] : true,
            ]);
        }

        return $normalized;
    }

    private function normalize_beds($beds) {
        if (is_string($beds) && trim($beds) !== '') {
            return trim($beds);
        }

        if (!is_array($beds)) {
            return null;
        }

        $normalized = [];
        foreach ($beds as $bed) {
            if (is_string($bed) && trim($bed) !== '') {
                $normalized[] = trim($bed);
                continue;
            }

            if (!is_array($bed)) {
                continue;
            }

            $entry = $this->clean_node([
                '@type'        => 'BedDetails',
                'typeOfBed'    => $bed['typeOfBed'] ?? $bed['type'] ?? null,
                'numberOfBeds' => $bed['numberOfBeds'] ?? $bed['count'] ?? null,
            ]);

            if (!empty($entry)) {
                $normalized[] = $entry;
            }
        }

        if (empty($normalized)) {
            return null;
        }

        return count($normalized) === 1 ? $normalized[0] : $normalized;
    }

    private function normalize_occupancy($occupancy, array $occupancy_details) {
        if (!empty($occupancy_details)) {
            $items = [];
            foreach ($occupancy_details as $detail) {
                if (!is_array($detail)) {
                    continue;
                }

                $items[] = $this->clean_node([
                    '@type'    => 'QuantitativeValue',
                    'name'     => $detail['name'] ?? null,
                    'value'    => isset($detail['value']) ? (float) $detail['value'] : null,
                    'minValue' => isset($detail['minValue']) ? (float) $detail['minValue'] : null,
                    'maxValue' => isset($detail['maxValue']) ? (float) $detail['maxValue'] : null,
                    'unitCode' => $detail['unitCode'] ?? 'C62',
                ]);
            }

            $items = array_values(array_filter($items));
            if (!empty($items)) {
                return count($items) === 1 ? $items[0] : $items;
            }
        }

        if ($occupancy === null || $occupancy === '') {
            return null;
        }

        if (is_numeric($occupancy)) {
            return [
                '@type'    => 'QuantitativeValue',
                'maxValue' => (float) $occupancy,
                'unitCode' => 'C62',
            ];
        }

        if (is_array($occupancy)) {
            return $this->clean_node([
                '@type'    => 'QuantitativeValue',
                'value'    => isset($occupancy['value']) ? (float) $occupancy['value'] : null,
                'minValue' => isset($occupancy['minValue']) ? (float) $occupancy['minValue'] : null,
                'maxValue' => isset($occupancy['maxValue']) ? (float) $occupancy['maxValue'] : null,
                'unitCode' => $occupancy['unitCode'] ?? 'C62',
                'name'     => $occupancy['name'] ?? null,
            ]);
        }

        return null;
    }

    private function normalize_floor_size($floor_size): ?array {
        if ($floor_size === null || $floor_size === '') {
            return null;
        }

        if (is_numeric($floor_size)) {
            return [
                '@type'    => 'QuantitativeValue',
                'value'    => (float) $floor_size,
                'unitCode' => 'MTK',
            ];
        }

        if (is_array($floor_size)) {
            return $this->clean_node([
                '@type'    => 'QuantitativeValue',
                'value'    => isset($floor_size['value']) ? (float) $floor_size['value'] : null,
                'unitCode' => $floor_size['unitCode'] ?? 'MTK',
                'unitText' => $floor_size['unitText'] ?? null,
            ]);
        }

        return null;
    }

    private function build_number_of_rooms($number_of_rooms): ?array {
        if ($number_of_rooms === null || $number_of_rooms === '') {
            return null;
        }

        if (is_numeric($number_of_rooms)) {
            return [
                '@type'    => 'QuantitativeValue',
                'value'    => (float) $number_of_rooms,
                'unitCode' => 'ROM',
            ];
        }

        if (is_array($number_of_rooms)) {
            return $this->clean_node([
                '@type'    => 'QuantitativeValue',
                'value'    => isset($number_of_rooms['value']) ? (float) $number_of_rooms['value'] : null,
                'unitCode' => $number_of_rooms['unitCode'] ?? 'ROM',
                'unitText' => $number_of_rooms['unitText'] ?? null,
            ]);
        }

        return null;
    }

    private function normalize_star_rating($rating) {
        if ($rating === null || $rating === '') {
            return null;
        }

        if (is_numeric($rating)) {
            return [
                '@type'       => 'Rating',
                'ratingValue' => (float) $rating,
            ];
        }

        if (is_array($rating)) {
            $author = null;
            if (!empty($rating['author'])) {
                if (is_string($rating['author'])) {
                    $author = [
                        '@type' => 'Organization',
                        'name'  => $rating['author'],
                    ];
                } elseif (is_array($rating['author'])) {
                    $author = $this->clean_node(array_merge(['@type' => 'Organization'], $rating['author']));
                }
            }

            return $this->clean_node([
                '@type'       => 'Rating',
                'ratingValue' => isset($rating['ratingValue']) ? (float) $rating['ratingValue'] : null,
                'bestRating'  => isset($rating['bestRating']) ? (float) $rating['bestRating'] : null,
                'worstRating' => isset($rating['worstRating']) ? (float) $rating['worstRating'] : null,
                'author'      => $author,
            ]);
        }

        return null;
    }

    private function normalize_contact_point($contact_point) {
        if (empty($contact_point)) {
            return null;
        }

        if (isset($contact_point['telephone']) || isset($contact_point['email'])) {
            $contact_point = [$contact_point];
        }

        if (!is_array($contact_point)) {
            return null;
        }

        $items = [];
        foreach ($contact_point as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = $this->clean_node([
                '@type'             => 'ContactPoint',
                'contactType'       => $item['contactType'] ?? null,
                'telephone'         => !empty($item['telephone']) ? $this->normalize_phone((string) $item['telephone']) : null,
                'email'             => $item['email'] ?? null,
                'availableLanguage' => $item['availableLanguage'] ?? null,
            ]);
        }

        $items = array_values(array_filter($items));
        if (empty($items)) {
            return null;
        }

        return count($items) === 1 ? $items[0] : $items;
    }

    private function normalize_bool_or_text($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    private function normalize_time($time): ?string {
        if (!is_string($time) || trim($time) === '') {
            return null;
        }

        $time = trim($time);
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }

    private function normalize_website_search($website_search): ?array {
        if (!is_array($website_search) || empty($website_search['urlTemplate'])) {
            return null;
        }

        return [
            'urlTemplate' => $website_search['urlTemplate'],
            'queryInput'  => $website_search['queryInput'] ?? 'required name=q',
        ];
    }

    private function clean_node($value) {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->clean_node($item);
            }
        }

        if ($this->is_list($value)) {
            $value = array_values(array_filter($value, function ($item) {
                if ($item === null || $item === '') {
                    return false;
                }

                if (is_array($item)) {
                    return !empty($item);
                }

                return true;
            }));

            return $value;
        }

        foreach ($value as $key => $item) {
            if ($item === null || $item === '') {
                unset($value[$key]);
                continue;
            }

            if (is_array($item) && empty($item)) {
                unset($value[$key]);
            }
        }

        return $value;
    }

    private function is_list(array $array): bool {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    private function is_booking_page(array $config): bool {
        $slug = $config['book_page_slug'];
        return is_page($slug);
    }

    private function current_url(): string {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? wp_parse_url(home_url(), PHP_URL_HOST);
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        $url = $scheme . $host . wp_unslash($uri);
        $url = strtok($url, '?');
        $url = strtok($url, '#');

        return esc_url_raw($url) ?: trailingslashit(home_url());
    }
}

new Shaped_Schema();