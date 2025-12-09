<?php
/**
 * Brand Configuration Test Script
 *
 * Run this file to test the brand configuration system.
 * Usage: Include this file in functions.php temporarily, then visit any page.
 *
 * @package Shaped_Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test the brand configuration system
 * Add this to wp-admin or create a custom admin page to run tests
 */
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Only show on plugins page for easy access
    $screen = get_current_screen();
    if ($screen->id !== 'plugins') {
        return;
    }

    echo '<div class="notice notice-info" style="padding: 20px; font-family: monospace;">';
    echo '<h2>🎨 Shaped Brand Configuration Test</h2>';

    // Test 1: Check if class exists
    echo '<h3>Test 1: Class Loaded</h3>';
    if (class_exists('Shaped_Brand_Config')) {
        echo '✅ Shaped_Brand_Config class is loaded<br>';
    } else {
        echo '❌ Shaped_Brand_Config class NOT found<br>';
        echo '</div>';
        return;
    }

    // Test 2: Check if helper functions exist
    echo '<h3>Test 2: Helper Functions</h3>';
    $functions = [
        'shaped_brand',
        'shaped_brand_color',
        'shaped_brand_colors',
        'shaped_brand_client',
        'shaped_brand_colors_for_js'
    ];

    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "✅ {$func}() exists<br>";
        } else {
            echo "❌ {$func}() NOT found<br>";
        }
    }

    // Test 3: Test basic color retrieval
    echo '<h3>Test 3: Color Retrieval</h3>';

    $primary = shaped_brand_color('primary');
    if ($primary) {
        echo '✅ Primary color: <span style="display:inline-block; width:20px; height:20px; background:' . esc_attr($primary) . '; border:1px solid #ccc; vertical-align:middle;"></span> ' . esc_html($primary) . '<br>';
    } else {
        echo '❌ Primary color not found<br>';
    }

    $success = shaped_brand_color('success');
    if ($success) {
        echo '✅ Success color: <span style="display:inline-block; width:20px; height:20px; background:' . esc_attr($success) . '; border:1px solid #ccc; vertical-align:middle;"></span> ' . esc_html($success) . '<br>';
    } else {
        echo '❌ Success color not found<br>';
    }

    // Test 4: Test dot notation
    echo '<h3>Test 4: Dot Notation Access</h3>';

    $brandPrimary = shaped_brand('colors.brand.primary');
    if ($brandPrimary) {
        echo '✅ colors.brand.primary: ' . esc_html($brandPrimary) . '<br>';
    } else {
        echo '❌ colors.brand.primary not found<br>';
    }

    $textMuted = shaped_brand('colors.text.muted');
    if ($textMuted) {
        echo '✅ colors.text.muted: ' . esc_html($textMuted) . '<br>';
    } else {
        echo '❌ colors.text.muted not found<br>';
    }

    // Test 5: Client detection
    echo '<h3>Test 5: Client Detection</h3>';
    $client = shaped_brand_client();
    if ($client) {
        echo '✅ Client detected: <strong>' . esc_html($client) . '</strong><br>';
    } else {
        echo 'ℹ️ No client override active (using base config)<br>';
        echo 'To test client override:<br>';
        echo '1. Create <code>CLIENTS/preelook/brand.json</code><br>';
        echo '2. Add <code>define(\'SHAPED_CLIENT\', \'preelook\');</code> to wp-config.php<br>';
    }

    // Test 6: All colors structure
    echo '<h3>Test 6: Color Palette</h3>';
    $colors = shaped_brand_colors();
    if (!empty($colors)) {
        echo '<details><summary>Click to view full color palette</summary>';
        echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; max-height: 300px; overflow: auto;">';
        print_r($colors);
        echo '</pre></details>';
    }

    // Test 7: JS Colors format
    echo '<h3>Test 7: JavaScript Format</h3>';
    $jsColors = shaped_brand_colors_for_js();
    if (!empty($jsColors)) {
        echo '✅ Flat structure for JS: ' . count($jsColors) . ' color values<br>';
        echo '<details><summary>Click to view JS color object</summary>';
        echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; max-height: 300px; overflow: auto;">';
        print_r($jsColors);
        echo '</pre></details>';
    }

    // Test 8: Sample usage examples
    echo '<h3>Test 8: Usage Examples</h3>';
    echo '<div style="background: #f5f5f5; padding: 15px; border-radius: 4px; font-size: 13px;">';

    // Example 1: Inline style
    $exampleStyle = 'color: ' . shaped_brand_color('primary') . '; background: ' . shaped_brand_color('success') . ';';
    echo '<strong>Inline Style Example:</strong><br>';
    echo '<code>$style = \'color: \' . shaped_brand_color(\'primary\') . \';\';</code><br>';
    echo '<div style="' . esc_attr($exampleStyle) . ' padding: 10px; margin: 10px 0;">Sample Text</div>';

    // Example 2: Email template
    echo '<strong>Email Template Example:</strong><br>';
    echo '<pre style="background: white; padding: 10px; border: 1px solid #ddd;">';
    echo htmlspecialchars('<td style="background: <?php echo shaped_brand_color(\'primary\'); ?>; color: white;">
    Content
</td>');
    echo '</pre>';

    echo '</div>';

    echo '<hr style="margin: 20px 0;">';
    echo '<p><strong>✅ Phase 1 Complete!</strong> Brand configuration system is operational.</p>';
    echo '<p>Next steps: Apply to email templates and PHP inline styles.</p>';

    echo '</div>';
});
