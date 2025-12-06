<?php
/**
 * Room Details Shortcode
 * 
 * Displays room description with proper formatting.
 * 
 * Usage: [shaped_room_details] or [shaped_room_details id="123"]
 * 
 * @package ShapedCore
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('shaped_room_details', 'shaped_room_details_shortcode');

function shaped_room_details_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0,
    ], $atts);
    
    // Get room ID
    $room_id = (int) $atts['id'];
    
    if (!$room_id) {
        $room_id = get_the_ID();
    }
    
    // Try query var for single room pages
    if (!$room_id) {
        $room_id = get_query_var('mphb_room_type_id');
    }
    
    if (!$room_id) {
        return '';
    }
    
    // Get room post
    $room = get_post($room_id);
    if (!$room || $room->post_type !== 'mphb_room_type') {
        return '';
    }
    
    // Build output
    $output = '<div class="shaped-room-details">';
    
    $description = $room->post_content;
    if ($description) {
        $output .= '<div class="room-description">';
        
        // Apply only basic formatting filters
        $description = wpautop($description);
        $description = wptexturize($description);
        $description = convert_smilies($description);
        
        $output .= $description;
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}
