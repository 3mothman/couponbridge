<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Merchant_Role
{
    public const ROLE = 'angebot_merchant';

    public static function register_hooks(): void
    {
        add_action('init', [self::class, 'register_role']);
        add_filter('map_meta_cap', [self::class, 'map_meta_cap'], 10, 4);
        add_action('pre_get_posts', [self::class, 'restrict_deal_list']);
        add_action('admin_menu', [self::class, 'restrict_admin_menu'], 999);
    }

    public static function restrict_admin_menu(): void
    {
        $user = wp_get_current_user();
        if (!in_array(self::ROLE, (array) $user->roles, true) || user_can($user, 'manage_options')) {
            return;
        }

        // Merchants: keep Deals + Vouchers + Profile, hide the rest.
        remove_menu_page('index.php');
        remove_menu_page('edit.php');
        remove_menu_page('upload.php');
        remove_menu_page('edit.php?post_type=page');
        remove_menu_page('edit-comments.php');
        remove_menu_page('themes.php');
        remove_menu_page('plugins.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('woocommerce');
        remove_menu_page('wc-admin');
    }

    public static function register_role(): void
    {
        $caps = [
            'read'                   => true,
            'edit_posts'             => true,
            'edit_published_posts'   => true,
            'publish_posts'          => false,
            'delete_posts'           => false,
            'upload_files'           => true,
            'angebot_redeem_voucher' => true,
            'angebot_view_own_deals' => true,
            'angebot_submit_deal'    => true,
        ];

        $role = get_role(self::ROLE);
        if (!$role) {
            add_role(self::ROLE, __('Merchant', 'angebot-deals'), $caps);
        } else {
            foreach ($caps as $cap => $grant) {
                if ($grant) {
                    $role->add_cap($cap);
                } else {
                    $role->remove_cap($cap);
                }
            }
        }

        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('angebot_redeem_voucher');
            $admin->add_cap('angebot_view_own_deals');
            $admin->add_cap('angebot_submit_deal');
        }
    }

    public static function map_meta_cap(array $caps, string $cap, int $user_id, array $args): array
    {
        if (!in_array($cap, ['edit_post', 'delete_post', 'read_post'], true)) {
            return $caps;
        }

        $post = isset($args[0]) ? get_post((int) $args[0]) : null;
        if (!$post || $post->post_type !== Angebot_Deals_Deal_CPT::POST_TYPE) {
            return $caps;
        }

        if (user_can($user_id, 'manage_options')) {
            return $caps;
        }

        $user = get_userdata($user_id);
        if (!$user || !in_array(self::ROLE, (array) $user->roles, true)) {
            return $caps;
        }

        $merchant_id = (int) Angebot_Deals_Deal_Meta::get($post->ID, 'merchant_user_id', 0);
        $is_owner    = $merchant_id === $user_id || (int) $post->post_author === $user_id;

        if ($cap === 'delete_post') {
            return ['do_not_allow'];
        }

        if (!$is_owner) {
            return ['do_not_allow'];
        }

        return ['exist'];
    }

    public static function restrict_deal_list(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== Angebot_Deals_Deal_CPT::POST_TYPE) {
            return;
        }

        $user_id = get_current_user_id();
        if (user_can($user_id, 'manage_options')) {
            return;
        }

        $user = wp_get_current_user();
        if (!in_array(self::ROLE, (array) $user->roles, true)) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = [
            'key'   => '_angebot_merchant_user_id',
            'value' => $user_id,
        ];
        $query->set('meta_query', $meta_query);
    }
}
