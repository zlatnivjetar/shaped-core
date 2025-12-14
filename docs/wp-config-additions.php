<?php
/**
 * Shaped Core - Recommended wp-config.php Additions
 *
 * Add these constants to your wp-config.php file to enable optional features.
 * Place them before the "That's all, stop editing!" comment.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Price API - Optional Authentication
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Require API key for /wp-json/shaped/v1/price endpoint
 *
 * When enabled, all requests to the price endpoint must include a valid API key
 * via X-Shaped-Key header or 'key' query parameter.
 *
 * Default: false (public access)
 * Recommended: false (for LLM/bot access), true (if concerned about abuse)
 */
// define('SHAPED_PRICE_API_REQUIRE_KEY', false);

/**
 * API key for price endpoint authentication
 *
 * Generate a random secret key (e.g., using: openssl rand -hex 32)
 * Only required if SHAPED_PRICE_API_REQUIRE_KEY is true.
 *
 * Example:
 * define('SHAPED_PRICE_API_KEY', 'a1b2c3d4e5f6789012345678901234567890abcd');
 */
// define('SHAPED_PRICE_API_KEY', 'your-random-secret-key-here');

// ─────────────────────────────────────────────────────────────────────────────
// Example: Enable API Key Protection
// ─────────────────────────────────────────────────────────────────────────────

/*
// Uncomment these lines to enable API key requirement:

define('SHAPED_PRICE_API_REQUIRE_KEY', true);
define('SHAPED_PRICE_API_KEY', 'replace-with-your-random-key');

// Then test with:
// curl -H "X-Shaped-Key: replace-with-your-random-key" "https://yoursite.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=2"
*/
