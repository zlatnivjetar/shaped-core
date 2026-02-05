<?php
/**
 * Landing Page Amenity Priority List
 *
 * Ordered list of amenity slugs for landing page room cards.
 * The template picks the first N matches from this list for each room.
 *
 * 'sleeps' is a special token: it pulls total capacity (adults + children)
 * from MPHB rather than from the facility taxonomy.
 *
 * To expand: add new slugs from config/amenities-registry.json in desired order.
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'sleeps',
    'swimming-pool',
    // Keep all kitchen-related slugs before view to honor registry top priority.
    'full-kitchen',
    'kitchen',
    'kitchenette',
    'sea-view',
    'private-parking',
    'smoke-free',
    'pet-friendly',
    'heating-system',
    'air-conditioning',
    'wifi',
    'room-service',
    'private-bathroom',
    'bedding',
    // Additional display options after top-priority set.
    'private-balcony',
    'rooftop-terrace',
];
