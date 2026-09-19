<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Deal_CPT
{
    public const POST_TYPE = 'deal';
    public const TAX_CATEGORY = 'deal_category';
    public const TAX_LOCATION = 'deal_location';

    public static function register_hooks(): void
    {
        add_action('init', [self::class, 'register']);
    }

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'               => __('Deals', 'angebot-deals'),
                'singular_name'      => __('Deal', 'angebot-deals'),
                'add_new'            => __('New Deal', 'angebot-deals'),
                'add_new_item'       => __('Add New Deal', 'angebot-deals'),
                'edit_item'          => __('Edit Deal', 'angebot-deals'),
                'new_item'           => __('New Deal', 'angebot-deals'),
                'view_item'          => __('View Deal', 'angebot-deals'),
                'search_items'       => __('Search Deals', 'angebot-deals'),
                'not_found'          => __('No deals found', 'angebot-deals'),
                'not_found_in_trash' => __('No deals found in Trash', 'angebot-deals'),
                'menu_name'          => __('Deals', 'angebot-deals'),
            ],
            'public'             => true,
            'has_archive'        => true,
            'rewrite'            => ['slug' => 'deals', 'with_front' => false],
            'menu_icon'          => 'dashicons-tag',
            'menu_position'      => 5,
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'author'],
            'show_in_rest'       => true,
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'publicly_queryable' => true,
        ]);

        register_taxonomy(self::TAX_CATEGORY, self::POST_TYPE, [
            'labels' => [
                'name'          => __('Deal Categories', 'angebot-deals'),
                'singular_name' => __('Deal Category', 'angebot-deals'),
                'search_items'  => __('Search Categories', 'angebot-deals'),
                'all_items'     => __('All Categories', 'angebot-deals'),
                'edit_item'     => __('Edit Category', 'angebot-deals'),
                'add_new_item'  => __('New Category', 'angebot-deals'),
            ],
            'public'            => true,
            'hierarchical'      => true,
            // NOT 'category' — that's WordPress core's own built-in
            // taxonomy slug (used for blog posts) and colliding with it
            // makes WordPress unable to tell the two apart, so a deal
            // category archive silently 404s / shows "Nothing found".
            'rewrite'           => ['slug' => 'deal-category'],
            'show_admin_column' => true,
            'show_in_rest'      => true,
        ]);

        register_taxonomy(self::TAX_LOCATION, self::POST_TYPE, [
            'labels' => [
                'name'          => __('Locations', 'angebot-deals'),
                'singular_name' => __('Location', 'angebot-deals'),
                'search_items'  => __('Search Locations', 'angebot-deals'),
                'all_items'     => __('All Locations', 'angebot-deals'),
                'edit_item'     => __('Edit Location', 'angebot-deals'),
                'add_new_item'  => __('New Location', 'angebot-deals'),
            ],
            'public'            => true,
            'hierarchical'      => true,
            'rewrite'           => ['slug' => 'city'],
            'show_admin_column' => true,
            'show_in_rest'      => true,
        ]);
    }
}
