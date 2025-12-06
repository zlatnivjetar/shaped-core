<?php
/**
 * Manage Booking Template
 * 
 * This template is used when the manage_booking entry route
 * includes a custom template file.
 * 
 * Available variables (set by the route handler):
 *   $booking_id - The booking ID
 *   $token - The access token
 * 
 * This file is optional - the shortcode [shaped_manage_booking]
 * handles everything if this template is not present.
 * 
 * @package ShapedCore
 */

if (!defined('ABSPATH')) {
    exit;
}

// Validate inputs
if (empty($booking_id) || empty($token)) {
    wp_die('Invalid booking reference');
}

// Verify booking exists
$booking = MPHB()->getBookingRepository()->findById($booking_id);
if (!$booking) {
    wp_die('Booking not found');
}

// Verify token
$expected_token = md5($booking_id . $booking->getCustomer()->getEmail());
if ($token !== $expected_token) {
    wp_die('Invalid access token');
}

// Get payment context
$context = class_exists('Shaped_Payment_Processor')
    ? Shaped_Payment_Processor::get_payment_context($booking)
    : null;

// Get header (optional - for standalone page)
get_header();
?>

<div class="shaped-manage-booking-page">
    <?php echo do_shortcode('[shaped_manage_booking]'); ?>
</div>

<?php
get_footer();
