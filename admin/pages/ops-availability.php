<?php
/**
 * Shaped Ops Availability Calendar
 * Shows RoomCloud room availability by date in a visual grid
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if RoomCloud is active
$roomcloud_active = defined('SHAPED_ENABLE_ROOMCLOUD') && SHAPED_ENABLE_ROOMCLOUD;

// Get number of days to show (default 30, max 90)
$days = isset($_GET['days']) ? min(90, max(7, intval($_GET['days']))) : 30;

// Get inventory summary
$summary = [];
$room_names = [];

if ($roomcloud_active && class_exists('Shaped_RC_Availability_Manager')) {
    $summary = Shaped_RC_Availability_Manager::get_inventory_summary($days);

    // Get friendly room names from room type posts
    foreach ($summary as $slug => $data) {
        $room_post = get_page_by_path($slug, OBJECT, 'mphb_room_type');
        $room_names[$slug] = $room_post ? $room_post->post_title : ucwords(str_replace('-', ' ', $slug));
    }
}

// Generate date headers
$dates = [];
$today = new DateTime();
for ($i = 0; $i < $days; $i++) {
    $date = clone $today;
    $date->modify("+{$i} days");
    $dates[] = $date;
}
$date_strings = array_map(static fn(DateTime $date): string => $date->format('Y-m-d'), $dates);
$coverage = [];

if ($roomcloud_active && class_exists('Shaped_RC_Availability_Manager')) {
    $coverage = Shaped_RC_Availability_Manager::get_inventory_coverage($date_strings);
}
?>

<div class="wrap shaped-ops-availability">
    <h1>Room Availability Calendar</h1>
    <p class="description">RoomCloud inventory status for the next <?php echo esc_html($days); ?> days.</p>

    <?php if (!$roomcloud_active): ?>
        <div class="notice notice-warning">
            <p><strong>RoomCloud is not enabled.</strong> Enable RoomCloud in the system settings to view availability data.</p>
        </div>
    <?php elseif (empty($summary)): ?>
        <div class="notice notice-info">
            <p><strong>No inventory data available.</strong> RoomCloud needs to sync availability data first.</p>
        </div>
    <?php else: ?>

        <!-- Controls -->
        <div class="shaped-availability-controls">
            <form method="get" action="">
                <input type="hidden" name="page" value="shaped-ops-availability">
                <label for="days">Show:</label>
                <select name="days" id="days" onchange="this.form.submit()">
                    <option value="7" <?php selected($days, 7); ?>>7 days</option>
                    <option value="14" <?php selected($days, 14); ?>>14 days</option>
                    <option value="30" <?php selected($days, 30); ?>>30 days</option>
                    <option value="60" <?php selected($days, 60); ?>>60 days</option>
                    <option value="90" <?php selected($days, 90); ?>>90 days</option>
                </select>
            </form>

            <div class="shaped-availability-legend">
                <span class="legend-item"><span class="legend-color available"></span> Available (2+)</span>
                <span class="legend-item"><span class="legend-color low"></span> Low (1)</span>
                <span class="legend-item"><span class="legend-color full"></span> Full (0)</span>
                <span class="legend-item"><span class="legend-color no-data"></span> No data</span>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="shaped-availability-wrapper">
            <table class="shaped-availability-table">
                <thead>
                    <tr>
                        <th class="room-header">Room Type</th>
                        <?php foreach ($dates as $date):
                            $is_weekend = in_array($date->format('N'), ['6', '7']);
                            $is_today = $date->format('Y-m-d') === $today->format('Y-m-d');
                        ?>
                            <th class="date-header <?php echo $is_weekend ? 'weekend' : ''; ?> <?php echo $is_today ? 'today' : ''; ?>">
                                <span class="date-day"><?php echo esc_html($date->format('D')); ?></span>
                                <span class="date-num"><?php echo esc_html($date->format('j')); ?></span>
                                <span class="date-month"><?php echo esc_html($date->format('M')); ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $slug => $room_data): ?>
                        <tr>
                            <td class="room-name">
                                <?php
                                $room_post = get_page_by_path($slug, OBJECT, 'mphb_room_type');
                                if ($room_post):
                                ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($room_post->ID)); ?>">
                                        <?php echo esc_html($room_names[$slug]); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo esc_html($room_names[$slug]); ?>
                                <?php endif; ?>
                                <?php if (isset($coverage[$slug])): ?>
                                    <?php $coverage_row = $coverage[$slug]; ?>
                                    <div style="margin-top: 6px; font-size: 12px; line-height: 1.4; color: #646970;">
                                        <?php echo esc_html(sprintf(
                                            'Stored %d dates. First %s. Last %s. Visible gaps %s.',
                                            (int) $coverage_row['stored_dates_count'],
                                            $coverage_row['first_stored_date'] ?: 'n/a',
                                            $coverage_row['last_stored_date'] ?: 'n/a',
                                            !empty($coverage_row['window_has_gaps']) ? 'yes' : 'no'
                                        )); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($dates as $date):
                                $date_str = $date->format('Y-m-d');
                                $quantity = isset($room_data['dates'][$date_str]) ? $room_data['dates'][$date_str] : null;
                                $is_weekend = in_array($date->format('N'), ['6', '7']);
                                $is_today = $date->format('Y-m-d') === $today->format('Y-m-d');

                                // Determine cell class
                                if ($quantity === null) {
                                    $cell_class = 'no-data';
                                } elseif ($quantity === 0) {
                                    $cell_class = 'full';
                                } elseif ($quantity === 1) {
                                    $cell_class = 'low';
                                } else {
                                    $cell_class = 'available';
                                }
                            ?>
                                <td class="availability-cell <?php echo esc_attr($cell_class); ?> <?php echo $is_weekend ? 'weekend' : ''; ?> <?php echo $is_today ? 'today' : ''; ?>"
                                    title="<?php echo esc_attr($room_names[$slug] . ' - ' . $date->format('D, M j') . ': ' . ($quantity === null ? 'No data' : $quantity . ' available')); ?>">
                                    <?php echo $quantity !== null ? esc_html($quantity) : '—'; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary Stats -->
        <div class="shaped-availability-stats">
            <h2>Quick Stats</h2>
            <div class="shaped-stats-grid">
                <?php
                // Calculate stats for each room
                foreach ($summary as $slug => $room_data):
                    $total_days = 0;
                    $available_days = 0;
                    $full_days = 0;
                    $low_days = 0;
                    $total_units = 0;

                    foreach ($room_data['dates'] as $date_str => $qty) {
                        if ($qty !== null) {
                            $total_days++;
                            $total_units += $qty;
                            if ($qty === 0) {
                                $full_days++;
                            } elseif ($qty === 1) {
                                $low_days++;
                            } else {
                                $available_days++;
                            }
                        }
                    }

                    $occupancy_rate = $total_days > 0 ? round((($total_days - $available_days - $low_days) / $total_days) * 100) : 0;
                ?>
                    <div class="shaped-stat-card">
                        <div class="stat-content">
                            <span class="stat-label"><?php echo esc_html($room_names[$slug]); ?></span>
                            <span class="stat-detail">
                                <span class="stat-available"><?php echo esc_html($available_days); ?> open</span>
                                <span class="stat-low"><?php echo esc_html($low_days); ?> low</span>
                                <span class="stat-full"><?php echo esc_html($full_days); ?> full</span>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
.shaped-ops-availability {
    max-width: 100%;
}

.shaped-availability-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin: 20px 0;
    padding: 15px 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.shaped-availability-controls form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.shaped-availability-controls select {
    padding: 5px 10px;
}

.shaped-availability-legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #50575e;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.1);
}

.legend-color.available { background: #d4edda; }
.legend-color.low { background: #fff3cd; }
.legend-color.full { background: #f8d7da; }
.legend-color.no-data { background: #e9ecef; }

.shaped-availability-wrapper {
    overflow-x: auto;
    margin: 20px 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.shaped-availability-table {
    border-collapse: collapse;
    width: 100%;
    min-width: max-content;
}

.shaped-availability-table th,
.shaped-availability-table td {
    border: 1px solid #e5e5e5;
    text-align: center;
    vertical-align: middle;
}

.shaped-availability-table th {
    background: #f6f7f7;
    font-weight: 600;
    padding: 8px 4px;
}

.room-header {
    position: sticky;
    left: 0;
    z-index: 2;
    background: #f0f0f1 !important;
    min-width: 180px;
    text-align: left !important;
    padding-left: 15px !important;
}

.room-name {
    position: sticky;
    left: 0;
    z-index: 1;
    background: #fff;
    min-width: 180px;
    text-align: left;
    padding: 10px 15px;
    font-weight: 500;
    border-right: 2px solid #ddd !important;
}

.room-name a {
    text-decoration: none;
    color: #1d2327;
}

.room-name a:hover {
    color: #2271b1;
}

.date-header {
    min-width: 50px;
    padding: 6px 2px !important;
}

.date-header .date-day {
    display: block;
    font-size: 10px;
    color: #646970;
    font-weight: 400;
    text-transform: uppercase;
}

.date-header .date-num {
    display: block;
    font-size: 14px;
    font-weight: 600;
}

.date-header .date-month {
    display: block;
    font-size: 10px;
    color: #646970;
    font-weight: 400;
}

.date-header.weekend {
    background: #f0f0f1 !important;
}

.date-header.today {
    background: #2271b1 !important;
    color: #fff;
}

.date-header.today .date-day,
.date-header.today .date-month {
    color: rgba(255,255,255,0.8);
}

.availability-cell {
    padding: 12px 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: default;
    transition: filter 0.1s ease;
}

.availability-cell:hover {
    filter: brightness(0.92);
}

.availability-cell.available {
    background: #d4edda;
    color: #155724;
}

.availability-cell.low {
    background: #fff3cd;
    color: #856404;
}

.availability-cell.full {
    background: #f8d7da;
    color: #721c24;
}

.availability-cell.no-data {
    background: #e9ecef;
    color: #6c757d;
}

.availability-cell.weekend {
    opacity: 0.9;
}

.availability-cell.today {
    border: 2px solid #2271b1 !important;
}

/* Stats section */
.shaped-availability-stats {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px 25px;
    margin: 25px 0;
}

.shaped-availability-stats h2 {
    margin: 0 0 20px;
    padding: 0;
    font-size: 16px;
    font-weight: 600;
}

.shaped-availability-stats .shaped-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
}

.shaped-availability-stats .shaped-stat-card {
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    padding: 15px;
}

.shaped-availability-stats .stat-label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #1d2327;
}

.shaped-availability-stats .stat-detail {
    display: flex;
    gap: 12px;
    font-size: 13px;
}

.stat-available { color: #155724; }
.stat-low { color: #856404; }
.stat-full { color: #721c24; }

/* Responsive */
@media screen and (max-width: 782px) {
    .shaped-availability-controls {
        flex-direction: column;
        align-items: flex-start;
    }

    .shaped-availability-legend {
        flex-direction: column;
        gap: 8px;
    }
}
</style>
