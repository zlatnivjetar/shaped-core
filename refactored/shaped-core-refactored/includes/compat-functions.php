<?php
/**
 * Backward Compatibility Function Wrappers
 * 
 * Provides procedural function wrappers for class methods.
 * Centralizes all wrappers to avoid circular dependencies.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shaped_get_payment_context')) {
    /**
     * Get payment context for a booking
     */
    function shaped_get_payment_context($booking) {
        if (class_exists('Shaped_Payment_Processor')) {
            return Shaped_Payment_Processor::get_payment_context($booking);
        }
        return null;
    }
}

if (!function_exists('shaped_calculate_final_amount')) {
    /**
     * Calculate discounted final amount for a booking
     */
    function shaped_calculate_final_amount($booking) {
        if (class_exists('Shaped_Pricing')) {
            return Shaped_Pricing::calculate_final_amount($booking);
        }
        return $booking ? (float) $booking->getTotalPrice() : 0.0;
    }
}

if (!function_exists('shaped_detach_payment_method')) {
    /**
     * Detach saved payment method from Stripe customer
     */
    function shaped_detach_payment_method($booking_id) {
        if (class_exists('Shaped_Payment_Processor')) {
            $processor = new Shaped_Payment_Processor();
            return $processor->detach_payment_method((int) $booking_id);
        }
        return false;
    }
}

if (!function_exists('shaped_mark_booking_abandoned')) {
    /**
     * Mark a booking as abandoned
     */
    function shaped_mark_booking_abandoned($booking_id) {
        if (class_exists('Shaped_Booking_Manager')) {
            Shaped_Booking_Manager::mark_booking_abandoned((int) $booking_id);
        }
    }
}

if (!function_exists('shaped_get_room_discount')) {
    /**
     * Get discount percentage for a room type
     */
    function shaped_get_room_discount(string $room_slug): float {
        if (class_exists('Shaped_Pricing')) {
            $config = Shaped_Pricing::get_discounts_config();
            return (float) ($config[$room_slug] ?? 0);
        }
        return 0.0;
    }
}

if (!function_exists('shaped_clear_scheduled_charge')) {
    /**
     * Clear scheduled charge for a booking
     */
    function shaped_clear_scheduled_charge(int $booking_id): void {
        if (class_exists('Shaped_Payment_Processor')) {
            Shaped_Payment_Processor::clear_scheduled_charge($booking_id);
        }
    }
}
