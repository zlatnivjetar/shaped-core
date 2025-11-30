<?php
/*
 * Room meta shortcodes used on every room page:
 * - [pre_meta key="mphb_room_type_size"]
 * - [pre_meta key="mphb_room_type_bed_type"]
 */

// 1) Generic: print any post meta by key
add_shortcode('pre_meta', function($atts){
  $key = isset($atts['key']) ? sanitize_key($atts['key']) : '';
  if (!$key) return '';
  $val = get_post_meta(get_the_ID(), $key, true);
  return esc_html(is_array($val) ? implode(', ', $val) : $val);
});

// 2) One‑time helper: list all meta keys for the current room (for discovery)
add_shortcode('pre_meta_keys', function(){
  if ( ! current_user_can('manage_options') ) return '';
  $meta = get_post_meta(get_the_ID());
  $out  = "<pre style='font:12px/1.4 monospace'>";
  foreach ($meta as $k => $v) { $out .= esc_html($k.': '.(is_array($v)?implode(', ',$v):$v))."\n"; }
  return $out.'</pre>';
});
