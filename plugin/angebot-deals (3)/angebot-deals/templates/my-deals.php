<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $vouchers */
?>
<div class="angebot-my-deals">
    <?php if (!$vouchers) : ?>
        <p><?php esc_html_e('You have not bought any deals yet.', 'angebot-deals'); ?></p>
        <?php if (function_exists('wc_get_page_permalink')) : ?>
            <p><a class="angebot-btn angebot-btn--primary" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Browse deals', 'angebot-deals'); ?></a></p>
        <?php endif; ?>
    <?php else : ?>
        <div class="angebot-my-deals__list">
            <?php foreach ($vouchers as $voucher) :
                $voucher = Angebot_Deals_Voucher::refresh_status($voucher);
                $deal_id = (int) $voucher->deal_id;
                ?>
                <article class="angebot-my-deal">
                    <div class="angebot-my-deal__info">
                        <h3><a href="<?php echo esc_url(get_permalink($deal_id)); ?>"><?php echo esc_html(get_the_title($deal_id)); ?></a></h3>
                        <p class="angebot-voucher-code"><?php echo esc_html($voucher->code); ?></p>
                        <p>
                            <span class="angebot-voucher-status status-<?php echo esc_attr($voucher->status); ?>">
                                <?php echo esc_html(Angebot_Deals_Voucher::status_label($voucher->status)); ?>
                            </span>
                            <?php if ($voucher->status === Angebot_Deals_Voucher::STATUS_REDEEMED && $voucher->redeemed_at) : ?>
                                — <?php printf(esc_html__('redeemed %s', 'angebot-deals'), esc_html(date_i18n('d.m.Y', strtotime($voucher->redeemed_at)))); ?>
                            <?php elseif ($voucher->expires_at) : ?>
                                — <?php printf(esc_html__('valid until %s', 'angebot-deals'), esc_html(date_i18n('d.m.Y', strtotime($voucher->expires_at)))); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($voucher->status === Angebot_Deals_Voucher::STATUS_ACTIVE) : ?>
                        <div class="angebot-my-deal__qr">
                            <?php echo Angebot_Deals_QR_Code::render_img($voucher->qr_token, 96); ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
