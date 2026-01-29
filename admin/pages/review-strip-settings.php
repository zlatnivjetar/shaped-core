<?php
/**
 * Review Strip Settings Page Template
 * Configure ratings for each provider displayed in review strips
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$providers = Shaped_Review_Strip_Settings::get_provider_configs();
$ratings = Shaped_Review_Strip_Settings::get_ratings();
?>

<div class="wrap shaped-review-strip-settings">
    <h1>Review Strip Settings</h1>
    <p class="description">Configure the ratings displayed in review strip shortcodes. These ratings appear in the header and footer review strips.</p>

    <form method="post" action="options.php">
        <?php settings_fields('shaped_review_strip_settings'); ?>

        <div class="shaped-settings-grid">
            <?php foreach ($providers as $key => $config) :
                $rating = $ratings[$key]['rating'] ?? 0;
                $enabled = $ratings[$key]['enabled'] ?? false;
                $scale = $config['scale'];
                $step = $scale === 10 ? '0.1' : '0.1';
            ?>
            <div class="shaped-provider-card <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>" data-provider="<?php echo esc_attr($key); ?>">
                <div class="provider-header">
                    <span class="provider-badge" style="background-color: <?php echo esc_attr($config['bg']); ?>; color: <?php echo esc_attr($config['text']); ?>;">
                        <?php echo esc_html($config['name']); ?>
                    </span>
                    <label class="provider-toggle">
                        <input type="checkbox"
                               name="<?php echo esc_attr(Shaped_Review_Strip_Settings::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>][enabled]"
                               value="1"
                               <?php checked($enabled); ?>>
                        <span class="toggle-label">Enabled</span>
                    </label>
                </div>

                <div class="provider-rating-input">
                    <label for="rating-<?php echo esc_attr($key); ?>">
                        Rating <span class="scale-indicator">(0-<?php echo esc_html($scale); ?>)</span>
                    </label>
                    <input type="number"
                           id="rating-<?php echo esc_attr($key); ?>"
                           name="<?php echo esc_attr(Shaped_Review_Strip_Settings::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>][rating]"
                           value="<?php echo esc_attr($rating); ?>"
                           min="0"
                           max="<?php echo esc_attr($scale); ?>"
                           step="<?php echo esc_attr($step); ?>"
                           class="rating-input">
                </div>

                <div class="provider-preview">
                    <span class="preview-label">Preview:</span>
                    <span class="preview-rating">
                        <?php
                        // Calculate star rating display
                        if ($scale === 10) {
                            $star_rating = $rating / 2;
                            $display_rating = number_format($rating, 1) . '/10';
                        } else {
                            $star_rating = $rating;
                            $display_rating = number_format($rating, 1) . '/5';
                        }
                        $full_stars = floor($star_rating);
                        $partial = $star_rating - $full_stars;
                        ?>
                        <span class="preview-stars">
                            <?php for ($i = 0; $i < 5; $i++) : ?>
                                <?php if ($i < $full_stars) : ?>
                                    <span class="star full">&#9733;</span>
                                <?php elseif ($i === $full_stars && $partial >= 0.5) : ?>
                                    <span class="star half">&#9733;</span>
                                <?php else : ?>
                                    <span class="star empty">&#9733;</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </span>
                        <span class="preview-numeric"><?php echo esc_html($display_rating); ?></span>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="shaped-settings-actions">
            <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
        </div>
    </form>

    <div class="shaped-shortcode-reference">
        <h2>Shortcode Reference</h2>

        <div class="shortcode-card">
            <h3>Single Review Strip</h3>
            <code>[shaped_review_strip provider="booking"]</code>
            <p>Displays a single provider's review strip with badge, stars, and rating.</p>
            <table class="shortcode-attributes">
                <tr>
                    <th>Attribute</th>
                    <th>Default</th>
                    <th>Description</th>
                </tr>
                <tr>
                    <td><code>provider</code></td>
                    <td>booking</td>
                    <td>Provider key: booking, expedia, google, tripadvisor, airbnb, direct</td>
                </tr>
                <tr>
                    <td><code>show_stars</code></td>
                    <td>true</td>
                    <td>Show star icons (true/false)</td>
                </tr>
            </table>
        </div>

        <div class="shortcode-card">
            <h3>Review Strips Grid</h3>
            <code>[shaped_review_strips providers="booking,expedia,google"]</code>
            <p>Displays multiple review strips in a responsive grid layout.</p>
            <table class="shortcode-attributes">
                <tr>
                    <th>Attribute</th>
                    <th>Default</th>
                    <th>Description</th>
                </tr>
                <tr>
                    <td><code>providers</code></td>
                    <td>booking,expedia,google</td>
                    <td>Comma-separated list of provider keys</td>
                </tr>
            </table>
            <p class="shortcode-note">The grid automatically adjusts to 2 or 3 columns based on the number of providers.</p>
        </div>
    </div>
</div>

<style>
.shaped-review-strip-settings {
    max-width: 1200px;
}

.shaped-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin: 24px 0;
}

.shaped-provider-card {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    padding: 20px;
    transition: opacity 0.2s, border-color 0.2s;
}

.shaped-provider-card.is-disabled {
    opacity: 0.6;
    border-color: #dcdcde;
}

.shaped-provider-card.is-enabled {
    border-color: #2271b1;
}

.provider-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.provider-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.provider-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.provider-toggle input {
    margin: 0;
}

.toggle-label {
    font-size: 13px;
    color: #50575e;
}

.provider-rating-input {
    margin-bottom: 16px;
}

.provider-rating-input label {
    display: block;
    font-weight: 500;
    margin-bottom: 6px;
    color: #1d2327;
}

.scale-indicator {
    font-weight: 400;
    color: #646970;
    font-size: 12px;
}

.rating-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
}

.provider-preview {
    background: #f6f7f7;
    border-radius: 4px;
    padding: 12px;
}

.preview-label {
    display: block;
    font-size: 11px;
    color: #646970;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.preview-rating {
    display: flex;
    align-items: center;
    gap: 8px;
}

.preview-stars {
    display: flex;
    gap: 2px;
}

.preview-stars .star {
    font-size: 16px;
    line-height: 1;
}

.preview-stars .star.full {
    color: #d4af37;
}

.preview-stars .star.half {
    color: #d4af37;
    position: relative;
}

.preview-stars .star.empty {
    color: #dcdcde;
}

.preview-numeric {
    font-weight: 600;
    font-size: 14px;
    color: #1d2327;
}

.shaped-settings-actions {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #dcdcde;
}

.shaped-shortcode-reference {
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid #dcdcde;
}

.shaped-shortcode-reference h2 {
    margin-bottom: 20px;
}

.shortcode-card {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.shortcode-card h3 {
    margin: 0 0 12px;
    font-size: 15px;
}

.shortcode-card > code {
    display: block;
    background: #f0f0f1;
    padding: 12px 16px;
    border-radius: 4px;
    font-size: 13px;
    margin-bottom: 12px;
}

.shortcode-card p {
    color: #50575e;
    margin: 0 0 16px;
}

.shortcode-attributes {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.shortcode-attributes th,
.shortcode-attributes td {
    text-align: left;
    padding: 8px 12px;
    border-bottom: 1px solid #dcdcde;
}

.shortcode-attributes th {
    background: #f6f7f7;
    font-weight: 500;
}

.shortcode-attributes code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.shortcode-note {
    font-style: italic;
    font-size: 12px;
    color: #646970;
    margin-top: 12px !important;
    margin-bottom: 0 !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle card enabled/disabled state
    $('.provider-toggle input').on('change', function() {
        var card = $(this).closest('.shaped-provider-card');
        if ($(this).is(':checked')) {
            card.removeClass('is-disabled').addClass('is-enabled');
        } else {
            card.removeClass('is-enabled').addClass('is-disabled');
        }
    });

    // Update preview on rating change
    $('.rating-input').on('input', function() {
        var card = $(this).closest('.shaped-provider-card');
        var provider = card.data('provider');
        var rating = parseFloat($(this).val()) || 0;
        var max = parseFloat($(this).attr('max'));
        var scale = max;

        // Clamp rating
        rating = Math.max(0, Math.min(rating, max));

        // Calculate star rating
        var starRating = (scale === 10) ? rating / 2 : rating;
        var fullStars = Math.floor(starRating);
        var partial = starRating - fullStars;

        // Update stars
        var starsHtml = '';
        for (var i = 0; i < 5; i++) {
            if (i < fullStars) {
                starsHtml += '<span class="star full">&#9733;</span>';
            } else if (i === fullStars && partial >= 0.5) {
                starsHtml += '<span class="star half">&#9733;</span>';
            } else {
                starsHtml += '<span class="star empty">&#9733;</span>';
            }
        }

        card.find('.preview-stars').html(starsHtml);

        // Update numeric display
        var displayRating = rating.toFixed(1) + '/' + scale;
        card.find('.preview-numeric').text(displayRating);
    });
});
</script>
