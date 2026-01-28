<?php
/**
 * Stripe Configuration Handler
 *
 * Manages Stripe API credentials with priority system:
 * 1. Constants in wp-config.php (SHAPED_STRIPE_SECRET, SHAPED_STRIPE_WEBHOOK)
 * 2. Database storage (encrypted)
 *
 * Security: Database values are encrypted using wp_salt().
 *
 * @package Shaped_Core
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Stripe_Config {

    /**
     * Option keys for database-stored credentials
     */
    const OPT_STRIPE_SECRET   = 'shaped_stripe_secret_key';
    const OPT_STRIPE_WEBHOOK  = 'shaped_stripe_webhook_secret';

    /**
     * Get Stripe secret key (constant takes priority, then database)
     */
    public static function get_stripe_secret(): string {
        // Constant defined in wp-config.php takes priority
        if (defined('SHAPED_STRIPE_SECRET') && SHAPED_STRIPE_SECRET !== '') {
            return SHAPED_STRIPE_SECRET;
        }

        // Fall back to database
        $encrypted = get_option(self::OPT_STRIPE_SECRET, '');
        return $encrypted ? self::decrypt($encrypted) : '';
    }

    /**
     * Get Stripe webhook secret (constant takes priority, then database)
     */
    public static function get_stripe_webhook(): string {
        // Constant defined in wp-config.php takes priority
        if (defined('SHAPED_STRIPE_WEBHOOK') && SHAPED_STRIPE_WEBHOOK !== '') {
            return SHAPED_STRIPE_WEBHOOK;
        }

        // Fall back to database
        $encrypted = get_option(self::OPT_STRIPE_WEBHOOK, '');
        return $encrypted ? self::decrypt($encrypted) : '';
    }

    /**
     * Check if Stripe credentials are from constants
     */
    public static function stripe_uses_constants(): bool {
        $secret_from_const = defined('SHAPED_STRIPE_SECRET') && SHAPED_STRIPE_SECRET !== '';
        $webhook_from_const = defined('SHAPED_STRIPE_WEBHOOK') && SHAPED_STRIPE_WEBHOOK !== '';
        return $secret_from_const || $webhook_from_const;
    }

    /**
     * Save Stripe secret key to database (encrypted)
     */
    public static function save_stripe_secret(string $value): bool {
        if (empty($value)) {
            return false;
        }
        return update_option(self::OPT_STRIPE_SECRET, self::encrypt($value));
    }

    /**
     * Save Stripe webhook secret to database (encrypted)
     */
    public static function save_stripe_webhook(string $value): bool {
        if (empty($value)) {
            return false;
        }
        return update_option(self::OPT_STRIPE_WEBHOOK, self::encrypt($value));
    }

    /**
     * Simple encryption using wp_salt
     */
    private static function encrypt(string $value): string {
        if (empty($value)) {
            return '';
        }
        $key = wp_salt('auth');
        $iv = substr(md5($key), 0, 16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($encrypted);
    }

    /**
     * Simple decryption using wp_salt
     */
    private static function decrypt(string $value): string {
        if (empty($value)) {
            return '';
        }
        $key = wp_salt('auth');
        $iv = substr(md5($key), 0, 16);
        $decrypted = openssl_decrypt(base64_decode($value), 'AES-256-CBC', $key, 0, $iv);
        return $decrypted ?: '';
    }

    /**
     * Mask a key for display (show last 4 chars)
     */
    public static function mask_key(string $key): string {
        if (strlen($key) < 8) {
            return str_repeat('•', strlen($key));
        }
        return str_repeat('•', strlen($key) - 4) . substr($key, -4);
    }

    /**
     * Get health check results for Stripe configuration
     */
    public static function get_health_checks(): array {
        $checks = [];

        // Stripe SDK
        $stripe_sdk_exists = file_exists(SHAPED_DIR . 'vendor/stripe-php/init.php')
                          || file_exists(WP_CONTENT_DIR . '/mu-plugins/stripe-php/init.php');
        $checks[] = [
            'label'        => 'Stripe PHP SDK',
            'status'       => $stripe_sdk_exists,
            'details'      => $stripe_sdk_exists ? 'Found in vendor or mu-plugins' : 'Not found',
            'action_url'   => '',
            'action_label' => '',
        ];

        // Stripe Secret Key
        $stripe_secret = self::get_stripe_secret();
        $checks[] = [
            'label'        => 'Stripe Secret Key',
            'status'       => !empty($stripe_secret),
            'details'      => !empty($stripe_secret)
                ? 'Configured (' . self::mask_key($stripe_secret) . ')'
                : 'Not configured',
            'action_url'   => admin_url('admin.php?page=shaped-pricing'),
            'action_label' => 'Configure',
        ];

        // Stripe Webhook Secret
        $stripe_webhook = self::get_stripe_webhook();
        $checks[] = [
            'label'        => 'Stripe Webhook Secret',
            'status'       => !empty($stripe_webhook),
            'details'      => !empty($stripe_webhook)
                ? 'Configured (' . self::mask_key($stripe_webhook) . ')'
                : 'Not configured',
            'action_url'   => admin_url('admin.php?page=shaped-pricing'),
            'action_label' => 'Configure',
        ];

        return $checks;
    }
}
