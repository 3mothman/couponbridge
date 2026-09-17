<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var WP_Post[] $deals */

$status_labels = [
    'publish' => __('Live', 'angebot-deals'),
    'pending' => __('Pending review', 'angebot-deals'),
    'draft'   => __('Draft', 'angebot-deals'),
];
?>
<?php if (!$deals) : ?>
    <p><?php esc_html_e('You have not submitted any deals yet.', 'angebot-deals'); ?></p>
<?php else : ?>
    <table class="angebot-my-deals-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Deal', 'angebot-deals'); ?></th>
                <th><?php esc_html_e('Price', 'angebot-deals'); ?></th>
                <th><?php esc_html_e('Sold', 'angebot-deals'); ?></th>
                <th><?php esc_html_e('Status', 'angebot-deals'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deals as $deal) :
                $data = Angebot_Deals_Deal_Meta::get_all($deal->ID);
                ?>
                <tr>
                    <td>
                        <?php if ($deal->post_status === 'publish') : ?>
                            <a href="<?php echo esc_url(get_permalink($deal)); ?>"><?php echo esc_html(get_the_title($deal)); ?></a>
                        <?php else : ?>
                            <?php echo esc_html(get_the_title($deal)); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="angebot-price-old"><?php echo esc_html(number_format((float) $data['original_price'], 2)); ?></span>
                        <span class="angebot-price-new"><?php echo esc_html(number_format((float) $data['deal_price'], 2)); ?></span>
                    </td>
                    <td>
                        <?php
                        $qty = (int) $data['quantity'];
                        echo $qty > 0 ? esc_html((int) $data['sold_count'] . ' / ' . $qty) : esc_html__('Unlimited', 'angebot-deals');
                        ?>
                    </td>
                    <td><span class="angebot-deal-status angebot-deal-status--<?php echo esc_attr($deal->post_status); ?>"><?php echo esc_html($status_labels[$deal->post_status] ?? $deal->post_status); ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
