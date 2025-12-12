<?php
/**
 * Debug script for Official Prices page
 * Run from WordPress root: php -r "define('WP_USE_THEMES', false); require('wp-load.php'); require('wp-content/plugins/shaped-core/debug-official-prices.php');"
 * Or via WP-CLI: wp eval-file wp-content/plugins/shaped-core/debug-official-prices.php
 */

echo "=== Official Prices Page Debug ===\n\n";

// Check if page exists
echo "1. Checking if page exists...\n";
$page = get_page_by_path('official-prices');
if ($page) {
    echo "   ✓ Page EXISTS (ID: {$page->ID})\n";
    echo "   Status: {$page->post_status}\n";
    echo "   URL: " . get_permalink($page->ID) . "\n";
    echo "   Content: " . substr($page->post_content, 0, 100) . "...\n\n";
} else {
    echo "   ✗ Page DOES NOT EXIST\n\n";

    // Try to create it
    echo "2. Creating page...\n";
    $page_id = wp_insert_post([
        'post_title'   => 'Official Prices',
        'post_name'    => 'official-prices',
        'post_content' => '[shaped_official_prices]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => 1,
        'comment_status' => 'closed',
        'ping_status'  => 'closed',
    ], true);

    if (is_wp_error($page_id)) {
        echo "   ✗ FAILED: " . $page_id->get_error_message() . "\n\n";
    } else {
        echo "   ✓ Created page (ID: {$page_id})\n";
        echo "   URL: " . get_permalink($page_id) . "\n\n";

        // Flush rewrite rules
        echo "3. Flushing rewrite rules...\n";
        flush_rewrite_rules();
        echo "   ✓ Done\n\n";

        // Re-check
        $page = get_page_by_path('official-prices');
        if ($page) {
            echo "4. Verification: Page now exists (ID: {$page->ID})\n\n";
        }
    }
}

// Check if class exists
echo "5. Checking if Shaped_Official_Prices_Page class exists...\n";
if (class_exists('Shaped_Official_Prices_Page')) {
    echo "   ✓ Class EXISTS\n\n";
} else {
    echo "   ✗ Class DOES NOT EXIST\n";
    echo "   Trying to load...\n";
    $file = __DIR__ . '/includes/pricing/class-official-prices-page.php';
    if (file_exists($file)) {
        require_once $file;
        echo "   ✓ File loaded\n";
        if (class_exists('Shaped_Official_Prices_Page')) {
            echo "   ✓ Class now available\n\n";
        } else {
            echo "   ✗ Class still not available after loading\n\n";
        }
    } else {
        echo "   ✗ File not found: {$file}\n\n";
    }
}

// Check if shortcode is registered
echo "6. Checking if shortcode is registered...\n";
global $shortcode_tags;
if (isset($shortcode_tags['shaped_official_prices'])) {
    echo "   ✓ Shortcode 'shaped_official_prices' IS registered\n";
    echo "   Handler: " . print_r($shortcode_tags['shaped_official_prices'], true) . "\n\n";
} else {
    echo "   ✗ Shortcode 'shaped_official_prices' NOT registered\n";
    echo "   Registered shortcodes: " . implode(', ', array_keys($shortcode_tags)) . "\n\n";
}

// Test shortcode output
if (isset($shortcode_tags['shaped_official_prices'])) {
    echo "7. Testing shortcode output...\n";
    $output = do_shortcode('[shaped_official_prices]');
    $preview = substr(strip_tags($output), 0, 200);
    echo "   Output preview: {$preview}...\n";
    echo "   Length: " . strlen($output) . " chars\n\n";
}

echo "=== Debug Complete ===\n";
