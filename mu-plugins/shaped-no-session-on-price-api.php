<?php
if (!defined('ABSPATH')) exit;

add_action('muplugins_loaded', function () {
	if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/shaped/v1/price') === false) return;
	if (!defined('SHAPED_NO_SESSION')) define('SHAPED_NO_SESSION', true);
}, 1);
