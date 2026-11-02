<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Location
{
    public const COOKIE = 'angebot_location';
    public const CITIES = [
        'coventry' => 'Coventry',
        'nuneaton' => 'Nuneaton',
        'rugby' => 'Rugby',
        'birmingham' => 'Birmingham',
    ];

    public static function register_hooks(): void
    {
        add_action('wp_ajax_angebot_set_location', [self::class, 'ajax_set']);
        add_action('wp_ajax_nopriv_angebot_set_location', [self::class, 'ajax_set']);
        add_action('init', [self::class, 'seed_default_locations'], 20);
        add_filter('get_terms_args', [self::class, 'filter_city_choices'], 10, 2);

        // Tell LiteSpeed Cache (and compatible cache plugins) to serve a
        // separate cached version per location cookie, instead of one
        // frozen version for everyone. Without this, a cached page always
        // shows whatever city was selected when the cache was first built.
        //
        // Two mechanisms, used together for reliability:
        // 1) The official filter (writes a rewrite rule into .htaccess).
        add_filter('litespeed_vary_cookies', [self::class, 'add_vary_cookie']);
        // 2) A direct response header, which LiteSpeed reads immediately on
        //    every request without needing an .htaccess rewrite first.
        add_action('send_headers', [self::class, 'send_vary_header']);
    }

    public static function current(): string
    {
        if (!empty($_COOKIE[self::COOKIE])) {
            $slug = sanitize_title(wp_unslash($_COOKIE[self::COOKIE]));
            if (isset(self::CITIES[$slug])) {
                return $slug;
            }
        }
        return self::sanitize_default(get_option('angebot_default_location', 'coventry'));
    }

    public static function sanitize_default($value): string
    {
        $slug = sanitize_title((string) $value);
        return isset(self::CITIES[$slug]) ? $slug : 'coventry';
    }

    public static function filter_city_choices(array $args, array $taxonomies): array
    {
        // Limit lists (including the theme footer and admin choices), while
        // preserving direct lookups and existing object relationships.
        if ($taxonomies === [Angebot_Deals_Deal_CPT::TAX_LOCATION]
            && empty($args['object_ids']) && empty($args['slug']) && empty($args['name'])) {
            $args['slug'] = array_keys(self::CITIES);
        }
        return $args;
    }

    public static function current_label(): string
    {
        $slug = self::current();
        $term = get_term_by('slug', $slug, Angebot_Deals_Deal_CPT::TAX_LOCATION);
        return $term && !is_wp_error($term) ? $term->name : ucfirst($slug);
    }

    /**
     * Registers our location cookie with LiteSpeed Cache's "vary group"
     * mechanism so the cache stores one version per selected city instead
     * of a single frozen version for every visitor.
     *
     * @param array<int, string> $cookies
     * @return array<int, string>
     */
    public static function add_vary_cookie(array $cookies): array
    {
        $cookies[] = self::COOKIE;
        return $cookies;
    }

    /**
     * Sends the X-LiteSpeed-Vary header directly on every front-end
     * request. This is the immediate, no-.htaccess-required way to tell
     * LiteSpeed's cache engine "cache a separate copy of this URL per
     * value of this cookie". Harmless (simply ignored) on hosts that
     * don't run LiteSpeed.
     */
    public static function send_vary_header(): void
    {
        if (is_admin() || headers_sent()) {
            return;
        }
        header('X-LiteSpeed-Vary: cookie=' . self::COOKIE);
    }

    public static function ajax_set(): void
    {
        check_ajax_referer('angebot_public', 'nonce');
        $slug = isset($_POST['location']) ? sanitize_title(wp_unslash($_POST['location'])) : '';
        if (!isset(self::CITIES[$slug])) {
            wp_send_json_error(['message' => 'unsupported_location']);
            return;
        }

        setcookie(self::COOKIE, $slug, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[self::COOKIE] = $slug;

        wp_send_json_success(['location' => $slug, 'label' => self::current_label()]);
    }

    public static function seed_default_locations(): void
    {
        if (get_option('angebot_service_cities_version') === '1') {
            return;
        }

        foreach (self::CITIES as $slug => $city) {
            if (!get_term_by('slug', $slug, Angebot_Deals_Deal_CPT::TAX_LOCATION)) {
                $created = wp_insert_term($city, Angebot_Deals_Deal_CPT::TAX_LOCATION, ['slug' => $slug]);
                if (is_wp_error($created)) {
                    return; // Retry next request if a city could not be created.
                }
            }
        }

        $categories = [
            'Food & Drink', 'Beauty & Wellness', 'Leisure', 'Travel',
            'Activities', 'Services', 'Shopping', 'Events',
        ];

        foreach ($categories as $cat) {
            if (!term_exists($cat, Angebot_Deals_Deal_CPT::TAX_CATEGORY)) {
                wp_insert_term($cat, Angebot_Deals_Deal_CPT::TAX_CATEGORY);
            }
        }

        update_option('angebot_locations_seeded', 1);
        update_option('angebot_service_cities_version', '1');
        update_option('angebot_default_location', self::sanitize_default(get_option('angebot_default_location', 'coventry')));
    }
}
