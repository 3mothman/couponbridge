<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** Optional business evidence. Kept outside the WordPress public directory. */
final class Angebot_Deals_Seller_Documents
{
    private const META = '_angebot_seller_document';

    public static function register_hooks(): void
    {
        add_action('admin_post_angebot_seller_document_upload', [self::class, 'handle_upload']);
        add_action('admin_post_angebot_seller_document_view', [self::class, 'handle_view']);
        add_action('delete_user', [self::class, 'delete_document']);
        add_action('woocommerce_created_customer', [self::class, 'notify_registration'], 20);
    }

    public static function is_seller(int $user_id): bool
    {
        $user = get_userdata($user_id);
        return $user && in_array(Angebot_Deals_Merchant_Role::ROLE, (array) $user->roles, true);
    }

    public static function notify_registration(int $user_id): void
    {
        if (self::is_seller($user_id)) {
            self::notify_admin($user_id, false);
        }
    }

    private static function notify_admin(int $user_id, bool $uploaded): void
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        $to = sanitize_email((string) get_option('angebot_eligibility_notify_email', ''));
        if ($to === '') {
            $to = (string) get_option('admin_email');
        }
        $subject = $uploaded
            ? __('Seller uploaded a business document', 'angebot-deals')
            : __('New seller awaiting your approval', 'angebot-deals');
        $body = sprintf(
            __("Seller: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nAddress: %4\$s\n\nReview the seller and any optional document here: %5\$s\n\nYou may approve this seller with or without a document. Documents are available after signing in and are not attached to this email.", 'angebot-deals'),
            $user->display_name,
            $user->user_email,
            (string) get_user_meta($user_id, '_angebot_phone', true),
            implode(', ', array_filter([
                (string) get_user_meta($user_id, 'billing_address_1', true),
                (string) get_user_meta($user_id, 'billing_city', true),
                (string) get_user_meta($user_id, 'billing_postcode', true),
            ])),
            admin_url('edit.php?post_type=deal&page=angebot-merchants')
        );
        $body .= "\n" . __('Business category: ', 'angebot-deals') . Angebot_Deals_Seller_Category::label($user_id);
        if (!wp_mail($to, $subject, $body) && function_exists('wc_get_logger')) {
            wc_get_logger()->error('Seller review notification could not be sent.', ['source' => 'angebot-deals']);
        }
    }

    public static function document_link(int $user_id): string
    {
        $document = get_user_meta($user_id, self::META, true);
        if (!is_array($document) || empty($document['path'])) {
            return esc_html__('No document uploaded (optional).', 'angebot-deals');
        }
        $url = wp_nonce_url(
            add_query_arg(['action' => 'angebot_seller_document_view', 'user_id' => $user_id], admin_url('admin-post.php')),
            'angebot_seller_document_view_' . $user_id
        );
        return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">'
            . esc_html__('View business document', 'angebot-deals') . '</a>';
    }

    public static function render_form(): string
    {
        $user_id = get_current_user_id();
        if (!self::is_seller($user_id)) {
            return '';
        }
        ob_start();
        ?>
        <section class="angebot-seller-verification">
            <?php echo Angebot_Deals_Seller_Category::render_account(); ?>
            <h2><?php esc_html_e('Business verification', 'angebot-deals'); ?></h2>
            <p><?php echo Angebot_Deals_Merchant_Portal::is_merchant_approved($user_id)
                ? esc_html__('Your seller account is approved.', 'angebot-deals')
                : esc_html__('Your seller account is awaiting our review. You can publish offers after approval.', 'angebot-deals'); ?></p>
            <p><?php esc_html_e('You may upload a document to help us verify your business. This is optional: our team can approve your account without a document. Sellers do not need to submit benefit eligibility evidence.', 'angebot-deals'); ?></p>
            <?php if (isset($_GET['seller_document'])) : ?>
                <p role="status"><?php echo $_GET['seller_document'] === 'saved'
                    ? esc_html__('Document saved for our team to review.', 'angebot-deals')
                    : esc_html__('The document could not be saved. Please use a PDF, JPG or PNG up to 8 MB and try again.', 'angebot-deals'); ?></p>
            <?php endif; ?>
            <p><?php echo self::document_link($user_id); ?></p>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="angebot_seller_document_upload">
                <?php wp_nonce_field('angebot_seller_document_upload'); ?>
                <p><label for="business_document"><?php esc_html_e('Business document (optional; PDF, JPG or PNG, up to 8 MB)', 'angebot-deals'); ?></label><br>
                <input type="file" id="business_document" name="business_document" accept=".pdf,.jpg,.jpeg,.png"></p>
                <p><?php esc_html_e('Uploading another file replaces your previous document. Uploading does not approve your account automatically.', 'angebot-deals'); ?></p>
                <button type="submit" class="button"><?php esc_html_e('Upload document', 'angebot-deals'); ?></button>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function directory(): string
    {
        // Fail closed if WordPress cannot create private storage outside public_html.
        $public_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? ABSPATH) ?: rtrim(ABSPATH, '/\\');
        return dirname($public_root) . '/angebot-private-seller-' . substr(hash('sha256', ABSPATH), 0, 16);
    }

    public static function handle_upload(): void
    {
        $user_id = get_current_user_id();
        if (!self::is_seller($user_id)) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'), '', ['response' => 403]);
        }
        check_admin_referer('angebot_seller_document_upload');
        $file = $_FILES['business_document'] ?? [];
        $saved = false;
        if (is_array($file) && isset($file['tmp_name'], $file['name'], $file['size'])
            && is_string($file['tmp_name']) && is_string($file['name'])
            && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && (int) $file['size'] > 0 && (int) $file['size'] <= 8 * 1024 * 1024
            && is_uploaded_file($file['tmp_name']) && filesize($file['tmp_name']) <= 8 * 1024 * 1024
            && class_exists('finfo')) {
            $mimes = ['pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png'];
            $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $mimes);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!empty($checked['ext']) && !empty($checked['type']) && $mime === $checked['type']) {
                $dir = self::directory();
                if (wp_mkdir_p($dir) && is_writable($dir)) {
                    $path = bin2hex(random_bytes(24)) . '.' . $checked['ext'];
                    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $path)) {
                        @chmod($dir . '/' . $path, 0600);
                        $old = get_user_meta($user_id, self::META, true);
                        $saved = (bool) update_user_meta($user_id, self::META, [
                            'path' => $path,
                            'mime' => $mime,
                            'name' => sanitize_file_name($file['name']),
                        ]);
                        if ($saved) {
                            self::remove_file($old);
                            self::notify_admin($user_id, true);
                        } else {
                            @unlink($dir . '/' . $path);
                        }
                    }
                }
            }
        }
        wp_safe_redirect(add_query_arg('seller_document', $saved ? 'saved' : 'error', wc_get_account_endpoint_url(Angebot_Deals_Merchant_Portal::ENDPOINT)));
        exit;
    }

    public static function handle_view(): void
    {
        $user_id = absint($_GET['user_id'] ?? 0);
        if (!current_user_can('manage_options') && (!self::is_seller($user_id) || get_current_user_id() !== $user_id)) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'), '', ['response' => 403]);
        }
        check_admin_referer('angebot_seller_document_view_' . $user_id);
        $document = get_user_meta($user_id, self::META, true);
        $path = self::file_path($document);
        if ($path === '' || !is_readable($path)) {
            wp_die(esc_html__('Document not found.', 'angebot-deals'), '', ['response' => 404]);
        }
        nocache_headers();
        header('Content-Type: ' . $document['mime']);
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($document['name']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    private static function file_path($document): string
    {
        if (!is_array($document) || !isset($document['path']) || !is_string($document['path'])
            || !preg_match('/^[a-f0-9]{48}\.(pdf|jpg|jpeg|png)$/D', $document['path'])) {
            return '';
        }
        return self::directory() . '/' . $document['path'];
    }

    private static function remove_file($document): void
    {
        $path = self::file_path($document);
        if ($path !== '' && is_file($path)) {
            wp_delete_file($path);
        }
    }

    public static function delete_document(int $user_id): void
    {
        self::remove_file(get_user_meta($user_id, self::META, true));
        delete_user_meta($user_id, self::META);
    }
}
