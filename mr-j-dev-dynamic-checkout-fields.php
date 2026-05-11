<?php
/**
 * Plugin Name: Mr. J Dev Dynamic Checkout Fields
 * Plugin URI:  https://github.com/ajay2188/mr-j-dev-dynamic-checkout-fields
 * Description: Add conditional custom fields to the WooCommerce checkout based on cart contents, cart value, or shipping country. Saves data to order meta and displays it in the admin.
 * Version:     1.0.0
 * Author:      Mr. J
 * License:     GPL-2.0+
 * Text Domain: mr-j-dev-dynamic-checkout-fields
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

defined('ABSPATH') || exit;

define('WCDCF_VERSION', '1.0.0');
define('WCDCF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCDCF_PLUGIN_URL', plugin_dir_url(__FILE__));

// ─────────────────────────────────────────────

// ─────────────────────────────────────────────
// Admin Enqueue Scripts
// ─────────────────────────────────────────────
add_action('admin_enqueue_scripts', 'wcdcf_admin_enqueue_scripts');
function wcdcf_admin_enqueue_scripts($hook_suffix) {
    if ($hook_suffix !== 'woocommerce_page_wcdcf-manager') {
        return;
    }

    wp_enqueue_style(
        'wcdcf-admin-style',
        WCDCF_PLUGIN_URL . 'assets/admin.css',
        [],
        WCDCF_VERSION
    );

    wp_enqueue_script(
        'wcdcf-admin-script',
        WCDCF_PLUGIN_URL . 'assets/admin.js',
        [],
        WCDCF_VERSION,
        true
    );

    $fields = wcdcf_get_fields();
    wp_localize_script('wcdcf-admin-script', 'wcdcfData', [
        'fields' => array_values($fields),
        'i18n' => [
            'amount' => __('Amount (₹)', 'mr-j-dev-dynamic-checkout-fields'),
            'amount_hint' => __('Show field when cart total exceeds this amount.', 'mr-j-dev-dynamic-checkout-fields'),
            'country' => __('Country Code', 'mr-j-dev-dynamic-checkout-fields'),
            'country_hint' => __('2-letter ISO code, e.g. IN, US, GB.', 'mr-j-dev-dynamic-checkout-fields'),
            'product' => __('Product ID', 'mr-j-dev-dynamic-checkout-fields'),
            'product_hint' => __('Show field only when this product is in the cart.', 'mr-j-dev-dynamic-checkout-fields'),
            'edit_field' => __('Edit Field', 'mr-j-dev-dynamic-checkout-fields'),
            'update_field' => __('Update Field', 'mr-j-dev-dynamic-checkout-fields'),
            'delete_field' => __('Delete field', 'mr-j-dev-dynamic-checkout-fields'),
            'new_field' => __('New Field', 'mr-j-dev-dynamic-checkout-fields'),
            'save_field' => __('Save Field', 'mr-j-dev-dynamic-checkout-fields'),
        ]
    ]);
}

// ─────────────────────────────────────────────
// 1. Admin menu
// ─────────────────────────────────────────────
add_action('admin_menu', 'wcdcf_admin_menu');
function wcdcf_admin_menu()
{
    add_submenu_page(
        'woocommerce',
        __('Mr. J Dev Dynamic Checkout Fields', 'mr-j-dev-dynamic-checkout-fields'),
        __('Checkout Fields', 'mr-j-dev-dynamic-checkout-fields'),
        'manage_woocommerce',
        'wcdcf-manager',
        'wcdcf_render_manager_page'
    );
}

// ─────────────────────────────────────────────
// 2. Helper – get saved field definitions
// ─────────────────────────────────────────────
function wcdcf_get_fields()
{
    return get_option('wcdcf_fields', []);
}

// ─────────────────────────────────────────────
// 3. Save / update field definition
// ─────────────────────────────────────────────
add_action('admin_post_wcdcf_save_field', 'wcdcf_save_field');
function wcdcf_save_field()
{
    check_admin_referer('wcdcf_save_field');
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Permission denied.', 'mr-j-dev-dynamic-checkout-fields'));
    }

    $fields = wcdcf_get_fields();
    $edit_id = sanitize_key($_POST['edit_field_id'] ?? '');

    $data = [
        'id' => isset($_POST['field_id']) ? sanitize_key(wp_unslash($_POST['field_id'])) : '',
        'label' => isset($_POST['field_label']) ? sanitize_text_field(wp_unslash($_POST['field_label'])) : '',
        'type' => isset($_POST['field_type']) ? sanitize_key(wp_unslash($_POST['field_type'])) : '',
        'required' => isset($_POST['field_required']),
        'options' => sanitize_textarea_field(wp_unslash($_POST['field_options'] ?? '')),
        'condition_type' => isset($_POST['condition_type']) ? sanitize_key(wp_unslash($_POST['condition_type'])) : '',
        'condition_value' => sanitize_text_field(wp_unslash($_POST['condition_value'] ?? '')),
        'validation_regex' => sanitize_text_field(wp_unslash($_POST['validation_regex'] ?? '')),
    ];

    $updated = false;
    // When editing, match by the original ID (edit_field_id), replace with new data
    $match_id = $edit_id ?: $data['id'];
    foreach ($fields as $k => $f) {
        if ($f['id'] === $match_id) {
            $fields[$k] = $data;
            $updated = true;
            break;
        }
    }
    if (!$updated) {
        $fields[] = $data;
    }

    update_option('wcdcf_fields', $fields);
    wp_safe_redirect(admin_url('admin.php?page=wcdcf-manager&saved=1'));
    exit;
}

// ─────────────────────────────────────────────
// 4. Delete field definition
// ─────────────────────────────────────────────
add_action('admin_post_wcdcf_delete_field', 'wcdcf_delete_field');
function wcdcf_delete_field()
{
    check_admin_referer('wcdcf_delete_field');
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Permission denied.', 'mr-j-dev-dynamic-checkout-fields'));
    }

    $id = isset($_GET['field_id']) ? sanitize_key(wp_unslash($_GET['field_id'])) : '';
    $fields = array_filter(wcdcf_get_fields(), fn($f) => $f['id'] !== $id);
    update_option('wcdcf_fields', array_values($fields));

    wp_safe_redirect(admin_url('admin.php?page=wcdcf-manager&deleted=1'));
    exit;
}

// ─────────────────────────────────────────────
// 5. Admin UI – split-panel layout
// ─────────────────────────────────────────────
function wcdcf_render_manager_page()
{
    $fields = wcdcf_get_fields();
    
    $type_icons = [
        'text' => '📝',
        'textarea' => '📄',
        'select' => '🔽',
        'checkbox' => '☑️',
    ];
    $condition_labels = [
        'always' => __('Always show', 'mr-j-dev-dynamic-checkout-fields'),
        'cart_total_gt' => __('Cart total >', 'mr-j-dev-dynamic-checkout-fields'),
        'shipping_country' => __('Shipping country =', 'mr-j-dev-dynamic-checkout-fields'),
        'product_in_cart' => __('Product ID in cart', 'mr-j-dev-dynamic-checkout-fields'),
    ];
    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Mr. J Dev Dynamic Checkout Fields', 'mr-j-dev-dynamic-checkout-fields'); ?></h1>
        <hr class="wp-header-end" />

        <?php if (isset($_GET['saved'])): // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('✅ Field saved successfully.', 'mr-j-dev-dynamic-checkout-fields'); ?></p>
            </div>
        <?php elseif (isset($_GET['deleted'])): // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('🗑️ Field deleted.', 'mr-j-dev-dynamic-checkout-fields'); ?></p>
            </div>
        <?php endif; ?>

        <div id="wcdcf-page">

            <!-- ══════════════════════════════ -->
            <!-- LEFT – Field List              -->
            <!-- ══════════════════════════════ -->
            <div id="wcdcf-list-panel">
                <h2><?php esc_html_e('Your Fields', 'mr-j-dev-dynamic-checkout-fields'); ?> (<?php echo count($fields); ?>)</h2>

                <button type="button" class="wcdcf-add-btn" id="wcdcf-new-btn">
                    ＋ <?php esc_html_e('Add New Field', 'mr-j-dev-dynamic-checkout-fields'); ?>
                </button>

                <?php if (empty($fields)): ?>
                    <div class="wcdcf-list-empty">
                        <?php esc_html_e('No fields yet. Click "Add New Field" to get started.', 'mr-j-dev-dynamic-checkout-fields'); ?>
                    </div>
                <?php else: ?>
                    <div id="wcdcf-card-list">
                        <?php foreach ($fields as $field):
                            $icon = $type_icons[$field['type']] ?? '📋';
                            $cond = $condition_labels[$field['condition_type']] ?? $field['condition_type'];
                            $cond .= $field['condition_value'] ? ' ' . $field['condition_value'] : '';
                            ?>
                            <div class="wcdcf-field-card" data-id="<?php echo esc_attr($field['id']); ?>"
                                onclick="wcdcfEditField('<?php echo esc_js($field['id']); ?>')">

                                <button type="button" class="wcdcf-card-del"
                                    title="<?php esc_attr_e('Delete', 'mr-j-dev-dynamic-checkout-fields'); ?>"
                                    onclick="event.stopPropagation(); wcdcfDeleteField('<?php echo esc_js($field['id']); ?>', '<?php echo esc_js($field['label']); ?>')">
                                    ✕
                                </button>

                                <div class="wcdcf-card-top">
                                    <span class="wcdcf-card-icon"><?php echo esc_html($icon); ?></span>
                                    <span class="wcdcf-card-label"><?php echo esc_html($field['label']); ?></span>
                                    <span class="wcdcf-card-id"><?php echo esc_html($field['id']); ?></span>
                                </div>
                                <div class="wcdcf-card-meta">
                                    <?php if ('text' === $field['type']): ?>
                                        <span class="wcdcf-type-badge badge-text">Text</span>
                                    <?php elseif ('textarea' === $field['type']): ?>
                                        <span class="wcdcf-type-badge badge-text"
                                            style="background:#f0eaff;color:#6941c6;">Textarea</span>
                                    <?php elseif ('select' === $field['type']): ?>
                                        <span class="wcdcf-type-badge badge-select">Dropdown</span>
                                    <?php else: ?>
                                        <span class="wcdcf-type-badge badge-checkbox">Checkbox</span>
                                    <?php endif; ?>
                                    &nbsp;·&nbsp;<?php echo esc_html($cond); ?>
                                    <?php if ($field['required']): ?>
                                        &nbsp;·&nbsp;<strong style="color:#d63638;">Required</strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div><!-- #wcdcf-list-panel -->

            <!-- ══════════════════════════════ -->
            <!-- RIGHT – Form Panel             -->
            <!-- ══════════════════════════════ -->
            <div id="wcdcf-form-panel">
                <h2 id="wcdcf-panel-label"><?php esc_html_e('Field Details', 'mr-j-dev-dynamic-checkout-fields'); ?></h2>

                <!-- Placeholder (shown when nothing selected) -->
                <div id="wcdcf-form-placeholder">
                    <span class="dashicons dashicons-edit-large"></span>
                    <p><?php esc_html_e('Select a field on the left to edit it, or click "Add New Field" to create one.', 'mr-j-dev-dynamic-checkout-fields'); ?>
                    </p>
                </div>

                <!-- Actual form (hidden until a field is selected / New clicked) -->
                <div id="wcdcf-form-card" class="wcdcf-form-card" style="display:none;">

                    <div class="wcdcf-form-header">
                        <h3 id="wcdcf-form-title">➕ <?php esc_html_e('New Field', 'mr-j-dev-dynamic-checkout-fields'); ?></h3>
                        <div>
                            <span id="wcdcf-editing-lbl" style="display:none;font-size:12px;background:#fff3cd;color:#856404;
                                         border:1px solid #ffc107;border-radius:3px;padding:2px 8px;">
                                <?php esc_html_e('Editing', 'mr-j-dev-dynamic-checkout-fields'); ?>
                            </span>
                        </div>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('wcdcf_save_field'); ?>
                        <input type="hidden" name="action" value="wcdcf_save_field" />
                        <input type="hidden" name="edit_field_id" id="wcdcf-edit-id" value="" />

                        <div class="wcdcf-form-body two-col">

                            <!-- Field ID -->
                            <div class="wcdcf-fld">
                                <label
                                    for="field_id"><?php esc_html_e('Field ID (slug)', 'mr-j-dev-dynamic-checkout-fields'); ?></label>
                                <input type="text" id="field_id" name="field_id" required placeholder="e.g. gift_message"
                                    pattern="[a-z0-9_]+" />
                                <span
                                    class="wcdcf-hint"><?php esc_html_e('Lowercase letters, numbers, underscores only.', 'mr-j-dev-dynamic-checkout-fields'); ?></span>
                            </div>

                            <!-- Label -->
                            <div class="wcdcf-fld">
                                <label
                                    for="field_label"><?php esc_html_e('Label', 'mr-j-dev-dynamic-checkout-fields'); ?></label>
                                <input type="text" id="field_label" name="field_label" required
                                    placeholder="e.g. Gift Message" />
                            </div>

                            <!-- Field Type -->
                            <div class="wcdcf-fld">
                                <label
                                    for="field_type"><?php esc_html_e('Field Type', 'mr-j-dev-dynamic-checkout-fields'); ?></label>
                                <select id="field_type" name="field_type">
                                    <option value="text">📝 <?php esc_html_e('Text', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                    </option>
                                    <option value="textarea">📄
                                        <?php esc_html_e('Textarea (multi-line)', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                    </option>
                                    <option value="select">🔽
                                        <?php esc_html_e('Dropdown', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                    </option>
                                    <option value="checkbox">☑️
                                        <?php esc_html_e('Checkbox', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                    </option>
                                </select>
                            </div>

                            <!-- Required -->
                            <div class="wcdcf-fld" style="display:flex;align-items:flex-end;padding-bottom:18px;">
                                <div class="wcdcf-required-row">
                                    <input type="checkbox" id="field_required" name="field_required" value="1" />
                                    <label
                                        for="field_required"><?php esc_html_e('Mark as required field', 'mr-j-dev-dynamic-checkout-fields'); ?></label>
                                </div>
                            </div>

                            <!-- Dropdown options (shown only for "select" type) -->
                            <div class="wcdcf-fld span-2" id="wcdcf-options-row" style="display:none;">
                                <label
                                    for="field_options"><?php esc_html_e('Dropdown Options', 'mr-j-dev-dynamic-checkout-fields'); ?></label>
                                <textarea id="field_options" name="field_options"
                                    placeholder="<?php esc_attr_e('One option per line, e.g.&#10;Standard&#10;Express&#10;Pickup', 'mr-j-dev-dynamic-checkout-fields'); ?>"></textarea>
                                <span
                                    class="wcdcf-hint"><?php esc_html_e('Each line becomes one dropdown option.', 'mr-j-dev-dynamic-checkout-fields'); ?></span>
                            </div>

                            <!-- Show Condition -->
                            <div class="wcdcf-fld">
                                <label
                                    for="condition_type"><?php esc_html_e('Show When', 'mr-j-dev-dynamic-checkout-fields'); ?></label>
                                <select id="condition_type" name="condition_type">
                                    <option value="always"><?php esc_html_e('Always', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                    </option>
                                    <option value="cart_total_gt">
                                        <?php esc_html_e('Cart total is greater than', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                    </option>
                                    <option value="shipping_country">
                                        <?php esc_html_e('Shipping country equals', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                    </option>
                                    <option value="product_in_cart">
                                        <?php esc_html_e('Product ID is in cart', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                    </option>
                                </select>
                            </div>

                            <!-- Condition Value (hidden for "always") -->
                            <div class="wcdcf-fld" id="wcdcf-cond-val-row">
                                <label for="condition_value" id="wcdcf-cond-val-label">
                                    <?php esc_html_e('Condition Value', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                </label>
                                <input type="text" id="condition_value" name="condition_value" placeholder="e.g. 500" />
                                <span class="wcdcf-hint" id="wcdcf-cond-hint">
                                    <?php esc_html_e('Amount in ₹', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                </span>
                            </div>

                            <!-- Validation Regex -->
                            <div class="wcdcf-fld span-2" id="wcdcf-regex-row">
                                <label
                                    for="validation_regex"><?php esc_html_e('Validation Regex', 'mr-j-dev-dynamic-checkout-fields'); ?>
                                    <span
                                        style="font-weight:normal;color:#646970;">(<?php esc_html_e('optional', 'mr-j-dev-dynamic-checkout-fields'); ?>)</span></label>
                                <input type="text" id="validation_regex" name="validation_regex"
                                    placeholder="e.g. ^[0-9]{10}$ for a 10-digit phone number" />
                                <span
                                    class="wcdcf-hint"><?php esc_html_e('Only applied to Text fields. Leave blank to skip.', 'mr-j-dev-dynamic-checkout-fields'); ?></span>
                            </div>

                        </div><!-- .wcdcf-form-body -->

                        <div class="wcdcf-form-footer">
                            <button type="submit" class="button button-primary" id="wcdcf-submit-btn">
                                💾 <?php esc_html_e('Save Field', 'mr-j-dev-dynamic-checkout-fields'); ?>
                            </button>
                            <span class="cancel" id="wcdcf-cancel-btn">
                                <?php esc_html_e('← Cancel', 'mr-j-dev-dynamic-checkout-fields'); ?>
                            </span>
                        </div>
                    </form>
                </div><!-- #wcdcf-form-card -->
            </div><!-- #wcdcf-form-panel -->

        </div><!-- #wcdcf-page -->
    </div><!-- .wrap -->

    <!-- Delete form (submits via JS redirect) -->
    <form id="wcdcf-delete-form" method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        style="display:none;">
        <input type="hidden" name="action" value="wcdcf_delete_field" />
        <input type="hidden" name="field_id" id="wcdcf-del-id" value="" />
        <?php wp_nonce_field('wcdcf_delete_field', '_wpnonce', true, true); ?>
    </form>
    <?php
}

// ─────────────────────────────────────────────
// 6a. Block Checkout support (WooCommerce 8.9+)
//     Registers fields via the Additional Checkout
//     Fields API so they appear in both the block
//     and classic checkout automatically.
//     Note: show-conditions are not applied in
//     block mode — all registered fields always show.
// ─────────────────────────────────────────────
add_action('woocommerce_init', 'wcdcf_register_block_fields', 5);
function wcdcf_register_block_fields()
{
    if (!function_exists('woocommerce_register_additional_checkout_field')) {
        return; // WooCommerce < 8.9 — block fields not supported
    }

    foreach (wcdcf_get_fields() as $field) {
        // Namespace the ID as required by the API (e.g. "wcdcf/my_field")
        $args = [
            'id' => 'wcdcf/' . $field['id'],
            'label' => $field['label'],
            'location' => 'order', // renders in the "Order" section of the block checkout
            'required' => (bool) $field['required'],
        ];

        if ('select' === $field['type']) {
            $raw = array_filter(array_map('trim', explode("\n", $field['options'])));
            $options = array_values(
                array_map(fn($o) => ['value' => $o, 'label' => $o], $raw)
            );
            $args['type'] = 'select';
            $args['options'] = $options;
        } elseif ('checkbox' === $field['type']) {
            $args['type'] = 'checkbox';
        } elseif ('textarea' === $field['type']) {
            // Block checkout API has no textarea type — fall back to text
            $args['type'] = 'text';
        } else {
            $args['type'] = 'text';
        }

        try {
            woocommerce_register_additional_checkout_field($args);
        } catch (\Exception $e) {
            // Silently skip — e.g. duplicate ID on re-register
        }
    }
}

// ─────────────────────────────────────────────
// 6b. Classic Checkout render
//     Two hook positions: the primary (after order
//     notes) + a fallback (after customer details)
//     for themes that skip the order notes section.
//     A static flag prevents double-rendering.
// ─────────────────────────────────────────────
add_action('woocommerce_after_order_notes', 'wcdcf_render_checkout_fields', 10);
add_action('woocommerce_checkout_after_customer_details', 'wcdcf_render_checkout_fields', 20);
function wcdcf_render_checkout_fields($checkout)
{
    // Prevent double-rendering if both hooks fire on the same page load
    static $rendered = false;
    if ($rendered) {
        return;
    }

    $fields = wcdcf_get_fields();
    if (empty($fields)) {
        return;
    }

    // Check at least one field would actually be visible before rendering the wrapper
    $any_visible = false;
    foreach ($fields as $f) {
        if (wcdcf_check_condition($f)) {
            $any_visible = true;
            break;
        }
    }
    if (!$any_visible) {
        return;
    }

    $rendered = true;
    echo '<div id="wcdcf_custom_fields"><h3>' . esc_html__('Additional Information', 'mr-j-dev-dynamic-checkout-fields') . '</h3>';

    foreach ($fields as $field) {
        if (!wcdcf_check_condition($field)) {
            continue;
        }

        $key = '_wcdcf_' . $field['id'];
        echo '<div class="wcdcf-field" data-field-id="' . esc_attr($field['id']) . '">';

        if ('checkbox' === $field['type']) {
            woocommerce_form_field($key, [
                'type' => 'checkbox',
                'label' => $field['label'],
                'required' => $field['required'],
            ], $checkout->get_value($key));
        } elseif ('select' === $field['type']) {
            $raw_opts = explode("\n", $field['options']);
            $options = ['' => __('Select…', 'mr-j-dev-dynamic-checkout-fields')];
            foreach ($raw_opts as $opt) {
                $opt = trim($opt);
                if ($opt) {
                    $options[$opt] = $opt;
                }
            }
            woocommerce_form_field($key, [
                'type' => 'select',
                'label' => $field['label'],
                'required' => $field['required'],
                'options' => $options,
            ], $checkout->get_value($key));
        } elseif ('textarea' === $field['type']) {
            woocommerce_form_field($key, [
                'type' => 'textarea',
                'label' => $field['label'],
                'required' => $field['required'],
                'input_class' => ['wcdcf-textarea'],
                'custom_attributes' => ['rows' => 4],
            ], $checkout->get_value($key));
        } else {
            woocommerce_form_field($key, [
                'type' => 'text',
                'label' => $field['label'],
                'required' => $field['required'],
            ], $checkout->get_value($key));
        }

        echo '</div>';
    }

    echo '</div>';
}

// ─────────────────────────────────────────────
// 7. Condition evaluation helper
// ─────────────────────────────────────────────
function wcdcf_check_condition($field)
{
    $type = $field['condition_type'];
    $value = $field['condition_value'];

    if ('always' === $type) {
        return true;
    }

    if ('cart_total_gt' === $type) {
        return WC()->cart && WC()->cart->get_cart_contents_total() > (float) $value;
    }

    if ('shipping_country' === $type) {
        $country = WC()->customer ? WC()->customer->get_shipping_country() : '';
        return strtoupper($country) === strtoupper($value);
    }

    if ('product_in_cart' === $type) {
        if (!WC()->cart) {
            return false;
        }
        foreach (WC()->cart->get_cart() as $item) {
            if ((int) $item['product_id'] === (int) $value) {
                return true;
            }
        }
        return false;
    }

    return true;
}

// ─────────────────────────────────────────────
// 8. Validate required fields and regex on checkout
// ─────────────────────────────────────────────
add_action('woocommerce_checkout_process', 'wcdcf_validate_checkout_fields');
// phpcs:disable WordPress.Security.NonceVerification.Missing
function wcdcf_validate_checkout_fields()
{
    $fields = wcdcf_get_fields();
    foreach ($fields as $field) {
        if (!wcdcf_check_condition($field)) {
            continue;
        }

        $key = '_wcdcf_' . $field['id'];
        $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ($field['required'] && '' === $value) {
            /* translators: %s: field label */
            wc_add_notice(sprintf(__('"%s" is a required field.', 'mr-j-dev-dynamic-checkout-fields'), $field['label']), 'error');
        }

        if (!empty($field['validation_regex']) && '' !== $value) {
            if (!preg_match('/' . $field['validation_regex'] . '/', $value)) {
                /* translators: %s: field label */
                wc_add_notice(sprintf(__('"%s" value is not valid.', 'mr-j-dev-dynamic-checkout-fields'), $field['label']), 'error');
            }
        }
    }
}
// phpcs:enable WordPress.Security.NonceVerification.Missing

// ─────────────────────────────────────────────
// 9. Save custom field values to order meta
// ─────────────────────────────────────────────
add_action('woocommerce_checkout_update_order_meta', 'wcdcf_save_checkout_fields');
// phpcs:disable WordPress.Security.NonceVerification.Missing
function wcdcf_save_checkout_fields($order_id)
{
    $fields = wcdcf_get_fields();
    foreach ($fields as $field) {
        $key = '_wcdcf_' . $field['id'];
        if (isset($_POST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            update_post_meta($order_id, $key, sanitize_text_field(wp_unslash($_POST[$key]))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
    }
}
// phpcs:enable WordPress.Security.NonceVerification.Missing

// ─────────────────────────────────────────────
// 10. Display saved values in admin order page
// ─────────────────────────────────────────────
add_action('woocommerce_admin_order_data_after_billing_address', 'wcdcf_display_in_admin', 10, 1);
function wcdcf_display_in_admin($order)
{
    $fields = wcdcf_get_fields();
    $output = '';
    foreach ($fields as $field) {
        $key = '_wcdcf_' . $field['id'];
        $value = get_post_meta($order->get_id(), $key, true);
        if ('' !== $value && false !== $value) {
            $output .= '<p><strong>' . esc_html($field['label']) . ':</strong> ' . esc_html($value) . '</p>';
        }
    }
    if ($output) {
        echo wp_kses_post('<div class="wcdcf-admin-fields"><h4>' . esc_html__('Custom Checkout Fields', 'mr-j-dev-dynamic-checkout-fields') . '</h4>' . $output . '</div>');
    }
}

// ─────────────────────────────────────────────
// 11. Display saved values in order emails
// ─────────────────────────────────────────────
add_action('woocommerce_email_order_meta', 'wcdcf_display_in_emails', 10, 3);
function wcdcf_display_in_emails($order, $sent_to_admin, $plain_text)
{
    $fields = wcdcf_get_fields();
    foreach ($fields as $field) {
        $key = '_wcdcf_' . $field['id'];
        $value = get_post_meta($order->get_id(), $key, true);
        if ('' !== $value && false !== $value) {
            if ($plain_text) {
                echo esc_html($field['label']) . ': ' . esc_html($value) . "\n";
            } else {
                echo '<p><strong>' . esc_html($field['label']) . ':</strong> ' . esc_html($value) . '</p>';
            }
        }
    }
}
