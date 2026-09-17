<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (current_user_can('angebot_submit_deal')) : ?>
<div class="angebot-merchant-portal">
    <h2><?php esc_html_e('Submit a new deal', 'angebot-deals'); ?></h2>
    <?php echo Angebot_Deals_Merchant_Portal::render_submit_deal_form(); ?>
</div>

<div class="angebot-merchant-portal">
    <h2><?php esc_html_e('Your deals', 'angebot-deals'); ?></h2>
    <?php echo Angebot_Deals_Merchant_Portal::render_my_deals_list(); ?>
</div>
<?php endif; ?>

<div class="angebot-merchant-portal">
    <h2><?php esc_html_e('Check & redeem voucher', 'angebot-deals'); ?></h2>
    <form id="angebot-merchant-lookup" class="angebot-merchant-lookup">
        <label>
            <span><?php esc_html_e('Voucher code', 'angebot-deals'); ?></span>
            <input type="text" name="code" placeholder="XXXX-XXXX-XXXX" required autocomplete="off">
        </label>
        <button type="submit" class="angebot-btn angebot-btn--primary"><?php esc_html_e('Check', 'angebot-deals'); ?></button>
    </form>
    <div id="angebot-merchant-result" class="angebot-merchant-result" hidden></div>
</div>
