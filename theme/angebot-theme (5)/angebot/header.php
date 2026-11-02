<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e('Skip to content', 'angebot'); ?></a>

<header class="site-header">
    <div class="site-header__top">
        <div class="container site-header__top-inner">
            <div class="site-branding">
                <?php if (has_custom_logo()) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <a class="site-logo-text" href="<?php echo esc_url(home_url('/')); ?>">
                        <img class="site-logo-image" src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/couponbridge.svg'); ?>" alt="<?php echo esc_attr(angebot_brand_name()); ?>" width="64" height="64">
                        <span class="site-logo-name"><?php echo esc_html(angebot_brand_name()); ?></span>
                    </a>
                <?php endif; ?>
            </div>

            <form class="site-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <label class="screen-reader-text" for="angebot-search"><?php esc_html_e('Search deals', 'angebot'); ?></label>
                <input id="angebot-search" type="search" name="s" placeholder="<?php esc_attr_e('What are you looking for?', 'angebot'); ?>" value="<?php echo esc_attr(get_search_query()); ?>">
                <input type="hidden" name="post_type" value="deal">
                <button type="submit" aria-label="<?php esc_attr_e('Search', 'angebot'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </form>

            <div class="site-header__actions">
                <?php
                if (shortcode_exists('angebot_location_picker')) {
                    echo do_shortcode('[angebot_location_picker]');
                }
                ?>
                <?php if (function_exists('wc_get_page_permalink')) : ?>
                    <a class="header-link" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
                        <?php echo is_user_logged_in() ? esc_html__('Account', 'angebot') : esc_html__('Login', 'angebot'); ?>
                    </a>
                    <a class="header-cart" href="<?php echo esc_url(wc_get_cart_url()); ?>">
                        <?php esc_html_e('Cart', 'angebot'); ?>
                        <span class="header-cart__count"><?php echo esc_html((string) angebot_cart_count()); ?></span>
                    </a>
                <?php endif; ?>
                <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="primary-nav" aria-label="<?php esc_attr_e('Menu', 'angebot'); ?>">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </div>

    <div class="site-header__nav" id="primary-nav">
        <div class="container">
            <?php
            // Always show the full category bar (Home + All + every category)
            // as the single navigation row. A custom "Appearance -> Menus"
            // entry at the 'primary' location is intentionally NOT used here
            // anymore, so you don't end up with two different navs.
            if (shortcode_exists('angebot_categories')) {
                echo do_shortcode('[angebot_categories]');
            }
            ?>
        </div>
    </div>

</header>

<main id="main" class="site-main">
