<?php
/**
 * Default Configuration Values
 * Centralized defaults for pricing, URLs, and other settings
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default discount percentages per room type
 * Can be filtered via 'shaped/pricing/discount_defaults'
 */
return [
    'discounts' => [
        'suite'        => 10,
        'apartment'    => 10,
        'triple-room'  => 10,
        'double-room'  => 10,
    ],

    /**
     * Default redirect URLs (can be overridden via constants in wp-config.php)
     */
    'urls' => [
        'success' => home_url('/thank-you/?booking_id={BOOKING_ID}'),
        'cancel'  => home_url('/checkout'),
    ],

    /**
     * Payment settings
     */
    'payment' => [
        'currency'              => 'EUR',
        'abandoned_timeout'     => 10, // minutes
        'charge_before_checkin' => 1, // days
    ],

    /**
     * Email settings
     */
    'email' => [
        'from_name'  => get_bloginfo('name'),
        'from_email' => get_option('admin_email'),
    ],

    /**
     * RoomCloud defaults
     */
    'roomcloud' => [
        'status_map' => [
            'SUBMITTED' => '2',
            'CONFIRMED' => '4',
            'CANCELLED' => '7',
        ],
    ],
];
