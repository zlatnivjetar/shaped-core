<?php

// ==========================
// 1) LOAD PHP FILES
// ==========================
foreach (glob(__DIR__ . '/php/*.php') as $file) {
    require_once $file;
}


// ==========================
// 2) LOAD CSS + JS
// ==========================
add_action('wp_enqueue_scripts', function () {

    $plugin_url = plugins_url('', __FILE__);

    // ---- CSS (supports nested folders) ----
    $css_root = __DIR__ . '/css';
    $css_files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($css_root));

    foreach ($css_files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'css') continue;

        // compute relative path
        $relative = str_replace($css_root, '', $file);
        $relative = ltrim($relative, DIRECTORY_SEPARATOR);

        wp_enqueue_style(
            'shaped-css-' . md5($relative),
            $plugin_url . '/css/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative),
            [],
            null
        );
    }

    // ---- JS ----
    $js_root = __DIR__ . '/js';
    foreach (glob($js_root . '/*.js') as $file) {
        $basename = basename($file);

        wp_enqueue_script(
            'shaped-js-' . md5($basename),
            $plugin_url . '/js/' . $basename,
            ['jquery'],
            null,
            true
        );
    }
});
