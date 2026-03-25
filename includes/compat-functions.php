<?php
/**
 * Backward Compatibility Function Wrappers
 * 
 * Provides procedural function wrappers for class methods.
 * Centralizes all wrappers to avoid circular dependencies.
 * 
 * @package Shaped_Core
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/* =========================================================================
 * Payment Processor Functions
 * ========================================================================= */

if (!function_exists('shaped_get_payment_context')) {
    function shaped_get_payment_context($booking) {
        if (class_exists('Shaped_Payment_Processor')) {
            return Shaped_Payment_Processor::get_payment_context($booking);
        }
        return null;
    }
}

if (!function_exists('shaped_detach_payment_method')) {
    function shaped_detach_payment_method($booking_id) {
        if (class_exists('Shaped_Payment_Processor')) {
            return Shaped_Payment_Processor::detach_saved_payment_method((int) $booking_id);
        }
        return false;
    }
}

if (!function_exists('shaped_clear_scheduled_charge')) {
    function shaped_clear_scheduled_charge(int $booking_id): void {
        if (class_exists('Shaped_Payment_Processor')) {
            Shaped_Payment_Processor::clear_scheduled_charge($booking_id);
        }
    }
}

/* =========================================================================
 * Pricing Functions
 * ========================================================================= */

if (!function_exists('shaped_calculate_final_amount')) {
    function shaped_calculate_final_amount($booking) {
        if (class_exists('Shaped_Pricing')) {
            return Shaped_Pricing::calculate_final_amount($booking);
        }
        return $booking ? (float) $booking->getTotalPrice() : 0.0;
    }
}

if (!function_exists('shaped_get_room_discount')) {
    function shaped_get_room_discount(string $room_slug, ?string $check_in_date = null): float {
        if (class_exists('Shaped_Pricing')) {
            return (float) Shaped_Pricing::get_room_discount($room_slug, $check_in_date);
        }
        return 0.0;
    }
}

if (!function_exists('shaped_get_payment_mode')) {
    function shaped_get_payment_mode(): string {
        if (class_exists('Shaped_Pricing')) {
            return Shaped_Pricing::get_payment_mode();
        }
        return 'scheduled';
    }
}

if (!function_exists('shaped_is_deposit_mode')) {
    function shaped_is_deposit_mode(): bool {
        if (class_exists('Shaped_Pricing')) {
            return Shaped_Pricing::is_deposit_mode();
        }
        return false;
    }
}

if (!function_exists('shaped_get_deposit_percent')) {
    function shaped_get_deposit_percent(): int {
        if (class_exists('Shaped_Pricing')) {
            return Shaped_Pricing::get_deposit_percent();
        }
        return 30;
    }
}

if (!function_exists('shaped_calculate_deposit')) {
    function shaped_calculate_deposit(float $total): array {
        if (class_exists('Shaped_Pricing')) {
            return Shaped_Pricing::calculate_deposit($total);
        }
        
        $percent = 30;
        $deposit = round($total * ($percent / 100), 2);
        $balance = round($total - $deposit, 2);
        
        return [
            'deposit' => $deposit,
            'balance' => $balance,
            'percent' => $percent,
            'total'   => $total,
        ];
    }
}

/* =========================================================================
 * Booking Manager Functions
 * ========================================================================= */

if (!function_exists('shaped_mark_booking_abandoned')) {
    function shaped_mark_booking_abandoned($booking_id) {
        if (class_exists('Shaped_Booking_Manager')) {
            Shaped_Booking_Manager::mark_booking_abandoned((int) $booking_id);
        }
    }
}

/* =========================================================================
 * Deposit-Specific Helpers
 * ========================================================================= */

if (!function_exists('shaped_get_booking_deposit_info')) {
    function shaped_get_booking_deposit_info(int $booking_id): ?array {
        $payment_type = get_post_meta($booking_id, '_shaped_payment_type', true);
        
        if ($payment_type !== 'deposit') {
            return null;
        }
        
        return [
            'payment_type'    => 'deposit',
            'deposit_percent' => (int) get_post_meta($booking_id, '_shaped_deposit_percent', true),
            'deposit_amount'  => (float) get_post_meta($booking_id, '_shaped_deposit_amount', true),
            'balance_due'     => (float) get_post_meta($booking_id, '_shaped_balance_due', true),
            'total_amount'    => (float) get_post_meta($booking_id, '_shaped_payment_amount', true),
            'paid_amount'     => (float) get_post_meta($booking_id, '_mphb_paid_amount', true),
            'status'          => get_post_meta($booking_id, '_shaped_payment_status', true),
        ];
    }
}

if (!function_exists('shaped_is_deposit_booking')) {
    function shaped_is_deposit_booking(int $booking_id): bool {
        return get_post_meta($booking_id, '_shaped_payment_type', true) === 'deposit';
    }
}

if (!function_exists('shaped_get_balance_due')) {
    function shaped_get_balance_due(int $booking_id): float {
        $payment_type = get_post_meta($booking_id, '_shaped_payment_type', true);
        
        if ($payment_type !== 'deposit') {
            return 0.0;
        }
        
        return (float) get_post_meta($booking_id, '_shaped_balance_due', true);
    }
}

if (!function_exists('shaped_format_deposit_summary')) {
    function shaped_format_deposit_summary(int $booking_id, string $currency = 'EUR'): string {
        $info = shaped_get_booking_deposit_info($booking_id);
        
        if (!$info) {
            return '';
        }
        
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
        ];
        $symbol = $symbols[$currency] ?? $currency . ' ';
        
        return sprintf(
            '%s%.2f deposit paid (%d%%) – %s%.2f due on arrival',
            $symbol,
            $info['deposit_amount'],
            $info['deposit_percent'],
            $symbol,
            $info['balance_due']
        );
    }
}
