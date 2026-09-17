<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Deal_Meta
{
    public const META_KEYS = [
        'original_price'   => 'float',
        'deal_price'       => 'float',
        'discount_percent' => 'int',
        'merchant_name'    => 'string',
        'merchant_user_id' => 'int',
        'location_label'   => 'string',
        'voucher_expires'  => 'string',
        'quantity'         => 'int',
        'sold_count'       => 'int',
        'product_id'       => 'int',
        'highlights'       => 'string',
        'fine_print'       => 'string',
        'is_featured'      => 'bool',
    ];

    public static function register_hooks(): void
    {
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
        add_action('save_post_' . Angebot_Deals_Deal_CPT::POST_TYPE, [self::class, 'save'], 10, 2);
        add_filter('manage_' . Angebot_Deals_Deal_CPT::POST_TYPE . '_posts_columns', [self::class, 'columns']);
        add_action('manage_' . Angebot_Deals_Deal_CPT::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box(
            'angebot_deal_details',
            __('Deal Details', 'angebot-deals'),
            [self::class, 'render_meta_box'],
            Angebot_Deals_Deal_CPT::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field('angebot_deal_meta', 'angebot_deal_meta_nonce');

        $data = self::get_all($post->ID);
        $merchants = get_users(['role' => 'angebot_merchant', 'orderby' => 'display_name']);
        ?>
        <style>
            .angebot-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 20px}
            .angebot-meta-grid label{display:block;font-weight:600;margin-bottom:4px}
            .angebot-meta-grid input,.angebot-meta-grid select,.angebot-meta-grid textarea{width:100%}
            .angebot-meta-grid .full{grid-column:1/-1}
        </style>
        <div class="angebot-meta-grid">
            <div>
                <label for="original_price"><?php esc_html_e('Original price (€)', 'angebot-deals'); ?></label>
                <input type="number" step="0.01" min="0" id="original_price" name="angebot_meta[original_price]" value="<?php echo esc_attr((string) $data['original_price']); ?>">
            </div>
            <div>
                <label for="deal_price"><?php esc_html_e('Deal price (€)', 'angebot-deals'); ?></label>
                <input type="number" step="0.01" min="0" id="deal_price" name="angebot_meta[deal_price]" value="<?php echo esc_attr((string) $data['deal_price']); ?>">
            </div>
            <div>
                <label for="discount_percent"><?php esc_html_e('Discount % (optional, otherwise auto)', 'angebot-deals'); ?></label>
                <input type="number" min="0" max="100" id="discount_percent" name="angebot_meta[discount_percent]" value="<?php echo esc_attr((string) $data['discount_percent']); ?>">
            </div>
            <div>
                <label for="quantity"><?php esc_html_e('Quota / available quantity', 'angebot-deals'); ?></label>
                <input type="number" min="0" id="quantity" name="angebot_meta[quantity]" value="<?php echo esc_attr((string) $data['quantity']); ?>">
            </div>
            <div>
                <label for="merchant_name"><?php esc_html_e('Merchant name', 'angebot-deals'); ?></label>
                <input type="text" id="merchant_name" name="angebot_meta[merchant_name]" value="<?php echo esc_attr($data['merchant_name']); ?>">
            </div>
            <div>
                <label for="merchant_user_id"><?php esc_html_e('Merchant user', 'angebot-deals'); ?></label>
                <?php if (current_user_can('manage_options')) : ?>
                    <select id="merchant_user_id" name="angebot_meta[merchant_user_id]">
                        <option value="0"><?php esc_html_e('— None —', 'angebot-deals'); ?></option>
                        <?php foreach ($merchants as $user) : ?>
                            <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected((int) $data['merchant_user_id'], (int) $user->ID); ?>>
                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="text" value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" disabled>
                    <p class="description"><?php esc_html_e('Deals you create are automatically assigned to you.', 'angebot-deals'); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="location_label"><?php esc_html_e('Location label (display)', 'angebot-deals'); ?></label>
                <input type="text" id="location_label" name="angebot_meta[location_label]" value="<?php echo esc_attr($data['location_label']); ?>" placeholder="e.g. Berlin-Mitte">
            </div>
            <div>
                <label for="voucher_expires"><?php esc_html_e('Voucher expiry date', 'angebot-deals'); ?></label>
                <input type="date" id="voucher_expires" name="angebot_meta[voucher_expires]" value="<?php echo esc_attr($data['voucher_expires']); ?>">
            </div>
            <div class="full">
                <label for="highlights"><?php esc_html_e('Highlights (one line per item)', 'angebot-deals'); ?></label>
                <textarea id="highlights" name="angebot_meta[highlights]" rows="4"><?php echo esc_textarea($data['highlights']); ?></textarea>
            </div>
            <div class="full">
                <label for="fine_print"><?php esc_html_e('Fine print / terms', 'angebot-deals'); ?></label>
                <textarea id="fine_print" name="angebot_meta[fine_print]" rows="4"><?php echo esc_textarea($data['fine_print']); ?></textarea>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="angebot_meta[is_featured]" value="1" <?php checked(!empty($data['is_featured'])); ?>>
                    <?php esc_html_e('Featured on homepage', 'angebot-deals'); ?>
                </label>
            </div>
            <div>
                <p><strong><?php esc_html_e('Sold:', 'angebot-deals'); ?></strong> <?php echo esc_html((string) (int) $data['sold_count']); ?></p>
                <p><strong><?php esc_html_e('WC product ID:', 'angebot-deals'); ?></strong> <?php echo esc_html((string) (int) $data['product_id']); ?></p>
            </div>
        </div>
        <?php
    }

    public static function save(int $post_id, WP_Post $post): void
    {
        if (!isset($_POST['angebot_deal_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['angebot_deal_meta_nonce'])), 'angebot_deal_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $raw = isset($_POST['angebot_meta']) && is_array($_POST['angebot_meta']) ? wp_unslash($_POST['angebot_meta']) : [];

        $original = isset($raw['original_price']) ? (float) $raw['original_price'] : 0.0;
        $deal     = isset($raw['deal_price']) ? (float) $raw['deal_price'] : 0.0;
        $discount = isset($raw['discount_percent']) ? (int) $raw['discount_percent'] : 0;

        if ($discount <= 0 && $original > 0 && $deal < $original) {
            $discount = (int) round((($original - $deal) / $original) * 100);
        }

        // Non-admins (merchants) can only ever be the merchant on their own
        // deals — never trust a posted merchant_user_id from them, in case
        // the disabled field in the metabox was tampered with client-side.
        $merchant_user_id = current_user_can('manage_options')
            ? absint($raw['merchant_user_id'] ?? 0)
            : get_current_user_id();

        $values = [
            'original_price'   => $original,
            'deal_price'       => $deal,
            'discount_percent' => max(0, min(100, $discount)),
            'merchant_name'    => sanitize_text_field((string) ($raw['merchant_name'] ?? '')),
            'merchant_user_id' => $merchant_user_id,
            'location_label'   => sanitize_text_field((string) ($raw['location_label'] ?? '')),
            'voucher_expires'  => sanitize_text_field((string) ($raw['voucher_expires'] ?? '')),
            'quantity'         => absint($raw['quantity'] ?? 0),
            'highlights'       => sanitize_textarea_field((string) ($raw['highlights'] ?? '')),
            'fine_print'       => sanitize_textarea_field((string) ($raw['fine_print'] ?? '')),
            'is_featured'      => !empty($raw['is_featured']) ? 1 : 0,
        ];

        foreach ($values as $key => $value) {
            update_post_meta($post_id, '_angebot_' . $key, $value);
        }
    }

    public static function get(int $deal_id, string $key, $default = '')
    {
        $value = get_post_meta($deal_id, '_angebot_' . $key, true);
        return $value === '' || $value === false ? $default : $value;
    }

    public static function get_all(int $deal_id): array
    {
        $data = [];
        foreach (array_keys(self::META_KEYS) as $key) {
            $type = self::META_KEYS[$key];
            if ($type === 'float') {
                $default = 0.0;
            } elseif ($type === 'int' || $type === 'bool') {
                $default = 0;
            } else {
                $default = '';
            }
            $data[$key] = self::get($deal_id, $key, $default);
        }
        return $data;
    }

    public static function remaining_quantity(int $deal_id): int
    {
        $qty  = (int) self::get($deal_id, 'quantity', 0);
        $sold = (int) self::get($deal_id, 'sold_count', 0);
        if ($qty <= 0) {
            return PHP_INT_MAX;
        }
        return max(0, $qty - $sold);
    }

    public static function columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['deal_price'] = __('Price', 'angebot-deals');
                $new['deal_stock'] = __('Quota', 'angebot-deals');
                $new['deal_merchant'] = __('Merchant', 'angebot-deals');
            }
        }
        return $new;
    }

    public static function render_column(string $column, int $post_id): void
    {
        $data = self::get_all($post_id);
        switch ($column) {
            case 'deal_price':
                printf(
                    '<span style="text-decoration:line-through;color:#888">%s €</span> <strong>%s €</strong> (-%d%%)',
                    esc_html(number_format((float) $data['original_price'], 2, ',', '.')),
                    esc_html(number_format((float) $data['deal_price'], 2, ',', '.')),
                    (int) $data['discount_percent']
                );
                break;
            case 'deal_stock':
                $qty = (int) $data['quantity'];
                echo $qty > 0
                    ? esc_html((int) $data['sold_count'] . ' / ' . $qty)
                    : esc_html__('Unlimited', 'angebot-deals');
                break;
            case 'deal_merchant':
                echo esc_html((string) $data['merchant_name']);
                break;
        }
    }
}
