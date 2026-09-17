<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Autoloader
{
    private const MAP = [
        'Angebot_Deals_Activator'        => 'class-activator.php',
        'Angebot_Deals_Deactivator'      => 'class-deactivator.php',
        'Angebot_Deals_Plugin'           => 'class-plugin.php',
        'Angebot_Deals_Deal_CPT'         => 'class-deal-cpt.php',
        'Angebot_Deals_Deal_Meta'        => 'class-deal-meta.php',
        'Angebot_Deals_WooCommerce_Sync' => 'class-woocommerce-sync.php',
        'Angebot_Deals_Voucher'          => 'class-voucher.php',
        'Angebot_Deals_Voucher_Email'    => 'class-voucher-email.php',
        'Angebot_Deals_QR_Code'          => 'class-qr-code.php',
        'Angebot_Deals_Merchant_Role'    => 'class-merchant-role.php',
        'Angebot_Deals_Merchant_Portal'  => 'class-merchant-portal.php',
        'Angebot_Deals_Shortcodes'       => 'class-shortcodes.php',
        'Angebot_Deals_Ajax_Filters'     => 'class-ajax-filters.php',
        'Angebot_Deals_Reviews'          => 'class-reviews.php',
        'Angebot_Deals_Legal_Pages'      => 'class-legal-pages.php',
        'Angebot_Deals_Assets'           => 'class-assets.php',
        'Angebot_Deals_Location'         => 'class-location.php',
        'Angebot_Deals_Admin'            => 'class-admin.php',
        'Angebot_Deals_Demo_Content'     => 'class-demo-content.php',
        'Angebot_Deals_Membership'       => 'class-membership.php',
        'Angebot_Deals_Eligibility'      => 'class-eligibility.php',
        'Angebot_Deals_Impact'           => 'class-impact.php',
    ];

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (!isset(self::MAP[$class])) {
            return;
        }

        $file = ANGEBOT_DEALS_PATH . 'includes/' . self::MAP[$class];
        if (is_readable($file)) {
            require_once $file;
        }
    }
}
