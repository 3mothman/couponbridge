<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $status */
/** @var array $submissions */
/** @var array $counts */

$base = admin_url('edit.php?post_type=deal&page=angebot-eligibility');
$tabs = [
    'pending'  => __('Pending', 'angebot-deals') . ' (' . $counts[Angebot_Deals_Eligibility::STATUS_PENDING] . ')',
    'verified' => __('Approved', 'angebot-deals') . ' (' . $counts[Angebot_Deals_Eligibility::STATUS_VERIFIED] . ')',
    'rejected' => __('Rejected', 'angebot-deals') . ' (' . $counts[Angebot_Deals_Eligibility::STATUS_REJECTED] . ')',
    'all'      => __('All', 'angebot-deals'),
];
?>
<div class="wrap angebot-admin-eligibility">
    <h1><?php esc_html_e('Eligibility review', 'angebot-deals'); ?></h1>

    <?php if (isset($_GET['angebot_reviewed'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Decision saved and the member has been emailed.', 'angebot-deals'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['angebot_deleted'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Submission and its document were deleted.', 'angebot-deals'); ?></p></div>
    <?php endif; ?>

    <div class="notice notice-warning inline" style="padding:10px 14px;max-width:900px">
        <p><?php esc_html_e('These submissions may include disability, health or financial information (special category data). Only review them here, never forward documents by email or download them onto shared devices.', 'angebot-deals'); ?></p>
    </div>

    <h2 class="nav-tab-wrapper">
        <?php foreach ($tabs as $key => $label) : ?>
            <a href="<?php echo esc_url(add_query_arg('status', $key, $base)); ?>" class="nav-tab <?php echo $status === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Member', 'angebot-deals'); ?></th>
                <th><?php esc_html_e('Benefit', 'angebot-deals'); ?></th>
                <th><?php esc_html_e('Proof type', 'angebot-deals'); ?></th>
                <th><?php esc_html_e('Document', 'angebot-deals'); ?></th>
                <th><?php esc_html_e('Submitted', 'angebot-deals'); ?></th>
                <th><?php esc_html_e('Status', 'angebot-deals'); ?></th>
                <th style="width:340px"><?php esc_html_e('Decision', 'angebot-deals'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$submissions) : ?>
                <tr><td colspan="7"><?php esc_html_e('No submissions here.', 'angebot-deals'); ?></td></tr>
            <?php else : foreach ($submissions as $row) :
                $user = get_userdata((int) $row->user_id);
                $doc_url = $row->file_path !== ''
                    ? wp_nonce_url(admin_url('admin-post.php?action=angebot_eligibility_document&id=' . (int) $row->id), 'angebot_eligibility_document_' . (int) $row->id)
                    : '';
                ?>
                <tr>
                    <td>
                        <?php if ($user) : ?>
                            <strong><?php echo esc_html($user->display_name); ?></strong><br>
                            <a href="mailto:<?php echo esc_attr($user->user_email); ?>"><?php echo esc_html($user->user_email); ?></a>
                        <?php else : ?>
                            #<?php echo esc_html((string) $row->user_id); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(Angebot_Deals_Eligibility::benefit_types()[$row->benefit_type] ?? $row->benefit_type); ?></td>
                    <td><?php echo esc_html(Angebot_Deals_Eligibility::proof_types()[$row->proof_type] ?? $row->proof_type); ?></td>
                    <td>
                        <?php if ($doc_url) : ?>
                            <a class="button button-small" href="<?php echo esc_url($doc_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'angebot-deals'); ?></a>
                        <?php else : ?>
                            <em><?php esc_html_e('deleted (retention policy)', 'angebot-deals'); ?></em>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($row->created_at))); ?></td>
                    <td>
                        <?php echo esc_html(Angebot_Deals_Eligibility::status_label($row->status)); ?>
                        <?php if ($row->admin_note) : ?>
                            <br><em style="font-size:12px;color:#666"><?php echo esc_html($row->admin_note); ?></em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row->status === Angebot_Deals_Eligibility::STATUS_PENDING) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="angebot-eligibility-decision">
                                <?php wp_nonce_field('angebot_eligibility_review'); ?>
                                <input type="hidden" name="submission_id" value="<?php echo esc_attr((string) $row->id); ?>">
                                <textarea name="admin_note" rows="2" style="width:100%" placeholder="<?php esc_attr_e('Note (shown to the member if rejected)', 'angebot-deals'); ?>"></textarea>
                                <p>
                                    <button type="submit" formaction="<?php echo esc_url(admin_url('admin-post.php?action=angebot_eligibility_approve')); ?>" class="button button-primary"><?php esc_html_e('Approve', 'angebot-deals'); ?></button>
                                    <button type="submit" formaction="<?php echo esc_url(admin_url('admin-post.php?action=angebot_eligibility_reject')); ?>" class="button"><?php esc_html_e('Reject', 'angebot-deals'); ?></button>
                                </p>
                            </form>
                        <?php else : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=angebot_eligibility_delete')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Permanently delete this submission and its document?', 'angebot-deals')); ?>');">
                                <?php wp_nonce_field('angebot_eligibility_delete'); ?>
                                <input type="hidden" name="submission_id" value="<?php echo esc_attr((string) $row->id); ?>">
                                <button type="submit" class="button-link-delete"><?php esc_html_e('Delete submission & document', 'angebot-deals'); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
