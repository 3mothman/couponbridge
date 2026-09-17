<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (!empty($notice)) : ?>
    <div class="angebot-notice angebot-notice--<?php echo esc_attr($notice['type']); ?>"><p><?php echo esc_html($notice['text']); ?></p></div>
<?php endif; ?>

<form class="angebot-eligibility-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="angebot_submit_eligibility">
    <?php wp_nonce_field('angebot_eligibility_submit', 'angebot_eligibility_nonce'); ?>

    <p class="angebot-eligibility-form__intro">
        <?php esc_html_e('Highbridge membership is reserved for UK benefit recipients. Tell us which support you receive and attach one document as proof — a member of our team reviews it by hand.', 'angebot-deals'); ?>
    </p>

    <label for="benefit_type">
        <span><?php esc_html_e('Which benefit or support do you receive?', 'angebot-deals'); ?></span>
        <select name="benefit_type" id="benefit_type" required>
            <option value=""><?php esc_html_e('— Please choose —', 'angebot-deals'); ?></option>
            <?php foreach ($benefit_types as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label for="proof_type">
        <span><?php esc_html_e('What proof can you provide?', 'angebot-deals'); ?></span>
        <select name="proof_type" id="proof_type" required>
            <option value=""><?php esc_html_e('— Please choose —', 'angebot-deals'); ?></option>
            <?php foreach ($proof_types as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label for="proof_document">
        <span><?php esc_html_e('Upload your document (PDF, JPG or PNG, max 8MB)', 'angebot-deals'); ?></span>
        <input type="file" name="proof_document" id="proof_document" accept=".pdf,.jpg,.jpeg,.png" required>
    </label>

    <label class="angebot-eligibility-form__consent">
        <input type="checkbox" name="consent" value="1" required>
        <span>
            <?php
            printf(
                /* translators: %s: privacy policy link */
                esc_html__('I understand this document may show health, disability or financial information, and I explicitly consent to Highbridge processing it solely to verify my membership eligibility, as described in the %s.', 'angebot-deals'),
                $privacy_url ? '<a href="' . esc_url($privacy_url) . '" target="_blank" rel="noopener">' . esc_html__('Privacy Policy', 'angebot-deals') . '</a>' : esc_html__('Privacy Policy', 'angebot-deals')
            );
            ?>
        </span>
    </label>

    <button type="submit" class="angebot-btn angebot-btn--primary"><?php esc_html_e('Submit for review', 'angebot-deals'); ?></button>
</form>
