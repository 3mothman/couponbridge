<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional demo deals — WP-CLI: wp eval-file wp-content/plugins/angebot-deals/includes/class-demo-content.php
 * Or trigger via Admin → Deals → Settings (button).
 */
final class Angebot_Deals_Demo_Content
{
    public static function seed(): array
    {
        if (!taxonomy_exists(Angebot_Deals_Deal_CPT::TAX_LOCATION)) {
            Angebot_Deals_Deal_CPT::register();
        }
        Angebot_Deals_Location::seed_default_locations();

        $samples = [
            [
                'title'    => '3-Course Menu for 2',
                'merchant' => 'Coventry Bistro',
                'location' => 'coventry',
                'category' => 'food-drink',
                'original' => 79.00,
                'deal'     => 39.00,
                'qty'      => 50,
                'featured' => 1,
                'excerpt'  => 'Candlelight dinner including a welcome drink.',
                'content'  => '<p>Enjoy a 3-course menu from the seasonal menu. Ideal for couples and special occasions.</p>',
            ],
            [
                'title'    => '60 Min. Classic Massage',
                'merchant' => 'Nuneaton Spa',
                'location' => 'nuneaton',
                'category' => 'beauty-wellness',
                'original' => 65.00,
                'deal'     => 29.90,
                'qty'      => 40,
                'featured' => 1,
                'excerpt'  => 'Relaxation from neck to toes.',
                'content'  => '<p>Professional full-body massage with premium oils. Appointment required after purchase.</p>',
            ],
            [
                'title'    => 'Indoor Trampoline 2 Hrs',
                'merchant' => 'Rugby Jump Arena',
                'location' => 'rugby',
                'category' => 'leisure',
                'original' => 28.00,
                'deal'     => 14.00,
                'qty'      => 100,
                'featured' => 0,
                'excerpt'  => 'Jumping, soft play & foam pit included.',
                'content'  => '<p>Two hours of fun for kids and adults. Socks required.</p>',
            ],
            [
                'title'    => 'Birmingham City Tour',
                'merchant' => 'Birmingham City Guides',
                'location' => 'birmingham',
                'category' => 'activities',
                'original' => 25.00,
                'deal'     => 12.50,
                'qty'      => 80,
                'featured' => 1,
                'excerpt'  => '2 hours of highlights around Birmingham city centre.',
                'content'  => '<p>Small groups, English-speaking. Meeting point shared after purchase.</p>',
            ],
        ];

        $created = [];
        foreach ($samples as $sample) {
            $existing = get_posts([
                'post_type'      => Angebot_Deals_Deal_CPT::POST_TYPE,
                'title'          => $sample['title'],
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'fields'         => 'ids',
            ]);
            if ($existing) {
                continue;
            }

            $id = wp_insert_post([
                'post_type'    => Angebot_Deals_Deal_CPT::POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => $sample['title'],
                'post_content' => $sample['content'],
                'post_excerpt' => $sample['excerpt'],
            ]);

            if (is_wp_error($id) || !$id) {
                continue;
            }

            $discount = (int) round((($sample['original'] - $sample['deal']) / $sample['original']) * 100);
            update_post_meta($id, '_angebot_original_price', $sample['original']);
            update_post_meta($id, '_angebot_deal_price', $sample['deal']);
            update_post_meta($id, '_angebot_discount_percent', $discount);
            update_post_meta($id, '_angebot_merchant_name', $sample['merchant']);
            update_post_meta($id, '_angebot_location_label', ucfirst($sample['location']));
            update_post_meta($id, '_angebot_quantity', $sample['qty']);
            update_post_meta($id, '_angebot_sold_count', 0);
            update_post_meta($id, '_angebot_is_featured', $sample['featured']);
            update_post_meta($id, '_angebot_voucher_expires', gmdate('Y-m-d', strtotime('+90 days')));
            update_post_meta($id, '_angebot_highlights', "Sent instantly by email\nRedeemable on site\nQR code included");
            update_post_meta($id, '_angebot_fine_print', "Cannot be combined with other discounts. Appointment required. No cash value.");

            $loc = get_term_by('slug', $sample['location'], Angebot_Deals_Deal_CPT::TAX_LOCATION);
            if ($loc) {
                wp_set_object_terms($id, [(int) $loc->term_id], Angebot_Deals_Deal_CPT::TAX_LOCATION);
            }

            $cat = get_term_by('slug', $sample['category'], Angebot_Deals_Deal_CPT::TAX_CATEGORY);
            if (!$cat) {
                // Fallback: first matching name fragment.
                $terms = get_terms(['taxonomy' => Angebot_Deals_Deal_CPT::TAX_CATEGORY, 'hide_empty' => false]);
                if (!is_wp_error($terms) && $terms) {
                    wp_set_object_terms($id, [(int) $terms[0]->term_id], Angebot_Deals_Deal_CPT::TAX_CATEGORY);
                }
            } else {
                wp_set_object_terms($id, [(int) $cat->term_id], Angebot_Deals_Deal_CPT::TAX_CATEGORY);
            }

            Angebot_Deals_WooCommerce_Sync::sync_product($id, get_post($id));
            $created[] = $id;
        }

        return $created;
    }
}
