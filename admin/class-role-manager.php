<?php
/**
 * Role Manager
 * Creates and manages the shaped_operator role for hotel staff
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Role_Manager {

    /**
     * Role slug
     */
    const ROLE_OPERATOR = 'shaped_operator';

    /**
     * Initialize role management
     */
    public static function init(): void {
        // Register role on plugin activation (called from main plugin)
        add_action('shaped_create_roles', [__CLASS__, 'create_roles']);

        // Ensure role exists on admin init (safety net)
        add_action('admin_init', [__CLASS__, 'maybe_create_roles']);
    }

    /**
     * Check if roles need to be created
     */
    public static function maybe_create_roles(): void {
        if (!get_role(self::ROLE_OPERATOR)) {
            self::create_roles();
        }

        // Ensure administrators have shaped_view_ops capability
        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('shaped_view_ops')) {
            $admin_role->add_cap('shaped_view_ops');
        }
    }

    /**
     * Create the operator role
     */
    public static function create_roles(): void {
        // Remove existing role first to update capabilities
        remove_role(self::ROLE_OPERATOR);

        // Create operator role with specific capabilities
        add_role(
            self::ROLE_OPERATOR,
            'Operator',
            self::get_operator_capabilities()
        );
    }

    /**
     * Get capabilities for the operator role
     * Operators can manage day-to-day hotel operations but not system settings
     */
    public static function get_operator_capabilities(): array {
        return [
            // WordPress core - reading
            'read'                       => true,

            // Pages - view and edit (for content updates)
            'edit_pages'                 => true,
            'edit_published_pages'       => true,
            'publish_pages'              => true,
            'delete_pages'               => false,  // Can't delete pages
            'edit_others_pages'          => true,

            // Media library
            'upload_files'               => true,
            'edit_files'                 => true,

            // MotoPress Hotel Booking - Bookings
            'edit_mphb_booking'          => true,
            'read_mphb_booking'          => true,
            'delete_mphb_booking'        => false,  // Can't delete bookings
            'edit_mphb_bookings'         => true,
            'edit_others_mphb_bookings'  => true,
            'publish_mphb_bookings'      => true,
            'read_private_mphb_bookings' => true,

            // MotoPress - Room Types (view inventory)
            'edit_mphb_room_type'          => true,
            'read_mphb_room_type'          => true,
            'delete_mphb_room_type'        => false,
            'edit_mphb_room_types'         => true,
            'edit_others_mphb_room_types'  => true,
            'publish_mphb_room_types'      => true,
            'read_private_mphb_room_types' => true,

            // MotoPress - Rooms (accommodation units)
            'edit_mphb_room'          => true,
            'read_mphb_room'          => true,
            'delete_mphb_room'        => false,
            'edit_mphb_rooms'         => true,
            'edit_others_mphb_rooms'  => true,
            'publish_mphb_rooms'      => true,
            'read_private_mphb_rooms' => true,

            // MotoPress - Rates
            'edit_mphb_rate'          => true,
            'read_mphb_rate'          => true,
            'delete_mphb_rate'        => false,
            'edit_mphb_rates'         => true,
            'edit_others_mphb_rates'  => true,
            'publish_mphb_rates'      => true,
            'read_private_mphb_rates' => true,

            // MotoPress - Seasons
            'edit_mphb_season'          => true,
            'read_mphb_season'          => true,
            'delete_mphb_season'        => false,
            'edit_mphb_seasons'         => true,
            'edit_others_mphb_seasons'  => true,
            'publish_mphb_seasons'      => true,
            'read_private_mphb_seasons' => true,

            // Shaped Reviews
            'edit_shaped_review'          => true,
            'read_shaped_review'          => true,
            'delete_shaped_review'        => false,
            'edit_shaped_reviews'         => true,
            'edit_others_shaped_reviews'  => true,
            'publish_shaped_reviews'      => true,
            'read_private_shaped_reviews' => true,

            // Shaped custom capability for Ops access
            'shaped_view_ops'            => true,
        ];
    }

    /**
     * Check if current user is an operator (not admin)
     */
    public static function is_operator(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array(self::ROLE_OPERATOR, (array) $user->roles, true);
    }

    /**
     * Check if current user is admin (has manage_options)
     */
    public static function is_admin(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Check if current user can access Shaped Ops
     */
    public static function can_access_ops(): bool {
        return current_user_can('shaped_view_ops') || current_user_can('manage_options');
    }

    /**
     * Remove roles on plugin deactivation (optional - called from main plugin)
     */
    public static function remove_roles(): void {
        remove_role(self::ROLE_OPERATOR);
    }
}
