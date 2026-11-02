<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ANGEBOT_THEME_VERSION', '1.1.3');

add_action('after_setup_theme', static function (): void {
    load_theme_textdomain('angebot', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('woocommerce');
    add_theme_support('custom-logo', [
        'height'      => 48,
        'width'       => 180,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    register_nav_menus([
        'primary' => __('Primary Menu', 'angebot'),
        'footer'  => __('Footer Menu', 'angebot'),
        'legal'   => __('Legal Menu', 'angebot'),
    ]);

    add_image_size('angebot-deal', 640, 400, true);
});

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'angebot-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap',
        [],
        null
    );

    wp_enqueue_style(
        'angebot-main',
        get_template_directory_uri() . '/assets/css/main.css',
        ['angebot-fonts'],
        ANGEBOT_THEME_VERSION
    );

    wp_enqueue_script(
        'angebot-main',
        get_template_directory_uri() . '/assets/js/main.js',
        [],
        ANGEBOT_THEME_VERSION,
        true
    );
});

add_filter('body_class', static function (array $classes): array {
    $classes[] = 'angebot-theme';
    return $classes;
});

/**
 * Brand name helper — never hardcode a third-party brand.
 */
function angebot_brand_name(): string
{
    $name = (string) get_option('angebot_brand_name', '');
    if ($name !== '') {
        return $name;
    }
    // Deliberately does NOT fall back to the WordPress site title
    // (get_bloginfo('name')) — on a fresh Hostinger install that's the
    // auto-generated subdomain (e.g. "lavenderblush-bat-933834...."),
    // which is far too long to use as a logo/header label.
    return 'CouponBridge';
}

function angebot_cart_count(): int
{
    if (!function_exists('WC') || !WC()->cart) {
        return 0;
    }
    return (int) WC()->cart->get_cart_contents_count();
}

require_once get_template_directory() . '/inc/template-tags.php';
