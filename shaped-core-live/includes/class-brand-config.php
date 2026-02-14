<?php
/**
 * Brand Configuration Loader
 *
 * Configuration Priority:
 * 1. MU-Plugin: shaped_get_client_config() function (recommended)
 * 2. Legacy: brand.json + client-specific JSON overrides
 *
 * For multi-client deployments, use MU-plugin approach for security.
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Brand_Config
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Merged brand configuration
     */
    private $config = null;

    /**
     * Current client identifier
     */
    private $client = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor - singleton pattern
     */
    private function __construct() {
        $this->load_config();
    }

    /**
     * Load and merge brand configuration
     *
     * Priority:
     * 1. MU-Plugin: shaped_get_client_config() function
     * 2. Legacy: brand.json with client-specific overrides
     */
    private function load_config() {
        // Detect current client first
        $this->client = $this->detect_client();

        // Method 1: MU-Plugin Configuration (Recommended)
        if (function_exists('shaped_get_client_config')) {
            $this->config = shaped_get_client_config();
            error_log('[Shaped Brand] Loaded config from MU-plugin' . ($this->client ? ' for client: ' . $this->client : ''));
            return;
        }

        // Method 2: Legacy JSON Configuration (Backward Compatibility)
        $base_path = $this->get_config_path('brand.json');
        $base_config = $this->load_json_file($base_path);

        if (!$base_config) {
            error_log('[Shaped Brand] Failed to load base brand.json');
            $this->config = [];
            return;
        }

        // Load client-specific override if exists
        if ($this->client) {
            $client_path = $this->get_client_config_path($this->client, $this->client . '.json');
            $client_config = $this->load_json_file($client_path);

            if ($client_config) {
                $base_config = $this->deep_merge($base_config, $client_config);
                error_log('[Shaped Brand] Loaded brand config for client: ' . $this->client);
            }
        }

        $this->config = $base_config;
    }

    /**
     * Detect current client
     *
     * Priority:
     * 1. SHAPED_CLIENT constant (defined in MU-plugin or wp-config.php)
     * 2. Auto-detect by domain (legacy)
     * 3. Default to null (uses base config only)
     */
    private function detect_client() {
        // Method 1: Constant (recommended - defined in MU-plugin)
        if (defined('SHAPED_CLIENT')) {
            return SHAPED_CLIENT;
        }

        // Method 2: Auto-detect by domain (legacy, not recommended)
        if (isset($_SERVER['HTTP_HOST'])) {
            $domain = $_SERVER['HTTP_HOST'];

            // Extract domain name without TLD as potential client name
            // e.g., "acme-hotel.com" -> "acme-hotel"
            // e.g., "www.acme-hotel.com" -> "acme-hotel"
            $parts = explode('.', $domain);

            // Remove 'www' if present
            if ($parts[0] === 'www' && count($parts) > 1) {
                array_shift($parts);
            }

            if (count($parts) > 0) {
                $potential_client = $parts[0];

                // Check if this client directory exists
                $client_dir = $this->get_clients_dir() . '/' . $potential_client;
                if (is_dir($client_dir)) {
                    return $potential_client;
                }
            }
        }

        // Method 3: No client detected - use base config
        return null;
    }

    /**
     * Get value from config using dot notation
     *
     * @param string $path Dot-separated path (e.g., 'colors.brand.primary')
     * @param mixed $default Default value if path not found
     * @return mixed
     */
    public function get($path, $default = null) {
        $keys = explode('.', $path);
        $value = $this->config;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Get color value by key
     *
     * @param string $key Color key (e.g., 'primary', 'success', 'textMuted')
     * @return string|null Color value or null if not found
     */
    public function get_color($key) {
        // Common color paths
        $paths = [
            'colors.brand.' . $key,
            'colors.semantic.' . $key,
            'colors.text.' . $key,
            'colors.surface.' . $key,
            'colors.border.' . $key,
        ];

        foreach ($paths as $path) {
            $value = $this->get($path);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get all brand colors
     *
     * @return array
     */
    public function get_all_colors() {
        return $this->get('colors', []);
    }

    /**
     * Get current client identifier
     *
     * @return string|null
     */
    public function get_client() {
        return $this->client;
    }

    /**
     * Get full configuration array
     *
     * @return array
     */
    public function get_all() {
        return $this->config;
    }

    /**
     * Load JSON file and return decoded array
     *
     * @param string $path File path
     * @return array|null
     */
    private function load_json_file($path) {
        if (!file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[Shaped Brand] JSON decode error in ' . $path . ': ' . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    /**
     * Deep merge two arrays
     *
     * @param array $base Base array
     * @param array $override Override array
     * @return array Merged array
     */
    private function deep_merge($base, $override) {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deep_merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    /**
     * Get path to config directory
     *
     * @param string $file Filename
     * @return string
     */
    private function get_config_path($file) {
        return dirname(SHAPED_PLUGIN_FILE) . '/config/' . $file;
    }

    /**
     * Get path to clients directory
     *
     * @return string
     */
    private function get_clients_dir() {
        return dirname(SHAPED_PLUGIN_FILE) . '/clients';
    }

    /**
     * Get path to client-specific config file
     *
     * @param string $client Client identifier
     * @param string $file Filename
     * @return string
     */
    private function get_client_config_path($client, $file) {
        return $this->get_clients_dir() . '/' . $client . '/' . $file;
    }

    /**
     * Reload configuration (useful for testing)
     */
    public function reload() {
        $this->config = null;
        $this->client = null;
        $this->load_config();
    }
}
