<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Digital Highbridge membership: extended registration fields (identity
 * data, not the eligibility proof itself — see Angebot_Deals_Eligibility)
 * and the membership status gate that controls who may purchase deals.
 */
final class Angebot_Deals_Membership
{
    public const STATUS_UNVERIFIED = 'unverified';
    public const STATUS_PENDING    = 'pending';
    public const STATUS_VERIFIED   = 'verified';
    public const STATUS_REJECTED   = 'rejected';

    public const ENDPOINT = 'membership';

    public static function register_hooks(): void
    {
        // Registration form: extra identity fields.
        add_action('woocommerce_register_form', [self::class, 'render_registration_fields']);
        add_filter('woocommerce_registration_errors', [self::class, 'validate_registration'], 10, 3);
        add_action('woocommerce_created_customer', [self::class, 'save_registration_fields']);

        // My Account: "Membership" tab.
        add_action('init', [self::class, 'add_endpoint']);
        add_filter('woocommerce_account_menu_items', [self::class, 'account_menu_items'], 20);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [self::class, 'account_endpoint_content']);
        add_filter('woocommerce_endpoint_' . self::ENDPOINT . '_title', [self::class, 'account_endpoint_title']);

        // Purchase gate.
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'gate_add_to_cart'], 5, 2);
        add_action('woocommerce_check_cart_items', [self::class, 'gate_cart_items']);
        add_action('woocommerce_checkout_process', [self::class, 'gate_checkout']);

        add_filter('wp_privacy_personal_data_exporters', [self::class, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'register_eraser']);
    }

    public static function add_endpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public static function account_menu_items(array $items): array
    {
        $insert = ['membership' => __('Membership', 'angebot-deals')];
        $pos    = array_search('orders', array_keys($items), true);

        if ($pos === false) {
            return $insert + $items;
        }

        return array_slice($items, 0, $pos + 1, true)
            + $insert
            + array_slice($items, $pos + 1, null, true);
    }

    public static function account_endpoint_title(string $title): string
    {
        return __('Membership', 'angebot-deals');
    }

    public static function account_endpoint_content(): void
    {
        $user_id    = get_current_user_id();
        $status     = self::get_status($user_id);
        $submission = class_exists('Angebot_Deals_Eligibility') ? Angebot_Deals_Eligibility::get_latest_for_user($user_id) : null;

        include ANGEBOT_DEALS_PATH . 'templates/membership-status.php';
    }

    /* ---------------------------------------------------------------
     * Status
     * ------------------------------------------------------------- */

    public static function get_status(int $user_id): string
    {
        $status = (string) get_user_meta($user_id, '_angebot_membership_status', true);
        if (!in_array($status, [self::STATUS_UNVERIFIED, self::STATUS_PENDING, self::STATUS_VERIFIED, self::STATUS_REJECTED], true)) {
            return self::STATUS_UNVERIFIED;
        }
        return $status;
    }

    public static function set_status(int $user_id, string $status): void
    {
        update_user_meta($user_id, '_angebot_membership_status', $status);
    }

    public static function status_label(string $status): string
    {
        $labels = [
            self::STATUS_UNVERIFIED => __('Not yet verified', 'angebot-deals'),
            self::STATUS_PENDING    => __('Verification pending', 'angebot-deals'),
            self::STATUS_VERIFIED   => __('Verified member', 'angebot-deals'),
            self::STATUS_REJECTED   => __('Verification rejected', 'angebot-deals'),
        ];
        return $labels[$status] ?? $status;
    }

    public static function can_purchase(int $user_id): bool
    {
        if (!$user_id) {
            return false;
        }
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        return self::get_status($user_id) === self::STATUS_VERIFIED;
    }

    /**
     * Single source of truth for the "Buy" button on deal cards / the deal
     * detail page: what it says and where it points, depending on whether
     * the visitor still needs to log in, verify, or can simply buy.
     *
     * @return array{url:string,label:string,disabled:bool}
     */
    public static function cta_for_deal(int $deal_id, int $product_id, int $remaining): array
    {
        if ($remaining <= 0 || !$product_id) {
            return ['url' => '#', 'label' => __('Sold out', 'angebot-deals'), 'disabled' => true];
        }

        if (!is_user_logged_in()) {
            return [
                'url'      => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url(),
                'label'    => __('Log in to buy', 'angebot-deals'),
                'disabled' => false,
            ];
        }

        $user_id = get_current_user_id();
        if (!self::can_purchase($user_id)) {
            $labels = [
                self::STATUS_PENDING  => __('Verification pending', 'angebot-deals'),
                self::STATUS_REJECTED => __('Verify your eligibility', 'angebot-deals'),
            ];
            $status = self::get_status($user_id);
            return [
                'url'      => function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url(self::ENDPOINT) : '#',
                'label'    => $labels[$status] ?? __('Become a member to buy', 'angebot-deals'),
                'disabled' => false,
            ];
        }

        $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '#';
        return [
            'url'      => add_query_arg('add-to-cart', $product_id, $cart_url),
            'label'    => __('Buy now', 'angebot-deals'),
            'disabled' => false,
        ];
    }

    /* ---------------------------------------------------------------
     * Registration fields
     * ------------------------------------------------------------- */

    public static function render_registration_fields(): void
    {
        $full_name = isset($_POST['angebot_reg_full_name']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_full_name'])) : '';
        $dob       = isset($_POST['angebot_reg_dob']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_dob'])) : '';
        $phone     = isset($_POST['angebot_reg_phone']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_phone'])) : '';
        $address1  = isset($_POST['angebot_reg_address_1']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_address_1'])) : '';
        $city      = isset($_POST['angebot_reg_city']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_city'])) : '';
        $postcode  = isset($_POST['angebot_reg_postcode']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_postcode'])) : '';
        ?>
        <fieldset class="angebot-reg-fields">
            <legend><?php esc_html_e('Your details', 'angebot-deals'); ?></legend>
            <p class="form-row form-row-wide">
                <label for="angebot_reg_full_name"><?php esc_html_e('Full name', 'angebot-deals'); ?>&nbsp;<span class="required">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="angebot_reg_full_name" id="angebot_reg_full_name" value="<?php echo esc_attr($full_name); ?>" required>
            </p>
            <p class="form-row form-row-wide">
                <label for="angebot_reg_dob"><?php esc_html_e('Date of birth', 'angebot-deals'); ?>&nbsp;<span class="required">*</span></label>
                <input type="date" class="woocommerce-Input woocommerce-Input--text input-text" name="angebot_reg_dob" id="angebot_reg_dob" value="<?php echo esc_attr($dob); ?>" max="<?php echo esc_attr(gmdate('Y-m-d')); ?>" required>
            </p>
            <p class="form-row form-row-wide">
                <label for="angebot_reg_phone"><?php esc_html_e('Phone number', 'angebot-deals'); ?>&nbsp;<span class="required">*</span></label>
                <input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="angebot_reg_phone" id="angebot_reg_phone" value="<?php echo esc_attr($phone); ?>" required>
            </p>
            <p class="form-row form-row-wide">
                <label for="angebot_reg_address_1"><?php esc_html_e('Address', 'angebot-deals'); ?>&nbsp;<span class="required">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="angebot_reg_address_1" id="angebot_reg_address_1" placeholder="<?php esc_attr_e('Street and house number', 'angebot-deals'); ?>" value="<?php echo esc_attr($address1); ?>" required>
            </p>
            <p class="form-row form-row-first">
                <label for="angebot_reg_city"><?php esc_html_e('Town / City', 'angebot-deals'); ?>&nbsp;<span class="required">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="angebot_reg_city" id="angebot_reg_city" value="<?php echo esc_attr($city); ?>" required>
            </p>
            <p class="form-row form-row-last">
                <label for="angebot_reg_postcode"><?php esc_html_e('Postcode', 'angebot-deals'); ?>&nbsp;<span class="required">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="angebot_reg_postcode" id="angebot_reg_postcode" value="<?php echo esc_attr($postcode); ?>" required>
            </p>
            <p class="angebot-reg-note"><?php esc_html_e('We ask for these details because Highbridge membership is restricted to UK benefit recipients. After registering you will be asked to verify your eligibility.', 'angebot-deals'); ?></p>
        </fieldset>
        <?php
    }

    public static function validate_registration($errors, string $username, string $email)
    {
        if (!($errors instanceof WP_Error)) {
            $errors = new WP_Error();
        }

        $full_name = isset($_POST['angebot_reg_full_name']) ? trim(sanitize_text_field(wp_unslash($_POST['angebot_reg_full_name']))) : '';
        $dob       = isset($_POST['angebot_reg_dob']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_dob'])) : '';
        $phone     = isset($_POST['angebot_reg_phone']) ? trim(sanitize_text_field(wp_unslash($_POST['angebot_reg_phone']))) : '';
        $address1  = isset($_POST['angebot_reg_address_1']) ? trim(sanitize_text_field(wp_unslash($_POST['angebot_reg_address_1']))) : '';
        $city      = isset($_POST['angebot_reg_city']) ? trim(sanitize_text_field(wp_unslash($_POST['angebot_reg_city']))) : '';
        $postcode  = isset($_POST['angebot_reg_postcode']) ? trim(sanitize_text_field(wp_unslash($_POST['angebot_reg_postcode']))) : '';

        if ($full_name === '' || strlen($full_name) < 2) {
            $errors->add('angebot_full_name', __('Please enter your full name.', 'angebot-deals'));
        }

        $dob_time = DateTime::createFromFormat('Y-m-d', $dob);
        if ($dob === '' || !$dob_time || $dob_time->format('Y-m-d') !== $dob || $dob_time->getTimestamp() > time()) {
            $errors->add('angebot_dob', __('Please enter a valid date of birth.', 'angebot-deals'));
        }

        if ($phone === '' || !preg_match('/^[0-9+()\s-]{7,20}$/', $phone)) {
            $errors->add('angebot_phone', __('Please enter a valid phone number.', 'angebot-deals'));
        }

        if ($address1 === '') {
            $errors->add('angebot_address', __('Please enter your address.', 'angebot-deals'));
        }
        if ($city === '') {
            $errors->add('angebot_city', __('Please enter your town or city.', 'angebot-deals'));
        }
        if ($postcode === '') {
            $errors->add('angebot_postcode', __('Please enter your postcode.', 'angebot-deals'));
        }

        return $errors;
    }

    public static function save_registration_fields(int $customer_id): void
    {
        $full_name = isset($_POST['angebot_reg_full_name']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_full_name'])) : '';
        $dob       = isset($_POST['angebot_reg_dob']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_dob'])) : '';
        $phone     = isset($_POST['angebot_reg_phone']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_phone'])) : '';
        $address1  = isset($_POST['angebot_reg_address_1']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_address_1'])) : '';
        $city      = isset($_POST['angebot_reg_city']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_city'])) : '';
        $postcode  = isset($_POST['angebot_reg_postcode']) ? sanitize_text_field(wp_unslash($_POST['angebot_reg_postcode'])) : '';

        $parts      = preg_split('/\s+/', trim($full_name), 2);
        $first_name = $parts[0] ?? '';
        $last_name  = $parts[1] ?? '';

        update_user_meta($customer_id, 'first_name', $first_name);
        update_user_meta($customer_id, 'last_name', $last_name);
        update_user_meta($customer_id, 'billing_first_name', $first_name);
        update_user_meta($customer_id, 'billing_last_name', $last_name);
        update_user_meta($customer_id, 'billing_phone', $phone);
        update_user_meta($customer_id, 'billing_address_1', $address1);
        update_user_meta($customer_id, 'billing_city', $city);
        update_user_meta($customer_id, 'billing_postcode', $postcode);
        update_user_meta($customer_id, 'billing_country', 'GB');
        update_user_meta($customer_id, 'shipping_address_1', $address1);
        update_user_meta($customer_id, 'shipping_city', $city);
        update_user_meta($customer_id, 'shipping_postcode', $postcode);
        update_user_meta($customer_id, 'shipping_country', 'GB');

        // The date of birth is special category-adjacent (used to establish
        // identity for eligibility review) — keep it in its own meta key so
        // it is easy to find, export, and erase.
        update_user_meta($customer_id, '_angebot_date_of_birth', $dob);
        update_user_meta($customer_id, '_angebot_phone', $phone);
        update_user_meta($customer_id, '_angebot_full_name', sanitize_text_field($full_name));

        self::set_status($customer_id, self::STATUS_UNVERIFIED);
    }

    /* ---------------------------------------------------------------
     * Purchase gate
     * ------------------------------------------------------------- */

    public static function gate_add_to_cart(bool $passed, int $product_id): bool
    {
        if (!$passed) {
            return $passed;
        }

        if (get_post_meta($product_id, '_angebot_is_deal_product', true) !== 'yes') {
            return $passed;
        }

        return self::block_if_not_member();
    }

    public static function gate_cart_items(): void
    {
        if (!WC()->cart) {
            return;
        }

        $has_deal = false;
        foreach (WC()->cart->get_cart() as $item) {
            if (get_post_meta((int) $item['product_id'], '_angebot_is_deal_product', true) === 'yes') {
                $has_deal = true;
                break;
            }
        }

        if ($has_deal) {
            self::block_if_not_member();
        }
    }

    public static function gate_checkout(): void
    {
        if (!WC()->cart) {
            return;
        }

        foreach (WC()->cart->get_cart() as $item) {
            if (get_post_meta((int) $item['product_id'], '_angebot_is_deal_product', true) === 'yes') {
                if (!is_user_logged_in() || !self::can_purchase(get_current_user_id())) {
                    wc_add_notice(self::gate_message(), 'error');
                }
                break;
            }
        }
    }

    private static function block_if_not_member(): bool
    {
        if (!is_user_logged_in()) {
            wc_add_notice(
                sprintf(
                    /* translators: %s: login/account URL */
                    __('Please <a href="%s">log in or register</a> to buy Highbridge deals.', 'angebot-deals'),
                    esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url())
                ),
                'error'
            );
            return false;
        }

        if (!self::can_purchase(get_current_user_id())) {
            wc_add_notice(self::gate_message(), 'error');
            return false;
        }

        return true;
    }

    private static function gate_message(): string
    {
        $url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url(self::ENDPOINT) : '';
        return sprintf(
            /* translators: %s: membership page URL */
            __('Highbridge deals are only available to verified members. <a href="%s">Verify your eligibility</a> to unlock purchasing.', 'angebot-deals'),
            esc_url($url)
        );
    }

    /* ---------------------------------------------------------------
     * Privacy: export / erase
     * ------------------------------------------------------------- */

    public static function register_exporter(array $exporters): array
    {
        $exporters['angebot-membership'] = [
            'exporter_friendly_name' => __('Highbridge membership data', 'angebot-deals'),
            'callback'                => [self::class, 'export_data'],
        ];
        return $exporters;
    }

    public static function export_data(string $email, int $page = 1): array
    {
        $user = get_user_by('email', $email);
        if (!$user) {
            return ['data' => [], 'done' => true];
        }

        $status = self::get_status($user->ID);
        $items  = [[
            'group_id'    => 'angebot-membership',
            'group_label' => __('Highbridge membership', 'angebot-deals'),
            'item_id'     => 'membership-' . $user->ID,
            'data'        => [
                ['name' => __('Full name', 'angebot-deals'), 'value' => (string) get_user_meta($user->ID, '_angebot_full_name', true)],
                ['name' => __('Date of birth', 'angebot-deals'), 'value' => (string) get_user_meta($user->ID, '_angebot_date_of_birth', true)],
                ['name' => __('Phone number', 'angebot-deals'), 'value' => (string) get_user_meta($user->ID, '_angebot_phone', true)],
                ['name' => __('Address', 'angebot-deals'), 'value' => trim((string) get_user_meta($user->ID, 'billing_address_1', true) . ', ' . get_user_meta($user->ID, 'billing_city', true) . ' ' . get_user_meta($user->ID, 'billing_postcode', true))],
                ['name' => __('Membership status', 'angebot-deals'), 'value' => self::status_label($status)],
            ],
        ]];

        return ['data' => $items, 'done' => true];
    }

    public static function register_eraser(array $erasers): array
    {
        $erasers['angebot-membership'] = [
            'eraser_friendly_name' => __('Highbridge membership data', 'angebot-deals'),
            'callback'              => [self::class, 'erase_data'],
        ];
        return $erasers;
    }

    public static function erase_data(string $email, int $page = 1): array
    {
        $user = get_user_by('email', $email);
        if (!$user) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        foreach (['_angebot_full_name', '_angebot_date_of_birth', '_angebot_phone', '_angebot_membership_status'] as $key) {
            delete_user_meta($user->ID, $key);
        }

        return ['items_removed' => true, 'items_retained' => false, 'messages' => [], 'done' => true];
    }
}
