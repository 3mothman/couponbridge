<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $categories */
/** @var array $locations */
/** @var array $notice */
?>
<?php if (!empty($notice)) : ?>
    <div class="angebot-notice angebot-notice--<?php echo esc_attr($notice['type']); ?>"><p><?php echo esc_html($notice['text']); ?></p></div>
<?php endif; ?>

<form class="angebot-deal-submit-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="angebot_submit_deal">
    <?php wp_nonce_field('angebot_submit_deal', 'angebot_submit_deal_nonce'); ?>

    <label for="deal_title">
        <span><?php esc_html_e('Deal title', 'angebot-deals'); ?></span>
        <input type="text" name="deal_title" id="deal_title" required>
    </label>

    <div class="angebot-deal-submit-form__row">
        <label for="deal_category">
            <span><?php esc_html_e('Category', 'angebot-deals'); ?></span>
            <select name="deal_category" id="deal_category" required>
                <option value=""><?php esc_html_e('— Please choose —', 'angebot-deals'); ?></option>
                <?php if (!is_wp_error($categories)) : foreach ($categories as $term) : ?>
                    <option value="<?php echo esc_attr((string) $term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                <?php endforeach; endif; ?>
            </select>
        </label>
        <label for="deal_location">
            <span><?php esc_html_e('City', 'angebot-deals'); ?></span>
            <select name="deal_location" id="deal_location" required>
                <option value=""><?php esc_html_e('— Please choose —', 'angebot-deals'); ?></option>
                <?php if (!is_wp_error($locations)) : foreach ($locations as $term) : ?>
                    <option value="<?php echo esc_attr((string) $term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                <?php endforeach; endif; ?>
            </select>
        </label>
    </div>

    <div class="angebot-deal-submit-form__row">
        <label for="original_price">
            <span><?php esc_html_e('Original price (£)', 'angebot-deals'); ?></span>
            <input type="number" step="0.01" min="0.01" name="original_price" id="original_price" required>
        </label>
        <label for="deal_price">
            <span><?php esc_html_e('Deal price (£)', 'angebot-deals'); ?></span>
            <input type="number" step="0.01" min="0" name="deal_price" id="deal_price" required>
        </label>
        <label for="quantity">
            <span><?php esc_html_e('How many can you offer?', 'angebot-deals'); ?></span>
            <input type="number" min="1" name="quantity" id="quantity" required>
        </label>
    </div>

    <label for="voucher_expires">
        <span><?php esc_html_e('Voucher valid until (optional)', 'angebot-deals'); ?></span>
        <input type="date" name="voucher_expires" id="voucher_expires">
    </label>

    <label for="deal_short_description">
        <span><?php esc_html_e('Short summary (shown on the deal card)', 'angebot-deals'); ?></span>
        <textarea name="deal_short_description" id="deal_short_description" rows="2"></textarea>
    </label>

    <label for="deal_description">
        <span><?php esc_html_e('Full description', 'angebot-deals'); ?></span>
        <textarea name="deal_description" id="deal_description" rows="5" required></textarea>
    </label>

    <label for="highlights">
        <span><?php esc_html_e('Highlights (one per line, optional)', 'angebot-deals'); ?></span>
        <textarea name="highlights" id="highlights" rows="3"></textarea>
    </label>

    <label for="fine_print">
        <span><?php esc_html_e('Fine print / redemption terms (optional)', 'angebot-deals'); ?></span>
        <textarea name="fine_print" id="fine_print" rows="3"></textarea>
    </label>

    <label for="deal_image">
        <span><?php esc_html_e('Photo (optional)', 'angebot-deals'); ?></span>
        <input type="file" name="deal_image" id="deal_image" accept=".jpg,.jpeg,.png,.webp">
    </label>

    <p class="angebot-deal-submit-form__note"><?php esc_html_e('New deals are reviewed before they go live — you will get an email once it is published.', 'angebot-deals'); ?></p>

    <button type="submit" class="angebot-btn angebot-btn--primary"><?php esc_html_e('Submit deal', 'angebot-deals'); ?></button>
</form>
