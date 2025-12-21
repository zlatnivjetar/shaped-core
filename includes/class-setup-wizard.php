<?php
/**
 * Setup Wizard
 *
 * Multi-step setup wizard for quick client configuration.
 * Handles Stripe credentials, payment mode, discounts, and modal pages.
 *
 * Security: Stripe keys stored in database are encrypted with wp_salt().
 * Priority: Constants in wp-config.php always take priority over database values.
 *
 * @package Shaped_Core
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Setup_Wizard {

    /**
     * Option keys for database-stored credentials
     */
    const OPT_STRIPE_SECRET   = 'shaped_stripe_secret_key';
    const OPT_STRIPE_WEBHOOK  = 'shaped_stripe_webhook_secret';
    const OPT_SETUP_COMPLETE  = 'shaped_setup_complete';
    const OPT_SETUP_DISMISSED = 'shaped_setup_dismissed';

    /**
     * Wizard steps
     */
    private static array $steps = [
        'stripe'       => 'Stripe Credentials',
        'payment_mode' => 'Payment Mode',
        'discounts'    => 'Room Discounts',
        'modals'       => 'Modal Pages',
        'complete'     => 'Complete',
    ];

    /**
     * Initialize the setup wizard
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'maybe_redirect_to_wizard']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_notices', [__CLASS__, 'show_setup_notice']);

        // AJAX handlers
        add_action('wp_ajax_shaped_wizard_save_step', [__CLASS__, 'ajax_save_step']);
        add_action('wp_ajax_shaped_wizard_validate_stripe', [__CLASS__, 'ajax_validate_stripe']);
        add_action('wp_ajax_shaped_wizard_dismiss', [__CLASS__, 'ajax_dismiss_wizard']);
        add_action('wp_ajax_shaped_wizard_skip', [__CLASS__, 'ajax_skip_step']);
    }

    /**
     * Add wizard page to admin menu (hidden from menu)
     */
    public static function add_admin_menu(): void {
        add_submenu_page(
            null, // Hidden from menu
            'Shaped Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'shaped-setup-wizard',
            [__CLASS__, 'render_wizard_page']
        );

        // Add Config Health as submenu under Shaped Core
        add_submenu_page(
            'shaped-settings',
            'Configuration Health',
            'Config Health',
            'manage_options',
            'shaped-config-health',
            [__CLASS__, 'render_health_page']
        );
    }

    /**
     * Maybe redirect to wizard after plugin activation
     */
    public static function maybe_redirect_to_wizard(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check for activation redirect flag
        if (get_transient('shaped_activation_redirect')) {
            delete_transient('shaped_activation_redirect');

            // Don't redirect if setup is complete or dismissed
            if (self::is_setup_complete() || get_option(self::OPT_SETUP_DISMISSED)) {
                return;
            }

            // Don't redirect on bulk activations
            if (isset($_GET['activate-multi'])) {
                return;
            }

            wp_safe_redirect(admin_url('admin.php?page=shaped-setup-wizard'));
            exit;
        }
    }

    /**
     * Show setup notice if not complete
     */
    public static function show_setup_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Don't show on wizard page
        if (isset($_GET['page']) && $_GET['page'] === 'shaped-setup-wizard') {
            return;
        }

        // Don't show if setup complete or dismissed
        if (self::is_setup_complete() || get_option(self::OPT_SETUP_DISMISSED)) {
            return;
        }

        $wizard_url = admin_url('admin.php?page=shaped-setup-wizard');
        $dismiss_url = wp_nonce_url(
            admin_url('admin-ajax.php?action=shaped_wizard_dismiss'),
            'shaped_wizard_dismiss'
        );
        ?>
        <div class="notice notice-info is-dismissible shaped-setup-notice">
            <p>
                <strong>Shaped Core:</strong>
                Complete the setup wizard to configure your booking system.
                <a href="<?php echo esc_url($wizard_url); ?>" class="button button-primary" style="margin-left: 10px;">
                    Run Setup Wizard
                </a>
                <a href="<?php echo esc_url($dismiss_url); ?>" class="button button-secondary" style="margin-left: 5px;">
                    Dismiss
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Enqueue wizard assets
     */
    public static function enqueue_assets(string $hook): void {
        if (!in_array($hook, ['admin_page_shaped-setup-wizard', 'shaped-core_page_shaped-config-health'])) {
            return;
        }

        wp_enqueue_style(
            'shaped-setup-wizard',
            SHAPED_URL . 'assets/css/admin-setup-wizard.css',
            [],
            SHAPED_VERSION
        );

        wp_enqueue_script(
            'shaped-setup-wizard',
            SHAPED_URL . 'assets/js/admin-setup-wizard.js',
            ['jquery'],
            SHAPED_VERSION,
            true
        );

        wp_localize_script('shaped-setup-wizard', 'ShapedWizard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('shaped_wizard'),
            'steps'   => array_keys(self::$steps),
        ]);
    }

    /**
     * Check if setup is complete
     */
    public static function is_setup_complete(): bool {
        // Check if marked complete
        if (get_option(self::OPT_SETUP_COMPLETE)) {
            return true;
        }

        // Or if all critical settings are configured
        $has_stripe_secret = self::get_stripe_secret() !== '';
        $has_stripe_webhook = self::get_stripe_webhook() !== '';
        $has_payment_mode = get_option(Shaped_Pricing::OPT_PAYMENT_MODE) !== false;

        return $has_stripe_secret && $has_stripe_webhook && $has_payment_mode;
    }

    /**
     * Get Stripe secret key (constant takes priority, then database)
     */
    public static function get_stripe_secret(): string {
        // Constant defined in wp-config.php takes priority
        if (defined('SHAPED_STRIPE_SECRET') && SHAPED_STRIPE_SECRET !== '') {
            return SHAPED_STRIPE_SECRET;
        }

        // Fall back to database
        $encrypted = get_option(self::OPT_STRIPE_SECRET, '');
        return $encrypted ? self::decrypt($encrypted) : '';
    }

    /**
     * Get Stripe webhook secret (constant takes priority, then database)
     */
    public static function get_stripe_webhook(): string {
        // Constant defined in wp-config.php takes priority
        if (defined('SHAPED_STRIPE_WEBHOOK') && SHAPED_STRIPE_WEBHOOK !== '') {
            return SHAPED_STRIPE_WEBHOOK;
        }

        // Fall back to database
        $encrypted = get_option(self::OPT_STRIPE_WEBHOOK, '');
        return $encrypted ? self::decrypt($encrypted) : '';
    }

    /**
     * Check if Stripe credentials are from constants
     */
    public static function stripe_uses_constants(): bool {
        $secret_from_const = defined('SHAPED_STRIPE_SECRET') && SHAPED_STRIPE_SECRET !== '';
        $webhook_from_const = defined('SHAPED_STRIPE_WEBHOOK') && SHAPED_STRIPE_WEBHOOK !== '';
        return $secret_from_const || $webhook_from_const;
    }

    /**
     * Simple encryption using wp_salt
     */
    private static function encrypt(string $value): string {
        if (empty($value)) {
            return '';
        }
        $key = wp_salt('auth');
        $iv = substr(md5($key), 0, 16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($encrypted);
    }

    /**
     * Simple decryption using wp_salt
     */
    private static function decrypt(string $value): string {
        if (empty($value)) {
            return '';
        }
        $key = wp_salt('auth');
        $iv = substr(md5($key), 0, 16);
        $decrypted = openssl_decrypt(base64_decode($value), 'AES-256-CBC', $key, 0, $iv);
        return $decrypted ?: '';
    }

    /**
     * Mask a key for display (show last 4 chars)
     */
    public static function mask_key(string $key): string {
        if (strlen($key) < 8) {
            return str_repeat('•', strlen($key));
        }
        return str_repeat('•', strlen($key) - 4) . substr($key, -4);
    }

    /**
     * Render the wizard page
     */
    public static function render_wizard_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'stripe';
        if (!isset(self::$steps[$current_step])) {
            $current_step = 'stripe';
        }

        $step_keys = array_keys(self::$steps);
        $current_index = array_search($current_step, $step_keys);
        $progress = (($current_index + 1) / count(self::$steps)) * 100;

        ?>
        <div class="wrap shaped-wizard-wrap">
            <div class="shaped-wizard-container">
                <!-- Header -->
                <div class="shaped-wizard-header">
                    <h1>Shaped Core Setup</h1>
                    <p class="description">Configure your booking system in a few simple steps.</p>
                </div>

                <!-- Progress Bar -->
                <div class="shaped-wizard-progress">
                    <div class="shaped-wizard-progress-bar" style="width: <?php echo esc_attr($progress); ?>%"></div>
                </div>

                <!-- Step Indicators -->
                <div class="shaped-wizard-steps">
                    <?php foreach (self::$steps as $step_key => $step_label):
                        $step_index = array_search($step_key, $step_keys);
                        $is_active = $step_key === $current_step;
                        $is_complete = $step_index < $current_index;
                        $classes = 'shaped-wizard-step';
                        if ($is_active) $classes .= ' is-active';
                        if ($is_complete) $classes .= ' is-complete';
                    ?>
                    <div class="<?php echo esc_attr($classes); ?>">
                        <span class="step-number"><?php echo esc_html($step_index + 1); ?></span>
                        <span class="step-label"><?php echo esc_html($step_label); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Step Content -->
                <div class="shaped-wizard-content">
                    <?php self::render_step($current_step); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render individual step content
     */
    private static function render_step(string $step): void {
        switch ($step) {
            case 'stripe':
                self::render_step_stripe();
                break;
            case 'payment_mode':
                self::render_step_payment_mode();
                break;
            case 'discounts':
                self::render_step_discounts();
                break;
            case 'modals':
                self::render_step_modals();
                break;
            case 'complete':
                self::render_step_complete();
                break;
        }
    }

    /**
     * Step 1: Stripe Credentials
     */
    private static function render_step_stripe(): void {
        $uses_constants = self::stripe_uses_constants();
        $secret = self::get_stripe_secret();
        $webhook = self::get_stripe_webhook();
        ?>
        <div class="shaped-wizard-step-content" data-step="stripe">
            <h2>Stripe Credentials</h2>

            <?php if ($uses_constants): ?>
            <div class="shaped-wizard-notice shaped-wizard-notice--success">
                <span class="dashicons dashicons-yes-alt"></span>
                <p>Stripe credentials are configured via <code>wp-config.php</code> constants. These take priority over database values.</p>
            </div>
            <?php endif; ?>

            <div class="shaped-wizard-form">
                <div class="shaped-wizard-field">
                    <label for="stripe_secret">Stripe Secret Key</label>
                    <input type="password"
                           id="stripe_secret"
                           name="stripe_secret"
                           value=""
                           placeholder="<?php echo $secret ? esc_attr(self::mask_key($secret)) : 'sk_live_...'; ?>"
                           autocomplete="off"
                           <?php echo $uses_constants && $secret ? 'disabled' : ''; ?>>
                    <p class="description">
                        Your Stripe secret key starting with <code>sk_live_</code> or <code>sk_test_</code>
                    </p>
                </div>

                <div class="shaped-wizard-field">
                    <label for="stripe_webhook">Stripe Webhook Secret</label>
                    <input type="password"
                           id="stripe_webhook"
                           name="stripe_webhook"
                           value=""
                           placeholder="<?php echo $webhook ? esc_attr(self::mask_key($webhook)) : 'whsec_...'; ?>"
                           autocomplete="off"
                           <?php echo $uses_constants && $webhook ? 'disabled' : ''; ?>>
                    <p class="description">
                        Webhook signing secret from Stripe Dashboard → Webhooks
                    </p>
                </div>

                <div class="shaped-wizard-validation" id="stripe-validation" style="display: none;">
                    <span class="spinner"></span>
                    <span class="validation-message"></span>
                </div>
            </div>

            <?php if (!$uses_constants): ?>
            <div class="shaped-wizard-info">
                <h4>Production Recommendation</h4>
                <p>For production sites, we recommend storing Stripe keys in <code>wp-config.php</code>:</p>
                <pre><code>define('SHAPED_STRIPE_SECRET', 'sk_live_...');
define('SHAPED_STRIPE_WEBHOOK', 'whsec_...');</code></pre>
                <p>This prevents accidental exposure through database backups.</p>
            </div>
            <?php endif; ?>

            <div class="shaped-wizard-actions">
                <?php if ($secret && $webhook): ?>
                <button type="button" class="button button-secondary shaped-wizard-skip" data-step="stripe">
                    Skip (Already Configured)
                </button>
                <?php endif; ?>
                <button type="button" class="button button-primary shaped-wizard-next" data-step="stripe">
                    <?php echo ($secret && $webhook) ? 'Update & Continue' : 'Save & Continue'; ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Step 2: Payment Mode
     */
    private static function render_step_payment_mode(): void {
        $payment_mode = get_option(Shaped_Pricing::OPT_PAYMENT_MODE, 'scheduled');
        $deposit_percent = get_option(Shaped_Pricing::OPT_DEPOSIT_PERCENT, 30);
        $scheduled_threshold = get_option(Shaped_Pricing::OPT_SCHEDULED_CHARGE_THRESHOLD, 7);
        ?>
        <div class="shaped-wizard-step-content" data-step="payment_mode">
            <h2>Payment Mode</h2>
            <p class="description">Choose how guests pay when booking directly.</p>

            <div class="shaped-wizard-form">
                <fieldset class="shaped-option-group">
                    <!-- Scheduled Charge -->
                    <div class="shaped-option-item<?php echo $payment_mode === 'scheduled' ? ' is-selected' : ''; ?>" data-mode="scheduled">
                        <label class="shaped-option-label<?php echo $payment_mode === 'scheduled' ? ' is-selected' : ''; ?>">
                            <input type="radio" name="payment_mode" value="scheduled" <?php checked($payment_mode, 'scheduled'); ?>>
                            <strong>Scheduled Charge</strong>
                            <span class="option-description">
                                Smart charging based on check-in date. Card saved for future bookings, charged automatically before arrival.
                            </span>
                        </label>
                        <div class="shaped-settings-panel shaped-settings-panel--success<?php echo $payment_mode === 'scheduled' ? ' is-visible' : ''; ?>">
                            <label for="scheduled_threshold">Days before check-in to charge:</label>
                            <input type="number" id="scheduled_threshold" name="scheduled_threshold"
                                   value="<?php echo esc_attr($scheduled_threshold); ?>" min="0" max="60">
                            <span>days</span>
                            <p class="description">
                                Bookings made ≥<?php echo esc_html($scheduled_threshold); ?> days out: save card, charge later.<br>
                                Bookings made &lt;<?php echo esc_html($scheduled_threshold); ?> days out: charge immediately.
                            </p>
                        </div>
                    </div>

                    <!-- Deposit -->
                    <div class="shaped-option-item<?php echo $payment_mode === 'deposit' ? ' is-selected' : ''; ?>" data-mode="deposit">
                        <label class="shaped-option-label<?php echo $payment_mode === 'deposit' ? ' is-selected' : ''; ?>">
                            <input type="radio" name="payment_mode" value="deposit" <?php checked($payment_mode, 'deposit'); ?>>
                            <strong>Deposit</strong>
                            <span class="option-description">
                                Collect a percentage upfront. Guest pays remaining balance on arrival.
                            </span>
                        </label>
                        <div class="shaped-settings-panel shaped-settings-panel--primary<?php echo $payment_mode === 'deposit' ? ' is-visible' : ''; ?>">
                            <label for="deposit_percent">Deposit percentage:</label>
                            <input type="number" id="deposit_percent" name="deposit_percent"
                                   value="<?php echo esc_attr($deposit_percent); ?>" min="1" max="100">
                            <span>%</span>
                            <p class="description">
                                Example: 30% of €200 = €60 now, €140 on arrival
                            </p>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="shaped-wizard-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-setup-wizard&step=stripe')); ?>" class="button button-secondary">
                    ← Back
                </a>
                <button type="button" class="button button-primary shaped-wizard-next" data-step="payment_mode">
                    Save & Continue
                </button>
            </div>
        </div>

        <script>
        (function() {
            const modeInputs = document.querySelectorAll('input[name="payment_mode"]');
            const optionItems = document.querySelectorAll('.shaped-option-item');

            modeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    optionItems.forEach(item => {
                        const itemMode = item.dataset.mode;
                        const label = item.querySelector('.shaped-option-label');
                        const panel = item.querySelector('.shaped-settings-panel');

                        if (itemMode === this.value) {
                            item.classList.add('is-selected');
                            label.classList.add('is-selected');
                            if (panel) panel.classList.add('is-visible');
                        } else {
                            item.classList.remove('is-selected');
                            label.classList.remove('is-selected');
                            if (panel) panel.classList.remove('is-visible');
                        }
                    });
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Step 3: Room Discounts
     */
    private static function render_step_discounts(): void {
        $room_types = Shaped_Pricing::fetch_room_types();
        $discounts = get_option(Shaped_Pricing::OPT_DISCOUNTS, Shaped_Pricing::discount_defaults());

        if (empty($room_types)) {
            ?>
            <div class="shaped-wizard-step-content" data-step="discounts">
                <h2>Room Discounts</h2>
                <div class="shaped-wizard-notice shaped-wizard-notice--warning">
                    <span class="dashicons dashicons-warning"></span>
                    <p>No room types found. Create room types in MotoPress Hotel Booking first, then return to configure discounts.</p>
                </div>
                <div class="shaped-wizard-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-setup-wizard&step=payment_mode')); ?>" class="button button-secondary">
                        ← Back
                    </a>
                    <button type="button" class="button button-primary shaped-wizard-skip" data-step="discounts">
                        Skip for Now
                    </button>
                </div>
            </div>
            <?php
            return;
        }
        ?>
        <div class="shaped-wizard-step-content" data-step="discounts">
            <h2>Direct Booking Discounts</h2>
            <p class="description">Set discount percentages guests receive when booking directly vs OTAs like Booking.com.</p>

            <div class="shaped-wizard-form">
                <table class="wp-list-table widefat fixed striped shaped-pricing-table">
                    <thead>
                        <tr>
                            <th>Room Type</th>
                            <th style="width: 150px;">Discount (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($room_types as $slug => $title):
                            $discount = isset($discounts[$slug]) ? intval($discounts[$slug]) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($title); ?></strong></td>
                            <td>
                                <input type="number"
                                       name="discounts[<?php echo esc_attr($slug); ?>]"
                                       value="<?php echo esc_attr($discount); ?>"
                                       min="0" max="100" step="1"
                                       class="small-text">
                                <span>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="description" style="margin-top: 12px;">
                    <strong>Tip:</strong> 10-15% is typical. This encourages direct bookings while covering OTA commission savings.
                </p>
            </div>

            <div class="shaped-wizard-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-setup-wizard&step=payment_mode')); ?>" class="button button-secondary">
                    ← Back
                </a>
                <button type="button" class="button button-primary shaped-wizard-next" data-step="discounts">
                    Save & Continue
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Step 4: Modal Pages
     */
    private static function render_step_modals(): void {
        $modal_pages = get_option(Shaped_Admin::OPT_MODAL_PAGES, []);
        $modal_types = Shaped_Admin::get_modal_types();
        ?>
        <div class="shaped-wizard-step-content" data-step="modals">
            <h2>Modal Pages</h2>
            <p class="description">Assign WordPress pages to display in modals during checkout.</p>

            <div class="shaped-wizard-form">
                <table class="form-table">
                    <?php foreach ($modal_types as $key => $label):
                        $page_id = isset($modal_pages[$key]) ? intval($modal_pages[$key]) : 0;
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="modal_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name'              => 'modal_pages[' . esc_attr($key) . ']',
                                'id'                => 'modal_' . esc_attr($key),
                                'selected'          => $page_id,
                                'show_option_none'  => '— Select Page —',
                                'option_none_value' => '0',
                            ]);
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <p class="description">
                    <strong>Note:</strong> Create these pages first if they don't exist. You can also configure this later in Shaped Core settings.
                </p>
            </div>

            <div class="shaped-wizard-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-setup-wizard&step=discounts')); ?>" class="button button-secondary">
                    ← Back
                </a>
                <button type="button" class="button button-secondary shaped-wizard-skip" data-step="modals">
                    Skip
                </button>
                <button type="button" class="button button-primary shaped-wizard-next" data-step="modals">
                    Save & Continue
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Step 5: Complete
     */
    private static function render_step_complete(): void {
        // Mark setup as complete
        update_option(self::OPT_SETUP_COMPLETE, true);
        ?>
        <div class="shaped-wizard-step-content shaped-wizard-complete" data-step="complete">
            <div class="shaped-wizard-success-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <h2>Setup Complete!</h2>
            <p>Your Shaped Core booking system is now configured and ready to use.</p>

            <div class="shaped-wizard-next-steps">
                <h3>Next Steps</h3>
                <ul>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-pricing')); ?>">
                            <span class="dashicons dashicons-money-alt"></span>
                            Review Pricing Settings
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-settings')); ?>">
                            <span class="dashicons dashicons-admin-generic"></span>
                            Shaped Core Settings
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-config-health')); ?>">
                            <span class="dashicons dashicons-heart"></span>
                            View Configuration Health
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(home_url()); ?>" target="_blank">
                            <span class="dashicons dashicons-external"></span>
                            View Your Site
                        </a>
                    </li>
                </ul>
            </div>

            <div class="shaped-wizard-actions" style="justify-content: center;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-config-health')); ?>" class="button button-primary button-hero">
                    View Configuration Health
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render Configuration Health page
     */
    public static function render_health_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $checks = self::get_health_checks();
        $all_pass = !in_array(false, array_column($checks, 'status'));
        ?>
        <div class="wrap shaped-admin-wrap">
            <h1>Configuration Health</h1>
            <p class="description">Overview of your Shaped Core configuration status.</p>

            <div class="shaped-health-summary <?php echo $all_pass ? 'is-healthy' : 'has-issues'; ?>">
                <span class="dashicons <?php echo $all_pass ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                <span class="summary-text">
                    <?php echo $all_pass ? 'All systems configured correctly!' : 'Some configuration items need attention.'; ?>
                </span>
                <?php if (!$all_pass): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=shaped-setup-wizard')); ?>" class="button button-primary">
                    Run Setup Wizard
                </a>
                <?php endif; ?>
            </div>

            <table class="wp-list-table widefat fixed striped shaped-health-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Status</th>
                        <th>Configuration Item</th>
                        <th>Details</th>
                        <th style="width: 150px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $check): ?>
                    <tr class="<?php echo $check['status'] ? 'status-ok' : 'status-warning'; ?>">
                        <td>
                            <span class="dashicons <?php echo $check['status'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"
                                  style="color: <?php echo $check['status'] ? '#4C9155' : '#dba617'; ?>;"></span>
                        </td>
                        <td><strong><?php echo esc_html($check['label']); ?></strong></td>
                        <td><?php echo esc_html($check['details']); ?></td>
                        <td>
                            <?php if (!empty($check['action_url'])): ?>
                            <a href="<?php echo esc_url($check['action_url']); ?>" class="button button-small">
                                <?php echo esc_html($check['action_label'] ?? 'Configure'); ?>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="shaped-admin-info" style="margin-top: 24px;">
                <h3>Environment Information</h3>
                <table class="form-table">
                    <tr>
                        <th>PHP Version</th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th>WordPress Version</th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th>Shaped Core Version</th>
                        <td><?php echo esc_html(SHAPED_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th>Stripe Keys Source</th>
                        <td><?php echo self::stripe_uses_constants() ? 'wp-config.php constants' : 'Database'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get health check results
     */
    private static function get_health_checks(): array {
        $checks = [];

        // MotoPress Hotel Booking
        $checks[] = [
            'label'        => 'MotoPress Hotel Booking',
            'status'       => function_exists('MPHB'),
            'details'      => function_exists('MPHB') ? 'Active and running' : 'Plugin not active',
            'action_url'   => admin_url('plugins.php'),
            'action_label' => 'Manage Plugins',
        ];

        // Stripe SDK
        $stripe_sdk_exists = file_exists(SHAPED_DIR . 'vendor/stripe-php/init.php')
                          || file_exists(WP_CONTENT_DIR . '/mu-plugins/stripe-php/init.php');
        $checks[] = [
            'label'        => 'Stripe PHP SDK',
            'status'       => $stripe_sdk_exists,
            'details'      => $stripe_sdk_exists ? 'Found in vendor or mu-plugins' : 'Not found',
            'action_url'   => '',
            'action_label' => '',
        ];

        // Stripe Secret Key
        $stripe_secret = self::get_stripe_secret();
        $checks[] = [
            'label'        => 'Stripe Secret Key',
            'status'       => !empty($stripe_secret),
            'details'      => !empty($stripe_secret)
                ? 'Configured (' . self::mask_key($stripe_secret) . ')'
                : 'Not configured',
            'action_url'   => admin_url('admin.php?page=shaped-setup-wizard&step=stripe'),
            'action_label' => 'Configure',
        ];

        // Stripe Webhook Secret
        $stripe_webhook = self::get_stripe_webhook();
        $checks[] = [
            'label'        => 'Stripe Webhook Secret',
            'status'       => !empty($stripe_webhook),
            'details'      => !empty($stripe_webhook)
                ? 'Configured (' . self::mask_key($stripe_webhook) . ')'
                : 'Not configured',
            'action_url'   => admin_url('admin.php?page=shaped-setup-wizard&step=stripe'),
            'action_label' => 'Configure',
        ];

        // Payment Mode
        $payment_mode = get_option(Shaped_Pricing::OPT_PAYMENT_MODE, '');
        $checks[] = [
            'label'        => 'Payment Mode',
            'status'       => !empty($payment_mode),
            'details'      => !empty($payment_mode)
                ? ucfirst($payment_mode) . ' mode'
                : 'Not configured',
            'action_url'   => admin_url('admin.php?page=shaped-pricing'),
            'action_label' => 'Configure',
        ];

        // Room Types
        $room_types = Shaped_Pricing::fetch_room_types();
        $checks[] = [
            'label'        => 'Room Types',
            'status'       => !empty($room_types),
            'details'      => !empty($room_types)
                ? count($room_types) . ' room types found'
                : 'No room types in MotoPress',
            'action_url'   => admin_url('edit.php?post_type=mphb_room_type'),
            'action_label' => 'Manage Rooms',
        ];

        // Modal Pages
        $modal_pages = get_option(Shaped_Admin::OPT_MODAL_PAGES, []);
        $configured_modals = array_filter($modal_pages);
        $checks[] = [
            'label'        => 'Modal Pages',
            'status'       => !empty($configured_modals),
            'details'      => !empty($configured_modals)
                ? count($configured_modals) . ' modal pages configured'
                : 'No modal pages assigned',
            'action_url'   => admin_url('admin.php?page=shaped-settings'),
            'action_label' => 'Configure',
        ];

        return $checks;
    }

    /**
     * AJAX: Save step data
     */
    public static function ajax_save_step(): void {
        check_ajax_referer('shaped_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $step = isset($_POST['step']) ? sanitize_key($_POST['step']) : '';
        $data = isset($_POST['data']) ? $_POST['data'] : [];

        $result = false;

        switch ($step) {
            case 'stripe':
                $result = self::save_step_stripe($data);
                break;
            case 'payment_mode':
                $result = self::save_step_payment_mode($data);
                break;
            case 'discounts':
                $result = self::save_step_discounts($data);
                break;
            case 'modals':
                $result = self::save_step_modals($data);
                break;
        }

        if ($result) {
            $steps = array_keys(self::$steps);
            $current_index = array_search($step, $steps);
            $next_step = isset($steps[$current_index + 1]) ? $steps[$current_index + 1] : 'complete';

            wp_send_json_success([
                'message'   => 'Settings saved',
                'next_step' => $next_step,
                'next_url'  => admin_url('admin.php?page=shaped-setup-wizard&step=' . $next_step),
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to save settings']);
        }
    }

    /**
     * Save Stripe credentials
     */
    private static function save_step_stripe(array $data): bool {
        $secret = isset($data['stripe_secret']) ? sanitize_text_field($data['stripe_secret']) : '';
        $webhook = isset($data['stripe_webhook']) ? sanitize_text_field($data['stripe_webhook']) : '';

        // Only save if values provided (don't overwrite with empty)
        if (!empty($secret)) {
            update_option(self::OPT_STRIPE_SECRET, self::encrypt($secret));
        }
        if (!empty($webhook)) {
            update_option(self::OPT_STRIPE_WEBHOOK, self::encrypt($webhook));
        }

        return true;
    }

    /**
     * Save payment mode settings
     */
    private static function save_step_payment_mode(array $data): bool {
        $mode = isset($data['payment_mode']) ? sanitize_key($data['payment_mode']) : 'scheduled';
        $deposit = isset($data['deposit_percent']) ? intval($data['deposit_percent']) : 30;
        $threshold = isset($data['scheduled_threshold']) ? intval($data['scheduled_threshold']) : 7;

        update_option(Shaped_Pricing::OPT_PAYMENT_MODE, Shaped_Pricing::sanitize_payment_mode($mode));
        update_option(Shaped_Pricing::OPT_DEPOSIT_PERCENT, Shaped_Pricing::sanitize_deposit_percent($deposit));
        update_option(Shaped_Pricing::OPT_SCHEDULED_CHARGE_THRESHOLD, Shaped_Pricing::sanitize_scheduled_threshold($threshold));

        return true;
    }

    /**
     * Save discounts
     */
    private static function save_step_discounts(array $data): bool {
        $discounts = isset($data['discounts']) ? $data['discounts'] : [];
        $sanitized = Shaped_Pricing::sanitize_discounts($discounts);
        update_option(Shaped_Pricing::OPT_DISCOUNTS, $sanitized);
        return true;
    }

    /**
     * Save modal pages
     */
    private static function save_step_modals(array $data): bool {
        $modal_pages = isset($data['modal_pages']) ? $data['modal_pages'] : [];
        $sanitized = Shaped_Admin::sanitize_modal_pages($modal_pages);
        update_option(Shaped_Admin::OPT_MODAL_PAGES, $sanitized);
        return true;
    }

    /**
     * AJAX: Validate Stripe credentials
     */
    public static function ajax_validate_stripe(): void {
        check_ajax_referer('shaped_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $secret = isset($_POST['secret']) ? sanitize_text_field($_POST['secret']) : '';

        if (empty($secret)) {
            wp_send_json_error(['message' => 'Secret key is required']);
            return;
        }

        // Validate key format
        if (!preg_match('/^sk_(live|test)_[a-zA-Z0-9]+$/', $secret)) {
            wp_send_json_error(['message' => 'Invalid key format. Must start with sk_live_ or sk_test_']);
            return;
        }

        // Try to validate with Stripe API
        shaped_load_stripe_sdk();

        if (!class_exists('\Stripe\Stripe')) {
            wp_send_json_error(['message' => 'Stripe SDK not loaded. Key format is valid.']);
            return;
        }

        try {
            \Stripe\Stripe::setApiKey($secret);
            $account = \Stripe\Account::retrieve();

            wp_send_json_success([
                'message' => 'Valid! Connected to: ' . ($account->business_profile->name ?? $account->email ?? 'Stripe Account'),
                'mode'    => strpos($secret, 'sk_test_') === 0 ? 'test' : 'live',
            ]);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            wp_send_json_error(['message' => 'Invalid API key']);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Validation error: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Dismiss wizard notice
     */
    public static function ajax_dismiss_wizard(): void {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'shaped_wizard_dismiss')) {
            wp_die('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        update_option(self::OPT_SETUP_DISMISSED, true);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /**
     * AJAX: Skip step
     */
    public static function ajax_skip_step(): void {
        check_ajax_referer('shaped_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $step = isset($_POST['step']) ? sanitize_key($_POST['step']) : '';
        $steps = array_keys(self::$steps);
        $current_index = array_search($step, $steps);
        $next_step = isset($steps[$current_index + 1]) ? $steps[$current_index + 1] : 'complete';

        wp_send_json_success([
            'next_step' => $next_step,
            'next_url'  => admin_url('admin.php?page=shaped-setup-wizard&step=' . $next_step),
        ]);
    }
}
