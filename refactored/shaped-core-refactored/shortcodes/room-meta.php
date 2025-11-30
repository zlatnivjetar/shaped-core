<?php
/**
 * Room Meta Shortcodes
 * 
 * Display room metadata values.
 * 
 * Usage:
 *   [shaped_meta key="mphb_room_type_size"]
 *   [shaped_meta key="mphb_room_type_bed_type"]
 *   [shaped_meta_keys] - Debug helper (admin only)
 * 
 * @package ShapedCore
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generic meta shortcode - print any post meta by key
 */
add_shortcode('shaped_meta', function($atts) {
    $atts = shortcode_atts([
        'key'     => '',
        'post_id' => 0,
    ], $atts);
    
    $key = sanitize_key($atts['key']);
    if (!$key) {
        return '';
    }
    
    $post_id = (int) $atts['post_id'] ?: get_the_ID();
    $val = get_post_meta($post_id, $key, true);
    
    if (is_array($val)) {
        return esc_html(implode(', ', $val));
    }
    
    return esc_html($val);
});

// Legacy alias
add_shortcode('pre_meta', function($atts) {
    return do_shortcode('[shaped_meta key="' . esc_attr($atts['key'] ?? '') . '"]');
});

/**
 * Debug helper - list all meta keys (admin only)
 */
add_shortcode('shaped_meta_keys', function() {
    if (!current_user_can('manage_options')) {
        return '';
    }
    
    $meta = get_post_meta(get_the_ID());
    $out = "<pre style='font:12px/1.4 monospace; background:#f5f5f5; padding:12px; overflow:auto; max-height:400px;'>";
    
    foreach ($meta as $k => $v) {
        $value = is_array($v) ? implode(', ', $v) : $v;
        $out .= esc_html($k . ': ' . $value) . "\n";
    }
    
    return $out . '</pre>';
});

// Legacy alias
add_shortcode('pre_meta_keys', function() {
    return do_shortcode('[shaped_meta_keys]');
});
