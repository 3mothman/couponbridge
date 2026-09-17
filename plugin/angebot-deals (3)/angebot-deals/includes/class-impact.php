<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Impact dashboard: aggregate, privacy-safe numbers only (no names, no
 * eligibility categories) — how many people the scheme has helped so far.
 */
final class Angebot_Deals_Impact
{
    public static function register_hooks(): void
    {
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_shortcode('angebot_impact', [self::class, 'shortcode']);
    }

    public static function admin_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=deal',
            __('Impact dashboard', 'angebot-deals'),
            __('Impact', 'angebot-deals'),
            'manage_options',
            'angebot-impact',
            [self::class, 'render_admin']
        );
    }

    public static function render_admin(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'));
        }

        $stats = self::get_stats();
        include ANGEBOT_DEALS_PATH . 'admin/views/impact.php';
    }

    public static function shortcode(): string
    {
        $stats = self::get_stats();
        ob_start();
        include ANGEBOT_DEALS_PATH . 'templates/impact.php';
        return (string) ob_get_clean();
    }

    public static function maybe_create_page(): void
    {
        if (get_page_by_path('impact')) {
            return;
        }

        wp_insert_post([
            'post_title'   => __('Our Impact', 'angebot-deals'),
            'post_name'    => 'impact',
            'post_content' => '<!-- wp:shortcode -->[angebot_impact]<!-- /wp:shortcode -->',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
    }

    /* ---------------------------------------------------------------
     * Aggregation
     * ------------------------------------------------------------- */

    public static function get_stats(): array
    {
        global $wpdb;
        $vouchers = Angebot_Deals_Voucher::table();

        $verified_members = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
            '_angebot_membership_status',
            Angebot_Deals_Membership::STATUS_VERIFIED
        ));

        $redeemed_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$vouchers} WHERE status = %s",
            Angebot_Deals_Voucher::STATUS_REDEEMED
        ));

        $active_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$vouchers} WHERE status = %s",
            Angebot_Deals_Voucher::STATUS_ACTIVE
        ));

        $people_helped = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT IF(user_id > 0, CONCAT('u', user_id), customer_email)) FROM {$vouchers} WHERE status = %s",
            Angebot_Deals_Voucher::STATUS_REDEEMED
        ));

        $savings_row = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(GREATEST(COALESCE(pm1.meta_value, 0) - COALESCE(pm2.meta_value, 0), 0)) AS savings
             FROM {$vouchers} v
             LEFT JOIN {$wpdb->postmeta} pm1 ON pm1.post_id = v.deal_id AND pm1.meta_key = '_angebot_original_price'
             LEFT JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = v.deal_id AND pm2.meta_key = '_angebot_deal_price'
             WHERE v.status = %s",
            Angebot_Deals_Voucher::STATUS_REDEEMED
        ));
        $total_savings = $savings_row ? (float) $savings_row->savings : 0.0;

        $monthly = self::monthly_redemptions();

        return [
            'verified_members' => $verified_members,
            'redeemed_count'   => $redeemed_count,
            'active_count'     => $active_count,
            'people_helped'    => $people_helped,
            'total_savings'    => $total_savings,
            'monthly'          => $monthly,
        ];
    }

    private static function monthly_redemptions(int $months = 6): array
    {
        global $wpdb;
        $vouchers = Angebot_Deals_Voucher::table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(redeemed_at, '%%Y-%%m') AS ym, COUNT(*) AS c
             FROM {$vouchers}
             WHERE status = %s AND redeemed_at IS NOT NULL AND redeemed_at >= %s
             GROUP BY ym ORDER BY ym ASC",
            Angebot_Deals_Voucher::STATUS_REDEEMED,
            gmdate('Y-m-01 00:00:00', strtotime('-' . ($months - 1) . ' months'))
        ), ARRAY_A);

        $by_month = [];
        foreach ($rows as $row) {
            $by_month[$row['ym']] = (int) $row['c'];
        }

        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts    = strtotime('-' . $i . ' months');
            $key   = gmdate('Y-m', $ts);
            $out[] = [
                'label' => date_i18n('M Y', $ts),
                'count' => $by_month[$key] ?? 0,
            ];
        }

        return $out;
    }
}
