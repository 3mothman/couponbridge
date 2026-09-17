<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $stats */
?>
<div class="angebot-impact">
    <div class="angebot-impact__stats">
        <div class="angebot-impact__stat">
            <span class="angebot-impact__number"><?php echo esc_html(number_format_i18n($stats['people_helped'])); ?></span>
            <span class="angebot-impact__label"><?php esc_html_e('People helped', 'angebot-deals'); ?></span>
        </div>
        <div class="angebot-impact__stat">
            <span class="angebot-impact__number">£<?php echo esc_html(number_format_i18n($stats['total_savings'], 0)); ?></span>
            <span class="angebot-impact__label"><?php esc_html_e('Total savings provided', 'angebot-deals'); ?></span>
        </div>
        <div class="angebot-impact__stat">
            <span class="angebot-impact__number"><?php echo esc_html(number_format_i18n($stats['redeemed_count'])); ?></span>
            <span class="angebot-impact__label"><?php esc_html_e('Deals redeemed', 'angebot-deals'); ?></span>
        </div>
        <div class="angebot-impact__stat">
            <span class="angebot-impact__number"><?php echo esc_html(number_format_i18n($stats['verified_members'])); ?></span>
            <span class="angebot-impact__label"><?php esc_html_e('Verified members', 'angebot-deals'); ?></span>
        </div>
    </div>
</div>
