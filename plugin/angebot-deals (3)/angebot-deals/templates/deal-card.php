<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$deal_id = get_the_ID();
$data    = Angebot_Deals_Deal_Meta::get_all($deal_id);
$product_id = (int) $data['product_id'];
$remaining  = Angebot_Deals_Deal_Meta::remaining_quantity($deal_id);
$permalink  = get_permalink($deal_id);
$thumb      = get_the_post_thumbnail_url($deal_id, 'medium_large') ?: ANGEBOT_DEALS_URL . 'assets/images/deal-placeholder.svg';
$expires    = (string) $data['voucher_expires'];
$avg        = Angebot_Deals_Reviews::average_rating($deal_id);
$count      = Angebot_Deals_Reviews::count($deal_id);
?>
<article class="angebot-deal-card">
    <a class="angebot-deal-card__media" href="<?php echo esc_url($permalink); ?>">
        <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy" width="640" height="400">
        <?php if ((int) $data['discount_percent'] > 0) : ?>
            <span class="angebot-badge">-<?php echo esc_html((string) (int) $data['discount_percent']); ?>%</span>
        <?php endif; ?>
    </a>
    <div class="angebot-deal-card__body">
        <p class="angebot-deal-card__merchant"><?php echo esc_html((string) $data['merchant_name']); ?></p>
        <h3 class="angebot-deal-card__title">
            <a href="<?php echo esc_url($permalink); ?>"><?php the_title(); ?></a>
        </h3>
        <?php if ($data['location_label']) : ?>
            <p class="angebot-deal-card__location"><?php echo esc_html((string) $data['location_label']); ?></p>
        <?php endif; ?>
        <?php if ($avg > 0) : ?>
            <p class="angebot-deal-card__rating">
                <span aria-hidden="true"><?php echo esc_html(str_repeat('★', (int) round($avg)) . str_repeat('☆', 5 - (int) round($avg))); ?></span>
                <span><?php echo esc_html(number_format($avg, 1, ',', '.')); ?> (<?php echo esc_html((string) $count); ?>)</span>
            </p>
        <?php endif; ?>
        <div class="angebot-deal-card__prices">
            <span class="angebot-price-old"><?php echo esc_html(number_format((float) $data['original_price'], 2, ',', '.')); ?> €</span>
            <span class="angebot-price-new"><?php echo esc_html(number_format((float) $data['deal_price'], 2, ',', '.')); ?> €</span>
        </div>
        <?php if ($expires) : ?>
            <p class="angebot-countdown" data-expires="<?php echo esc_attr($expires); ?>T23:59:59">
                <?php esc_html_e('Expires in', 'angebot-deals'); ?> <span class="angebot-countdown__time">—</span>
            </p>
        <?php endif; ?>
        <?php $cta = Angebot_Deals_Membership::cta_for_deal($deal_id, $product_id, $remaining); ?>
        <div class="angebot-deal-card__actions">
            <?php if ($cta['disabled']) : ?>
                <span class="angebot-btn angebot-btn--disabled"><?php echo esc_html($cta['label']); ?></span>
            <?php else : ?>
                <a class="angebot-btn angebot-btn--primary" href="<?php echo esc_url($cta['url']); ?>">
                    <?php echo esc_html($cta['label']); ?>
                </a>
            <?php endif; ?>
            <a class="angebot-btn angebot-btn--ghost" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('Details', 'angebot-deals'); ?></a>
        </div>
    </div>
</article>
