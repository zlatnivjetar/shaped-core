<?php
/**
 * Landing Flow Cookie Manager
 *
 * Sets a cookie when the user visits /book so that downstream pages
 * (/search-results, /checkout) can detect the landing-page flow and
 * swap Elementor header/footer templates accordingly.
 *
 * @package Shaped_Core
 * @subpackage Landing_Flow
 */

namespace Shaped\Modules\LandingFlow;

if (!defined('ABSPATH')) {
    exit;
}

class Cookie_Manager {

    const COOKIE_NAME = 'shaped_landing_flow';
    const COOKIE_TTL  = 7200; // 2 hours

    /**
     * Set cookie when user visits the /book page.
     */
    public static function maybe_set_cookie(): void {
        if (!is_page('book')) {
            return;
        }

        if (self::is_active()) {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            '1',
            [
                'expires'  => time() + self::COOKIE_TTL,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly'  => true,
                'samesite' => 'Lax',
            ]
        );

        // Make available in the current request (setcookie only sends in response)
        $_COOKIE[self::COOKIE_NAME] = '1';
    }

    /**
     * Clear cookie when user reaches /thank-you (booking complete).
     */
    public static function maybe_clear_cookie(): void {
        if (!self::is_active()) {
            return;
        }

        if (is_page('thank-you')) {
            self::clear();
        }
    }

    /**
     * Check if the landing flow cookie is active.
     */
    public static function is_active(): bool {
        return !empty($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Clear the cookie.
     */
    public static function clear(): void {
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly'  => true,
                'samesite' => 'Lax',
            ]
        );
        unset($_COOKIE[self::COOKIE_NAME]);
    }
}
