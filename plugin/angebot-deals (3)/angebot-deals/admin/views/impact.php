<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $stats */

$max = max(1, ...array_column($stats['monthly'], 'count'));
?>
<div class="wrap angebot-admin-impact">
    <h1><?php esc_html_e('Impact dashboard', 'angebot-deals'); ?></h1>
    <p class="description"><?php esc_html_e('Aggregate numbers only — no member names or eligibility categories.', 'angebot-deals'); ?></p>

    <div class="angebot-admin-impact__tiles">
        <div class="card">
            <h2><?php echo esc_html(number_format_i18n($stats['verified_members'])); ?></h2>
            <p><?php esc_html_e('Verified members', 'angebot-deals'); ?></p>
        </div>
        <div class="card">
            <h2><?php echo esc_html(number_format_i18n($stats['people_helped'])); ?></h2>
            <p><?php esc_html_e('People helped (redeemed at least one deal)', 'angebot-deals'); ?></p>
        </div>
        <div class="card">
            <h2><?php echo esc_html(number_format_i18n($stats['redeemed_count'])); ?></h2>
            <p><?php esc_html_e('Deals redeemed', 'angebot-deals'); ?></p>
        </div>
        <div class="card">
            <h2><?php echo esc_html(number_format_i18n($stats['active_count'])); ?></h2>
            <p><?php esc_html_e('Vouchers active (not yet redeemed)', 'angebot-deals'); ?></p>
        </div>
        <div class="card">
            <h2>£<?php echo esc_html(number_format_i18n($stats['total_savings'], 2)); ?></h2>
            <p><?php esc_html_e('Total savings provided (redeemed deals, at current prices)', 'angebot-deals'); ?></p>
        </div>
    </div>

    <h2><?php esc_html_e('Redemptions over the last 6 months', 'angebot-deals'); ?></h2>
    <div class="angebot-admin-impact__chart">
        <?php foreach ($stats['monthly'] as $month) : ?>
            <div class="angebot-admin-impact__bar">
                <div class="angebot-admin-impact__bar-fill" style="height: <?php echo esc_attr((string) max(4, (int) round($month['count'] / $max * 120))); ?>px" title="<?php echo esc_attr($month['count']); ?>"></div>
                <span class="angebot-admin-impact__bar-count"><?php echo esc_html((string) $month['count']); ?></span>
                <span class="angebot-admin-impact__bar-label"><?php echo esc_html($month['label']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <p style="margin-top:24px">
        <?php
        printf(
            /* translators: %s: shortcode */
            esc_html__('Show a public, privacy-safe version of these numbers anywhere with the %s shortcode, or on the auto-created "Our Impact" page.', 'angebot-deals'),
            '<code>[angebot_impact]</code>'
        );
        ?>
    </p>
</div>
<style>
.angebot-admin-impact__tiles{display:flex;flex-wrap:wrap;gap:16px;margin:16px 0}
.angebot-admin-impact__tiles .card{flex:1 1 180px;padding:16px;text-align:center}
.angebot-admin-impact__tiles .card h2{margin:0 0 4px;font-size:28px;color:#17293F}
.angebot-admin-impact__tiles .card p{margin:0;color:#5b6773}
.angebot-admin-impact__chart{display:flex;align-items:flex-end;gap:16px;height:170px;padding:16px;background:#fff;border:1px solid #dcdcde;max-width:600px}
.angebot-admin-impact__bar{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;flex:1;height:100%}
.angebot-admin-impact__bar-fill{width:28px;background:#3F7A72;border-radius:3px 3px 0 0}
.angebot-admin-impact__bar-count{font-size:12px;font-weight:600;margin-top:4px}
.angebot-admin-impact__bar-label{font-size:11px;color:#5b6773;margin-top:2px}
</style>
