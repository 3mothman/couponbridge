<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** Uses the existing deal categories so buyer filters also find a seller's offers. */
final class Angebot_Deals_Seller_Category
{
    private const META = '_angebot_business_category';
    private static bool $syncing = false;

    public static function register_hooks(): void
    {
        add_action('woocommerce_register_form', [self::class, 'registration_field'], 15);
        add_filter('woocommerce_registration_errors', [self::class, 'validate_registration'], 20, 3);
        add_action('woocommerce_created_customer', [self::class, 'save_registration'], 15);
        add_action('admin_post_angebot_business_category', [self::class, 'handle_save']);
        add_action('set_object_terms', [self::class, 'sync_deal'], 20, 4);
    }

    public static function get(int $user_id): int
    {
        $id = (int) get_user_meta($user_id, self::META, true);
        return self::valid($id) ? $id : 0;
    }

    public static function valid(int $id): bool
    {
        $term = $id > 0 ? get_term($id, Angebot_Deals_Deal_CPT::TAX_CATEGORY) : null;
        return $term && !is_wp_error($term);
    }

    public static function label(int $user_id): string
    {
        $id = self::get($user_id);
        return $id ? (string) get_term($id, Angebot_Deals_Deal_CPT::TAX_CATEGORY)->name : __('Not selected', 'angebot-deals');
    }

    private static function select(int $selected, bool $enabled): void
    {
        $categories = get_terms(['taxonomy' => Angebot_Deals_Deal_CPT::TAX_CATEGORY, 'hide_empty' => false]);
        ?>
        <label for="angebot_business_category"><?php esc_html_e('Business category', 'angebot-deals'); ?> <span class="required">*</span></label>
        <select id="angebot_business_category" name="angebot_business_category" <?php echo $enabled ? 'required' : 'disabled'; ?>>
            <option value=""><?php esc_html_e('Please choose your business category', 'angebot-deals'); ?></option>
            <?php if (!is_wp_error($categories)) : foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php selected($selected, (int) $category->term_id); ?>><?php echo esc_html($category->name); ?></option>
            <?php endforeach; endif; ?>
        </select>
        <?php
    }

    public static function registration_field(): void
    {
        $seller = ($_POST['angebot_account_type'] ?? '') === 'merchant';
        ?><p class="form-row form-row-wide" data-account-note="merchant" <?php echo $seller ? '' : 'hidden'; ?>><?php
        self::select(absint($_POST['angebot_business_category'] ?? 0), $seller);
        ?></p><?php
    }

    public static function validate_registration($errors, string $username, string $email)
    {
        if (($_POST['angebot_account_type'] ?? '') === 'merchant'
            && !self::valid(absint($_POST['angebot_business_category'] ?? 0))) {
            $errors->add('angebot_business_category', __('Please choose a valid business category.', 'angebot-deals'));
        }
        return $errors;
    }

    public static function save_registration(int $user_id): void
    {
        $id = absint($_POST['angebot_business_category'] ?? 0);
        if (Angebot_Deals_Seller_Documents::is_seller($user_id) && self::valid($id)) {
            update_user_meta($user_id, self::META, $id);
        }
    }

    public static function render_account(): string
    {
        $user_id = get_current_user_id();
        if (!Angebot_Deals_Seller_Documents::is_seller($user_id)) {
            return '';
        }
        ob_start();
        ?><h3><?php esc_html_e('Business category', 'angebot-deals'); ?></h3><?php
        if (self::get($user_id)) {
            echo '<p>' . esc_html(self::label($user_id)) . '</p>';
        } else {
            ?><p><?php esc_html_e('Choose your business category so buyers can find your offers using the category filter.', 'angebot-deals'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="angebot_business_category">
                <?php wp_nonce_field('angebot_business_category'); self::select(0, true); ?>
                <button type="submit" class="button"><?php esc_html_e('Save business category', 'angebot-deals'); ?></button>
            </form><?php
        }
        return (string) ob_get_clean();
    }

    public static function handle_save(): void
    {
        $user_id = get_current_user_id();
        if (!Angebot_Deals_Seller_Documents::is_seller($user_id)) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'), '', ['response' => 403]);
        }
        check_admin_referer('angebot_business_category');
        $id = absint($_POST['angebot_business_category'] ?? 0);
        if (self::get($user_id) || !self::valid($id)) {
            wp_die(esc_html__('Please choose a valid category. Contact our team to change an existing business category.', 'angebot-deals'));
        }
        if (!update_user_meta($user_id, self::META, $id)) {
            wp_die(esc_html__('Your category could not be saved. Please try again.', 'angebot-deals'));
        }
        // Existing sellers can classify their existing offers without publishing them.
        $args = ['post_type' => Angebot_Deals_Deal_CPT::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids'];
        $deals = array_unique(array_merge(
            get_posts($args + ['meta_key' => '_angebot_merchant_user_id', 'meta_value' => $user_id]),
            get_posts($args + ['author' => $user_id])
        ));
        foreach ($deals as $deal_id) {
            self::sync_deal((int) $deal_id, [], [], Angebot_Deals_Deal_CPT::TAX_CATEGORY);
        }
        wp_safe_redirect(wc_get_account_endpoint_url(Angebot_Deals_Merchant_Portal::ENDPOINT));
        exit;
    }

    public static function sync_deal(int $object_id, $terms, array $term_taxonomy_ids, string $taxonomy): void
    {
        if (self::$syncing || $taxonomy !== Angebot_Deals_Deal_CPT::TAX_CATEGORY
            || get_post_type($object_id) !== Angebot_Deals_Deal_CPT::POST_TYPE) {
            return;
        }
        $seller_id = (int) get_post_meta($object_id, '_angebot_merchant_user_id', true);
        if (!$seller_id) {
            $seller_id = (int) get_post_field('post_author', $object_id);
        }
        if (!Angebot_Deals_Seller_Documents::is_seller($seller_id)) {
            return;
        }
        $category_id = self::get($seller_id);
        if ($category_id) {
            self::$syncing = true;
            try {
                // Append the business category, retaining offer-specific categories.
                wp_set_object_terms($object_id, [$category_id], $taxonomy, true);
            } finally {
                self::$syncing = false;
            }
        }
    }
}
