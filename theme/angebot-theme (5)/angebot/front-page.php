<?php
declare(strict_types=1);

get_header();
?>

<section class="hero">
    <div class="hero__atmosphere" aria-hidden="true"></div>
    <div class="container hero__content">
        <p class="hero__brand"><?php echo esc_html(angebot_brand_name()); ?></p>
        <h1 class="hero__title"><?php esc_html_e('Local deals & coupons', 'angebot'); ?></h1>
        <p class="hero__lead"><?php esc_html_e('Save on dining, wellness, leisure and more — local coupons and vouchers with real discounts.', 'angebot'); ?></p>
        <div class="hero__cta">
            <a class="angebot-btn angebot-btn--primary" href="<?php echo esc_url(get_post_type_archive_link('deal')); ?>">
                <?php esc_html_e('Discover deals', 'angebot'); ?>
            </a>
            <?php if (shortcode_exists('angebot_location_picker')) : ?>
                <div class="hero__location"><?php echo do_shortcode('[angebot_location_picker]'); ?></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="home-section container">
    <header class="section-header">
        <h2><?php esc_html_e('Highlights', 'angebot'); ?></h2>
        <p><?php esc_html_e('Hand-picked deals with especially strong discounts.', 'angebot'); ?></p>
    </header>
    <?php echo do_shortcode('[angebot_featured limit="4"]'); ?>
</section>

<section class="home-section container">
    <header class="section-header">
        <h2><?php printf(esc_html__('Popular in %s', 'angebot'), esc_html(class_exists('Angebot_Deals_Location') ? Angebot_Deals_Location::current_label() : '')); ?></h2>
        <p><?php esc_html_e('Current deals in your selected city.', 'angebot'); ?></p>
    </header>
    <?php echo do_shortcode('[deals_grid limit="9" columns="3" show_filters="1"]'); ?>
</section>

<section class="home-howto">
    <div class="container">
        <header class="section-header section-header--center">
            <h2><?php esc_html_e('How it works', 'angebot'); ?></h2>
        </header>
        <ol class="howto-steps">
            <li>
                <strong><?php esc_html_e('Choose a deal', 'angebot'); ?></strong>
                <span><?php esc_html_e('Filter by city & category, then pick your deal.', 'angebot'); ?></span>
            </li>
            <li>
                <strong><?php esc_html_e('Pay securely', 'angebot'); ?></strong>
                <span><?php esc_html_e('Checkout via WooCommerce — Stripe or PayPal.', 'angebot'); ?></span>
            </li>
            <li>
                <strong><?php esc_html_e('Redeem', 'angebot'); ?></strong>
                <span><?php esc_html_e('Show your QR code or text code at the merchant.', 'angebot'); ?></span>
            </li>
        </ol>
    </div>
</section>

<?php
get_footer();
