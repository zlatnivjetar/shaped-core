<?php
/**
 * Shaped Core Autoloader
 * 
 * Maps class names to file paths for automatic loading.
 * Uses a static class map for predictable loading order.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Loader {
    
    /**
     * Class name to file path mapping
     * Paths are relative to SHAPED_DIR
     */
    private static array $class_map = [
        // Core classes
        'Shaped_Pricing'            => 'core/class-pricing.php',
        'Shaped_Payment_Processor'  => 'core/class-payment-processor.php',
        'Shaped_Booking_Manager'    => 'core/class-booking-manager.php',

        // Include classes
        'Shaped_Assets'                    => 'includes/class-assets.php',
        'Shaped_Admin'                     => 'includes/class-admin.php',
        'Shaped_Amenity_Mapper'            => 'includes/class-amenity-mapper.php',
        'Shaped_Brand_Config'              => 'includes/class-brand-config.php',
        'Shaped_Dashboard_Api'             => 'includes/class-dashboard-api.php',
        'Shaped_Font_Loader'               => 'includes/class-font-loader.php',
        'Shaped_Design_Tokens_Generator'   => 'includes/class-design-tokens-generator.php',
        'Shaped_Book_Search_Form'          => 'includes/class-book-search-form.php',

        // Admin classes
        'Shaped_Role_Manager'           => 'admin/class-role-manager.php',
        'Shaped_Menu_Controller'        => 'admin/class-menu-controller.php',
        'Shaped_Noise_Control'          => 'admin/class-noise-control.php',
        'Shaped_Reviews_Dashboard'      => 'admin/class-reviews-dashboard.php',
        'Shaped_Review_Strip_Settings'  => 'admin/class-review-strip-settings.php',

        // Schema
        'Shaped_Schema_Markup'      => 'schema/class-markup.php',
    ];
    
    /**
     * Register the autoloader
     */
    public static function register(): void {
        spl_autoload_register([__CLASS__, 'autoload']);
    }
    
    /**
     * Autoload callback
     */
    public static function autoload(string $class): void {
        // Only handle Shaped_ prefixed classes
        if (strpos($class, 'Shaped_') !== 0) {
            return;
        }
        
        if (isset(self::$class_map[$class])) {
            $file = SHAPED_DIR . self::$class_map[$class];
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
    
    /**
     * Manually register a class (for modules to use)
     */
    public static function add_class(string $class_name, string $relative_path): void {
        self::$class_map[$class_name] = $relative_path;
    }
}
