</main>

<footer class="site-footer">
    <div class="container site-footer__grid">
        <div class="site-footer__brand">
            <p class="site-footer__logo">
                <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/couponbridge.svg'); ?>" alt="<?php echo esc_attr(angebot_brand_name()); ?>" width="28" height="28">
                <?php echo esc_html(angebot_brand_name()); ?>
            </p>
            <p><?php esc_html_e('Local deals, real experiences — vouchers for restaurants, wellness, leisure and more in your city.', 'angebot'); ?></p>
        </div>

        <div>
            <h3><?php esc_html_e('Discover', 'angebot'); ?></h3>
            <?php
            wp_nav_menu([
                'theme_location' => 'footer',
                'container'      => false,
                'menu_class'     => 'footer-menu',
                'fallback_cb'    => static function (): void {
                    echo '<ul class="footer-menu">';
                    echo '<li><a href="' . esc_url(get_post_type_archive_link('deal')) . '">' . esc_html__('All Deals', 'angebot') . '</a></li>';
                    echo '</ul>';
                },
            ]);
            ?>
        </div>

        <div>
            <h3><?php esc_html_e('Popular Cities', 'angebot'); ?></h3>
            <ul class="footer-menu footer-cities">
                <?php
                $cities = get_terms([
                    'taxonomy'   => 'deal_location',
                    'hide_empty' => false,
                    'number'     => 10,
                ]);
                if (!is_wp_error($cities)) {
                    foreach ($cities as $city) {
                        printf(
                            '<li><a href="%s">%s</a></li>',
                            esc_url(get_term_link($city)),
                            esc_html($city->name)
                        );
                    }
                }
                ?>
            </ul>
        </div>

        <div>
            <h3><?php esc_html_e('Legal', 'angebot'); ?></h3>
            <ul class="footer-menu">
                <?php
                $legal_slugs = ['imprint', 'terms', 'cancellation', 'privacy'];
                foreach ($legal_slugs as $slug) {
                    $page = get_page_by_path($slug);
                    if ($page) {
                        printf(
                            '<li><a href="%s">%s</a></li>',
                            esc_url(get_permalink($page)),
                            esc_html(get_the_title($page))
                        );
                    }
                }
                ?>
            </ul>
        </div>
    </div>
    <div class="site-footer__bottom">
        <div class="container">
            <p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(angebot_brand_name()); ?>. <?php esc_html_e('All rights reserved.', 'angebot'); ?></p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
