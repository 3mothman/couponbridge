<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Merchant_Portal
{
    public static function register_hooks(): void
    {
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('wp_ajax_angebot_redeem_voucher', [self::class, 'ajax_redeem']);
        add_action('wp_ajax_angebot_lookup_voucher', [self::class, 'ajax_lookup']);
        add_shortcode('angebot_merchant_portal', [self::class, 'shortcode']);

        add_action('admin_post_angebot_submit_deal', [self::class, 'handle_submit_deal']);
        add_action('admin_post_nopriv_angebot_submit_deal', [self::class, 'handle_submit_deal']);
    }

    public static function admin_menu(): void
    {
        add_menu_page(
            __('Redeem vouchers', 'angebot-deals'),
            __('Vouchers', 'angebot-deals'),
            'angebot_redeem_voucher',
            'angebot-vouchers',
            [self::class, 'render_admin_page'],
            'dashicons-tickets-alt',
            6
        );
    }

    public static function render_admin_page(): void
    {
        if (!current_user_can('angebot_redeem_voucher')) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'));
        }

        $user_id  = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status   = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

        if ($is_admin) {
            global $wpdb;
            $table = Angebot_Deals_Voucher::table();
            $sql   = "SELECT * FROM {$table} WHERE 1=1";
            $params = [];
            if ($status) {
                $sql .= ' AND status = %s';
                $params[] = $status;
            }
            if ($search) {
                $sql .= ' AND (code LIKE %s OR customer_email LIKE %s)';
                $like = '%' . $wpdb->esc_like($search) . '%';
                $params[] = $like;
                $params[] = $like;
            }
            $sql .= ' ORDER BY created_at DESC LIMIT 100';
            $vouchers = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
        } else {
            $vouchers = Angebot_Deals_Voucher::get_for_merchant($user_id, [
                'search' => $search,
                'status' => $status,
                'limit'  => 100,
            ]);
        }

        include ANGEBOT_DEALS_PATH . 'admin/views/voucher-redeem.php';
    }

    public static function shortcode(): string
    {
        if (!is_user_logged_in() || !current_user_can('angebot_redeem_voucher')) {
            return '<p>' . esc_html__('Please log in as a merchant to redeem vouchers.', 'angebot-deals') . '</p>';
        }

        ob_start();
        include ANGEBOT_DEALS_PATH . 'templates/merchant-portal.php';
        return (string) ob_get_clean();
    }

    /* ---------------------------------------------------------------
     * Submit a deal (frontend)
     * ------------------------------------------------------------- */

    public static function render_submit_deal_form(): string
    {
        $categories = get_terms(['taxonomy' => Angebot_Deals_Deal_CPT::TAX_CATEGORY, 'hide_empty' => false]);
        $locations  = get_terms(['taxonomy' => Angebot_Deals_Deal_CPT::TAX_LOCATION, 'hide_empty' => false]);
        $notice     = self::consume_deal_notice();

        ob_start();
        include ANGEBOT_DEALS_PATH . 'templates/merchant-submit-deal.php';
        return (string) ob_get_clean();
    }

    public static function render_my_deals_list(): string
    {
        $deals = get_posts([
            'post_type'      => Angebot_Deals_Deal_CPT::POST_TYPE,
            'post_status'    => ['publish', 'pending', 'draft'],
            'meta_key'       => '_angebot_merchant_user_id',
            'meta_value'     => get_current_user_id(),
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        ob_start();
        include ANGEBOT_DEALS_PATH . 'templates/merchant-my-deals.php';
        return (string) ob_get_clean();
    }

    private static function consume_deal_notice(): array
    {
        $code = isset($_GET['angebot_deal_notice']) ? sanitize_key(wp_unslash($_GET['angebot_deal_notice'])) : '';
        $map  = [
            'submitted'    => ['type' => 'success', 'text' => __('Thanks! Your deal has been submitted and will go live after a quick review.', 'angebot-deals')],
            'error_fields' => ['type' => 'error', 'text' => __('Please fill in the title, description, both prices (deal price lower than original), quota, category and location.', 'angebot-deals')],
        ];
        return $map[$code] ?? [];
    }

    private static function redirect_deal_notice(string $notice): void
    {
        wp_safe_redirect(add_query_arg('angebot_deal_notice', $notice, wp_get_referer() ?: home_url('/')));
        exit;
    }

    public static function handle_submit_deal(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url());
            exit;
        }

        if (!current_user_can('angebot_submit_deal')) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'));
        }

        check_admin_referer('angebot_submit_deal', 'angebot_submit_deal_nonce');

        $title       = isset($_POST['deal_title']) ? sanitize_text_field(wp_unslash($_POST['deal_title'])) : '';
        $content     = isset($_POST['deal_description']) ? wp_kses_post(wp_unslash($_POST['deal_description'])) : '';
        $excerpt     = isset($_POST['deal_short_description']) ? sanitize_textarea_field(wp_unslash($_POST['deal_short_description'])) : '';
        $category_id = absint($_POST['deal_category'] ?? 0);
        $location_id = absint($_POST['deal_location'] ?? 0);
        $original    = isset($_POST['original_price']) ? (float) $_POST['original_price'] : 0.0;
        $price       = isset($_POST['deal_price']) ? (float) $_POST['deal_price'] : 0.0;
        $quantity    = absint($_POST['quantity'] ?? 0);
        $expires     = isset($_POST['voucher_expires']) ? sanitize_text_field(wp_unslash($_POST['voucher_expires'])) : '';
        $highlights  = isset($_POST['highlights']) ? sanitize_textarea_field(wp_unslash($_POST['highlights'])) : '';
        $fine_print  = isset($_POST['fine_print']) ? sanitize_textarea_field(wp_unslash($_POST['fine_print'])) : '';

        $valid_category = $category_id && !is_wp_error(get_term($category_id, Angebot_Deals_Deal_CPT::TAX_CATEGORY));
        $valid_location  = $location_id && !is_wp_error(get_term($location_id, Angebot_Deals_Deal_CPT::TAX_LOCATION));

        if ($title === '' || $content === '' || $original <= 0 || $price <= 0 || $price >= $original
            || $quantity < 1 || !$valid_category || !$valid_location
        ) {
            self::redirect_deal_notice('error_fields');
        }

        $user_id  = get_current_user_id();
        $discount = (int) round((($original - $price) / $original) * 100);

        $post_id = wp_insert_post([
            'post_type'    => Angebot_Deals_Deal_CPT::POST_TYPE,
            // Merchant submissions always start as a review queue; an admin
            // publishes them from wp-admin like any other pending post.
            // Admins using this same form get published immediately.
            'post_status'  => current_user_can('manage_options') ? 'publish' : 'pending',
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_author'  => $user_id,
            'meta_input'   => [
                '_angebot_original_price'   => $original,
                '_angebot_deal_price'       => $price,
                '_angebot_discount_percent' => max(0, min(100, $discount)),
                '_angebot_merchant_name'    => wp_get_current_user()->display_name,
                '_angebot_merchant_user_id' => $user_id,
                '_angebot_voucher_expires'  => $expires,
                '_angebot_quantity'         => $quantity,
                '_angebot_sold_count'       => 0,
                '_angebot_highlights'       => $highlights,
                '_angebot_fine_print'       => $fine_print,
                '_angebot_is_featured'      => 0,
            ],
        ], true);

        if (is_wp_error($post_id) || !$post_id) {
            self::redirect_deal_notice('error_fields');
        }

        $post_id = (int) $post_id;

        wp_set_object_terms($post_id, [$category_id], Angebot_Deals_Deal_CPT::TAX_CATEGORY, false);
        wp_set_object_terms($post_id, [$location_id], Angebot_Deals_Deal_CPT::TAX_LOCATION, false);

        $location_term = get_term($location_id, Angebot_Deals_Deal_CPT::TAX_LOCATION);
        if ($location_term && !is_wp_error($location_term)) {
            update_post_meta($post_id, '_angebot_location_label', $location_term->name);
        }

        if (!empty($_FILES['deal_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $attachment_id = media_handle_upload('deal_image', $post_id);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        if (get_post_status($post_id) !== 'publish') {
            self::notify_admin_new_deal($post_id);
        }
        self::notify_merchant_deal_submitted($user_id, $post_id);

        self::redirect_deal_notice('submitted');
    }

    private static function notify_admin_new_deal(int $post_id): void
    {
        $to      = (string) get_option('admin_email');
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] New deal submitted for review', 'angebot-deals'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );
        $body = sprintf(
            /* translators: 1: deal title, 2: edit URL */
            __("A merchant submitted a new deal: \"%1\$s\".\n\nReview and publish it here: %2\$s", 'angebot-deals'),
            get_the_title($post_id),
            admin_url('post.php?post=' . $post_id . '&action=edit')
        );
        wp_mail($to, $subject, $body);
    }

    private static function notify_merchant_deal_submitted(int $user_id, int $post_id): void
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        $subject = sprintf(
            __('[%s] Your deal was submitted', 'angebot-deals'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );
        $body = get_post_status($post_id) === 'publish'
            ? sprintf(__('"%s" is now live.', 'angebot-deals'), get_the_title($post_id))
            : sprintf(__('Thanks — "%s" has been submitted and will go live after a quick review.', 'angebot-deals'), get_the_title($post_id));
        wp_mail($user->user_email, $subject, $body);
    }

    public static function ajax_lookup(): void
    {
        check_ajax_referer('angebot_merchant', 'nonce');

        if (!current_user_can('angebot_redeem_voucher')) {
            wp_send_json_error(['message' => __('Permission denied.', 'angebot-deals')], 403);
        }

        $code  = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';

        $voucher = $token
            ? Angebot_Deals_Voucher::get_by_token($token)
            : Angebot_Deals_Voucher::get_by_code($code);

        if (!$voucher) {
            wp_send_json_error(['message' => __('Voucher not found.', 'angebot-deals')]);
        }

        $voucher = Angebot_Deals_Voucher::refresh_status($voucher);

        if (!current_user_can('manage_options') && (int) $voucher->merchant_user_id !== get_current_user_id()) {
            wp_send_json_error(['message' => __('This voucher does not belong to your deals.', 'angebot-deals')]);
        }

        wp_send_json_success([
            'id'            => (int) $voucher->id,
            'code'          => $voucher->code,
            'status'        => $voucher->status,
            'status_label'  => Angebot_Deals_Voucher::status_label($voucher->status),
            'deal_title'    => get_the_title((int) $voucher->deal_id),
            'customer'      => $voucher->customer_name ?: $voucher->customer_email,
            'expires_at'    => $voucher->expires_at ? date_i18n('d.m.Y', strtotime($voucher->expires_at)) : '',
            'can_redeem'    => $voucher->status === Angebot_Deals_Voucher::STATUS_ACTIVE,
        ]);
    }

    public static function ajax_redeem(): void
    {
        check_ajax_referer('angebot_merchant', 'nonce');

        if (!current_user_can('angebot_redeem_voucher')) {
            wp_send_json_error(['message' => __('Permission denied.', 'angebot-deals')], 403);
        }

        $id = absint($_POST['voucher_id'] ?? 0);
        $result = Angebot_Deals_Voucher::redeem($id, get_current_user_id());

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        wp_send_json_success(['message' => $result['message']]);
    }
}
