<?php
/**
 * Plugin Name:       Angebot Deals
 * Plugin URI:        https://angebot.local
 * Description:       Deals platform with WooCommerce checkout, vouchers, merchant portal, and QR redemption.
 * Version:           1.4.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Angebot
 * Text Domain:       angebot-deals
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ANGEBOT_DEALS_VERSION', '1.4.1');
define('ANGEBOT_DEALS_FILE', __FILE__);
define('ANGEBOT_DEALS_PATH', plugin_dir_path(__FILE__));
define('ANGEBOT_DEALS_URL', plugin_dir_url(__FILE__));
define('ANGEBOT_DEALS_BASENAME', plugin_basename(__FILE__));

require_once ANGEBOT_DEALS_PATH . 'includes/class-autoloader.php';
Angebot_Deals_Autoloader::register();

register_activation_hook(__FILE__, ['Angebot_Deals_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Angebot_Deals_Deactivator', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Angebot Deals requires WooCommerce. Please install and activate WooCommerce.', 'angebot-deals');
            echo '</p></div>';
        });
        return;
    }

    Angebot_Deals_Plugin::instance()->init();
});
