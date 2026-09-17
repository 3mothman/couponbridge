<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Activator
{
    public static function activate(): void
    {
        self::create_voucher_table();
        self::create_reviews_table();
        self::create_eligibility_table();
        Angebot_Deals_Merchant_Role::register_role();
        Angebot_Deals_Eligibility::grant_admin_capability();
        Angebot_Deals_Deal_CPT::register();
        Angebot_Deals_Legal_Pages::maybe_create_pages();
        Angebot_Deals_Impact::maybe_create_page();
        add_option('angebot_brand_name', 'Highbridge');
        add_option('angebot_eligibility_retention_days', 90);
        // New WooCommerce My Account endpoints (membership, my-deals) need
        // a rewrite flush or their URLs 404 until Permalinks is re-saved.
        flush_rewrite_rules();
        update_option('angebot_deals_version', ANGEBOT_DEALS_VERSION);
    }

    public static function create_voucher_table(): void
    {
        global $wpdb;

        $table   = $wpdb->prefix . 'angebot_vouchers';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(64) NOT NULL,
            deal_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_email VARCHAR(191) NOT NULL,
            customer_name VARCHAR(191) NOT NULL DEFAULT '',
            merchant_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            expires_at DATETIME NULL,
            redeemed_at DATETIME NULL,
            redeemed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            qr_token VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            UNIQUE KEY qr_token (qr_token),
            KEY deal_id (deal_id),
            KEY order_id (order_id),
            KEY status (status),
            KEY merchant_user_id (merchant_user_id),
            KEY customer_email (customer_email),
            KEY user_id (user_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function create_reviews_table(): void
    {
        global $wpdb;

        $table   = $wpdb->prefix . 'angebot_reviews';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            deal_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            author_name VARCHAR(191) NOT NULL,
            author_email VARCHAR(191) NOT NULL DEFAULT '',
            rating TINYINT UNSIGNED NOT NULL,
            title VARCHAR(191) NOT NULL DEFAULT '',
            content TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY deal_id (deal_id),
            KEY status (status),
            KEY rating (rating)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function create_eligibility_table(): void
    {
        global $wpdb;

        $table   = $wpdb->prefix . 'angebot_eligibility';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            benefit_type VARCHAR(40) NOT NULL,
            proof_type VARCHAR(60) NOT NULL,
            file_path VARCHAR(255) NOT NULL DEFAULT '',
            file_name VARCHAR(191) NOT NULL DEFAULT '',
            file_mime VARCHAR(100) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            admin_note TEXT NULL,
            consent_given TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            consent_at DATETIME NULL,
            reviewed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
