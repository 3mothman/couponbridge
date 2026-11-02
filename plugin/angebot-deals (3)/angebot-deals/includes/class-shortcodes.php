<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Shortcodes
{
    public static function register_hooks(): void
    {
        add_shortcode('deals_grid', [self::class, 'deals_grid']);
        add_shortcode('angebot_deals', [self::class, 'deals_grid']);
        add_shortcode('angebot_filters', [self::class, 'filters']);
        add_shortcode('angebot_location_picker', [self::class, 'location_picker']);
        add_shortcode('angebot_featured', [self::class, 'featured']);
        add_shortcode('angebot_categories', [self::class, 'categories_bar']);
    }

    public static function deals_grid($atts): string
    {
        $atts = shortcode_atts([
            'category' => '',
            'location' => '',
            'limit'    => '24',
            'featured' => '',
            'columns'  => '3',
            'orderby'  => 'date',
            'show_filters' => '0',
        ], $atts, 'deals_grid');

        $query = self::query_deals($atts);

        ob_start();
        if ($atts['show_filters'] === '1') {
            echo self::filters([]);
        }
        echo '<div class="angebot-deals-grid" data-columns="' . esc_attr($atts['columns']) . '" id="angebot-deals-grid">';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                include ANGEBOT_DEALS_PATH . 'templates/deal-card.php';
            }
            wp_reset_postdata();
        } else {
            echo '<p class="angebot-empty">' . esc_html__('No deals found for this selection.', 'angebot-deals') . '</p>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function featured($atts): string
    {
        $atts = shortcode_atts(['limit' => '4'], $atts, 'angebot_featured');
        return self::deals_grid([
            'featured' => '1',
            'limit'    => $atts['limit'],
            'columns'  => '4',
        ]);
    }

    public static function filters($atts): string
    {
        $categories = get_terms([
            'taxonomy'   => Angebot_Deals_Deal_CPT::TAX_CATEGORY,
            'hide_empty' => false,
        ]);
        $locations = get_terms([
            'taxonomy'   => Angebot_Deals_Deal_CPT::TAX_LOCATION,
            'hide_empty' => false,
        ]);

        ob_start();
        include ANGEBOT_DEALS_PATH . 'templates/filters.php';
        return (string) ob_get_clean();
    }

    public static function location_picker($atts): string
    {
        $locations = get_terms([
            'taxonomy'   => Angebot_Deals_Deal_CPT::TAX_LOCATION,
            'hide_empty' => false,
        ]);
        $current = Angebot_Deals_Location::current();

        ob_start();
        ?>
        <div class="angebot-location-picker">
            <button type="button" class="angebot-location-trigger" aria-expanded="false" aria-haspopup="listbox">
                <span class="angebot-loc-icon" aria-hidden="true"></span>
                <span class="angebot-loc-label"><?php echo esc_html(Angebot_Deals_Location::current_label()); ?></span>
            </button>
            <div class="angebot-location-dropdown" hidden>
                <input type="search" class="angebot-location-search" placeholder="<?php esc_attr_e('Search city…', 'angebot-deals'); ?>">
                <ul role="listbox">
                    <?php if (!is_wp_error($locations)) : foreach ($locations as $loc) : ?>
                        <li>
                            <button type="button" data-location="<?php echo esc_attr($loc->slug); ?>" <?php echo $loc->slug === $current ? 'aria-current="true"' : ''; ?>>
                                <?php echo esc_html($loc->name); ?>
                            </button>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function categories_bar($atts): string
    {
        $terms = get_terms([
            'taxonomy'   => Angebot_Deals_Deal_CPT::TAX_CATEGORY,
            'hide_empty' => false,
            'number'     => 12,
        ]);

        if (is_wp_error($terms) || !$terms) {
            return '';
        }

        ob_start();
        echo '<nav class="angebot-category-bar" aria-label="' . esc_attr__('Categories', 'angebot-deals') . '"><ul>';
        echo '<li><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Home', 'angebot-deals') . '</a></li>';
        echo '<li><a href="' . esc_url(get_post_type_archive_link(Angebot_Deals_Deal_CPT::POST_TYPE)) . '">' . esc_html__('All', 'angebot-deals') . '</a></li>';
        foreach ($terms as $term) {
            printf(
                '<li><a href="%s">%s</a></li>',
                esc_url(get_term_link($term)),
                esc_html($term->name)
            );
        }
        echo '</ul></nav>';
        return (string) ob_get_clean();
    }

    public static function query_deals(array $atts): WP_Query
    {
        $args = [
            'post_type'      => Angebot_Deals_Deal_CPT::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => absint($atts['limit'] ?? 24),
            'orderby'        => sanitize_key($atts['orderby'] ?? 'date'),
            'order'          => 'DESC',
        ];

        $tax_query = [];

        $category = $atts['category'] ?? '';
        if ($category === '' && !empty($_GET['deal_category'])) {
            $category = sanitize_title(wp_unslash($_GET['deal_category']));
        }
        if ($category !== '') {
            $tax_query[] = [
                'taxonomy' => Angebot_Deals_Deal_CPT::TAX_CATEGORY,
                'field'    => 'slug',
                'terms'    => $category,
            ];
        }

        $location = $atts['location'] ?? '';
        if ($location === '' && !empty($_GET['deal_location'])) {
            $location = sanitize_title(wp_unslash($_GET['deal_location']));
        }
        if ($location === '') {
            $location = Angebot_Deals_Location::current();
        }
        // "All" means all four supported service cities.
        if ($location !== 'all') {
            $location = Angebot_Deals_Location::sanitize_default($location);
        }
        if ($location !== '') {
            $tax_query[] = [
                'taxonomy' => Angebot_Deals_Deal_CPT::TAX_LOCATION,
                'field'    => 'slug',
                'terms'    => $location === 'all' ? array_keys(Angebot_Deals_Location::CITIES) : $location,
            ];
        }

        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }

        $meta_query = [];
        if (!empty($atts['featured'])) {
            $meta_query[] = [
                'key'   => '_angebot_is_featured',
                'value' => '1',
            ];
        }

        $min_price = isset($_GET['min_price']) ? (float) $_GET['min_price'] : null;
        $max_price = isset($_GET['max_price']) ? (float) $_GET['max_price'] : null;
        if ($min_price !== null && $min_price > 0) {
            $meta_query[] = [
                'key'     => '_angebot_deal_price',
                'value'   => $min_price,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ];
        }
        if ($max_price !== null && $max_price > 0) {
            $meta_query[] = [
                'key'     => '_angebot_deal_price',
                'value'   => $max_price,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ];
        }

        if ($meta_query) {
            $args['meta_query'] = $meta_query;
        }

        if (($atts['orderby'] ?? '') === 'price') {
            $args['meta_key'] = '_angebot_deal_price';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = !empty($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
        }

        return new WP_Query($args);
    }
}
