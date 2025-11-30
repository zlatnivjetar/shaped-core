<?php
/**
 * Backward compatibility function wrappers
 * Centralizes all procedural wrappers to avoid circular dependencies
 */

if (!function_exists('shaped_get_payment_context')) {
    function shaped_get_payment_context($booking) {
        return Shaped_Payment_Processor::get_payment_context($booking);
    }
}

if (!function_exists('shaped_calculate_final_amount')) {
    function shaped_calculate_final_amount($booking) {
        return Shaped_Pricing::calculate_final_amount($booking);
    }
}

if (!function_exists('shaped_detach_payment_method')) {
    function shaped_detach_payment_method($booking_id) {
        if (class_exists('Shaped_Payment_Processor')) {
            $processor = new Shaped_Payment_Processor();
            return $processor->detach_payment_method((int)$booking_id);
        }
        return false;
    }
}

if (!function_exists('shaped_mark_booking_abandoned')) {
    function shaped_mark_booking_abandoned($booking_id) {
        Shaped_Booking_Manager::mark_booking_abandoned((int)$booking_id);
    }
}