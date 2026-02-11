<?php
/**
 * Shaped Ops Dashboard
 * Overview page for hotel operations
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get quick stats
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

// Today's check-ins
$checkins_today = new WP_Query([
    'post_type'      => 'mphb_booking',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'     => 'mphb_check_in_date',
            'value'   => $today,
            'compare' => '=',
            'type'    => 'DATE',
        ],
    ],
    'post_status'    => ['confirmed', 'publish'],
]);
$checkins_count = $checkins_today->found_posts;

// Today's check-outs
$checkouts_today = new WP_Query([
    'post_type'      => 'mphb_booking',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'     => 'mphb_check_out_date',
            'value'   => $today,
            'compare' => '=',
            'type'    => 'DATE',
        ],
    ],
    'post_status'    => ['confirmed', 'publish'],
]);
$checkouts_count = $checkouts_today->found_posts;

// Pending bookings (need attention)
$pending_bookings = new WP_Query([
    'post_type'      => 'mphb_booking',
    'posts_per_page' => -1,
    'post_status'    => 'pending',
]);
$pending_count = $pending_bookings->found_posts;

// This week's bookings
$week_bookings = new WP_Query([
    'post_type'      => 'mphb_booking',
    'posts_per_page' => -1,
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'     => 'mphb_check_in_date',
            'value'   => $week_end,
            'compare' => '<=',
            'type'    => 'DATE',
        ],
        [
            'key'     => 'mphb_check_out_date',
            'value'   => $week_start,
            'compare' => '>=',
            'type'    => 'DATE',
        ],
    ],
    'post_status'    => ['confirmed', 'publish'],
]);
$week_count = $week_bookings->found_posts;

// Recent bookings (last 5)
$recent_bookings = new WP_Query([
    'post_type'      => 'mphb_booking',
    'posts_per_page' => 5,
    'post_status'    => ['confirmed', 'pending', 'publish'],
    'orderby'        => 'date',
    'order'          => 'DESC',
]);

// Reviews stats
$reviews_count = wp_count_posts('shaped_review');
$published_reviews = $reviews_count->publish ?? 0;
?>

<div class="wrap shaped-ops-dashboard">
    <h1>Hotel Operations</h1>
    <p class="description">Welcome back! Here's your daily overview.</p>

    <!-- Quick Stats Cards -->
    <div class="shaped-stats-grid">
        <div class="shaped-stat-card shaped-stat-arrivals">
            <div class="stat-icon dashicons dashicons-calendar-alt"></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html($checkins_count); ?></span>
                <span class="stat-label">Arrivals Today</span>
            </div>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=mphb_booking')); ?>" class="stat-link">View All</a>
        </div>

        <div class="shaped-stat-card shaped-stat-departures">
            <div class="stat-icon dashicons dashicons-migrate"></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html($checkouts_count); ?></span>
                <span class="stat-label">Departures Today</span>
            </div>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=mphb_booking')); ?>" class="stat-link">View All</a>
        </div>

        <div class="shaped-stat-card shaped-stat-pending <?php echo $pending_count > 0 ? 'has-pending' : ''; ?>">
            <div class="stat-icon dashicons dashicons-warning"></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html($pending_count); ?></span>
                <span class="stat-label">Pending Bookings</span>
            </div>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=mphb_booking&post_status=pending')); ?>" class="stat-link">Review</a>
        </div>

        <div class="shaped-stat-card shaped-stat-week">
            <div class="stat-icon dashicons dashicons-groups"></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html($week_count); ?></span>
                <span class="stat-label">Guests This Week</span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="shaped-section">
        <h2>Quick Actions</h2>
        <div class="shaped-quick-actions">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=mphb_booking')); ?>" class="button button-primary button-hero">
                <span class="dashicons dashicons-calendar-alt"></span>
                View Reservations
            </a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=mphb_room_type')); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-building"></span>
                Manage Inventory
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-pricing')); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-tag"></span>
                Update Pricing
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-reviews-dashboard')); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-star-filled"></span>
                Reviews (<?php echo esc_html($published_reviews); ?>)
            </a>
        </div>
    </div>

    <!-- Recent Bookings -->
    <?php if ($recent_bookings->have_posts()): ?>
    <div class="shaped-section">
        <h2>Recent Bookings</h2>
        <?php $payment_mode = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_payment_mode() : 'scheduled'; ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Guest</th>
                    <th>Dates</th>
                    <th>Accommodation / Rate</th>
                    <th>Status</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($recent_bookings->have_posts()): $recent_bookings->the_post();
                    $booking_id = get_the_ID();
                    $guest_name = get_post_meta($booking_id, 'mphb_first_name', true) . ' ' . get_post_meta($booking_id, 'mphb_last_name', true);
                    $check_in = get_post_meta($booking_id, 'mphb_check_in_date', true);
                    $check_out = get_post_meta($booking_id, 'mphb_check_out_date', true);
                    $status = get_post_status();

                    // Load MPHB booking object for accommodation, rate, and payment data
                    $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
                    $accommodation_name = '';
                    $rate_name = '';
                    $total_price = 0;
                    $payment_context = null;

                    if ($booking) {
                        $total_price = $booking->getTotalPrice();

                        // Accommodation name
                        $reserved_rooms = $booking->getReservedRooms();
                        if (!empty($reserved_rooms)) {
                            $room = reset($reserved_rooms);
                            $room_type = MPHB()->getRoomTypeRepository()->findById($room->getRoomTypeId());
                            if ($room_type) {
                                $accommodation_name = $room_type->getTitle();
                            }
                        }

                        // Rate name
                        if (function_exists('shaped_get_booking_rate_name')) {
                            $rate_name = shaped_get_booking_rate_name($booking_id);
                        }

                        // Payment context
                        if (class_exists('Shaped_Payment_Processor')) {
                            $payment_context = Shaped_Payment_Processor::get_payment_context($booking);
                        }
                    }
                ?>
                <tr>
                    <td><strong>#<?php echo esc_html($booking_id); ?></strong></td>
                    <td><?php echo esc_html($guest_name); ?></td>
                    <td>
                        <?php if ($check_in && $check_out): ?>
                            <?php echo esc_html(date_i18n('M j', strtotime($check_in))); ?> – <?php echo esc_html(date_i18n('M j', strtotime($check_out))); ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                            $accom_display = $accommodation_name ?: '—';
                            if ($rate_name) {
                                $accom_display .= ' · ' . $rate_name;
                            }
                            echo esc_html($accom_display);
                        ?>
                    </td>
                    <td>
                        <span class="booking-status status-<?php echo esc_attr($status); ?>">
                            <?php echo esc_html(ucfirst($status)); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($payment_mode === 'deposit' && $payment_context): ?>
                            <?php
                                $deposit = $payment_context['deposit_amount'];
                                $balance = $payment_context['balance_due'];
                                if (function_exists('shaped_format_room_price')) {
                                    echo esc_html(shaped_format_room_price($deposit) . ' paid / ' . shaped_format_room_price($balance) . ' due');
                                } else {
                                    echo esc_html('€' . number_format($deposit, 2) . ' paid / €' . number_format($balance, 2) . ' due');
                                }
                            ?>
                        <?php elseif ($payment_context): ?>
                            <?php
                                $total_display = function_exists('shaped_format_room_price') ? shaped_format_room_price($total_price) : '€' . number_format($total_price, 2);
                                $charged_display = $payment_context['is_charged'] ? 'Yes' : 'No';
                                echo esc_html($total_display . ' · ' . $charged_display);
                            ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; wp_reset_postdata(); ?>
            </tbody>
        </table>
        <p style="margin-top: 15px;">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=mphb_booking')); ?>">View all reservations &rarr;</a>
        </p>
    </div>
    <?php endif; ?>
</div>

<style>
.shaped-ops-dashboard {
    max-width: 1200px;
}

.shaped-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.shaped-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 15px;
    position: relative;
    transition: box-shadow 0.2s ease;
}

.shaped-stat-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.shaped-stat-card .stat-icon {
    font-size: 32px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f0f0f1;
    border-radius: 50%;
    color: #50575e;
}

.shaped-stat-arrivals .stat-icon { background: #e7f5ea; color: #00a32a; }
.shaped-stat-departures .stat-icon { background: #fef3e7; color: #dba617; }
.shaped-stat-pending .stat-icon { background: #f0f0f1; color: #50575e; }
.shaped-stat-pending.has-pending .stat-icon { background: #fcf0f1; color: #d63638; }
.shaped-stat-week .stat-icon { background: #e7f2fa; color: #0073aa; }

.shaped-stat-card .stat-content {
    flex: 1;
}

.shaped-stat-card .stat-number {
    display: block;
    font-size: 28px;
    font-weight: 600;
    line-height: 1.2;
    color: #1d2327;
}

.shaped-stat-card .stat-label {
    display: block;
    font-size: 13px;
    color: #50575e;
    margin-top: 2px;
}

.shaped-stat-card .stat-link {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 12px;
    text-decoration: none;
}

.shaped-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px 25px;
    margin: 25px 0;
}

.shaped-section h2 {
    margin: 0 0 20px;
    padding: 0;
    font-size: 16px;
    font-weight: 600;
}

.shaped-quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.shaped-quick-actions .button-hero {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    height: auto;
    font-size: 14px;
}

.shaped-quick-actions .button-hero .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.booking-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.booking-status.status-confirmed,
.booking-status.status-publish {
    background: #e7f5ea;
    color: #00a32a;
}

.booking-status.status-pending {
    background: #fef3e7;
    color: #996800;
}

.booking-status.status-cancelled {
    background: #fcf0f1;
    color: #d63638;
}
</style>
