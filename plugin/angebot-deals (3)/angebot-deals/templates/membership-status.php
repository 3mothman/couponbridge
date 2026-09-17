<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var int $user_id */
/** @var string $status */
/** @var object|null $submission */

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
?>
<div class="angebot-membership">
    <p class="angebot-membership__badge angebot-membership__badge--<?php echo esc_attr($status); ?>">
        <?php echo esc_html(Angebot_Deals_Membership::status_label($status)); ?>
    </p>

    <?php if ($status === Angebot_Deals_Membership::STATUS_VERIFIED) : ?>
        <p><?php esc_html_e('You are a verified Highbridge member. You can browse and buy any deal on the site.', 'angebot-deals'); ?></p>
        <p><a class="angebot-btn angebot-btn--primary" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Browse deals', 'angebot-deals'); ?></a></p>

    <?php elseif ($status === Angebot_Deals_Membership::STATUS_PENDING) : ?>
        <p><?php esc_html_e('Thanks — your eligibility submission is with our team for review. We will email you as soon as a decision has been made.', 'angebot-deals'); ?></p>
        <?php if ($submission) : ?>
            <table class="angebot-membership__summary">
                <tr>
                    <th><?php esc_html_e('Benefit', 'angebot-deals'); ?></th>
                    <td><?php echo esc_html(Angebot_Deals_Eligibility::benefit_types()[$submission->benefit_type] ?? $submission->benefit_type); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Submitted', 'angebot-deals'); ?></th>
                    <td><?php echo esc_html(date_i18n('d.m.Y', strtotime($submission->created_at))); ?></td>
                </tr>
                <?php if ($submission->file_path !== '') : ?>
                    <tr>
                        <th><?php esc_html_e('Document', 'angebot-deals'); ?></th>
                        <td>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=angebot_eligibility_document&id=' . (int) $submission->id), 'angebot_eligibility_document_' . (int) $submission->id)); ?>" target="_blank" rel="noopener">
                                <?php esc_html_e('View what you submitted', 'angebot-deals'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        <?php endif; ?>

    <?php else : ?>
        <?php if ($status === Angebot_Deals_Membership::STATUS_REJECTED && $submission) : ?>
            <div class="angebot-notice angebot-notice--error">
                <p><?php esc_html_e('We could not verify your last submission.', 'angebot-deals'); ?></p>
                <?php if (!empty($submission->admin_note)) : ?>
                    <p><em><?php echo esc_html($submission->admin_note); ?></em></p>
                <?php endif; ?>
                <p><?php esc_html_e('Please submit a new document below.', 'angebot-deals'); ?></p>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('Verify your eligibility below to unlock buying deals.', 'angebot-deals'); ?></p>
        <?php endif; ?>

        <?php echo Angebot_Deals_Eligibility::render_form($user_id); ?>
    <?php endif; ?>
</div>
