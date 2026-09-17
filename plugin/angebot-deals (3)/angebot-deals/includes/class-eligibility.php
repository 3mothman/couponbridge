<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Eligibility verification: benefit/category + proof-of-entitlement
 * document, manually reviewed by an admin (or, in future, a partner
 * organisation) before a member is allowed to purchase deals.
 *
 * The proof document is special category / financial data under UK GDPR
 * (Art. 9). It is stored outside the public catalogue in a protected
 * uploads folder (deny-all .htaccess, no directory listing, randomised
 * filename — the same pattern WooCommerce itself uses for downloadable
 * files) and is only ever served through capability-checked, nonced
 * admin-post endpoints. See README "GDPR & eligibility data" section.
 */
final class Angebot_Deals_Eligibility
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public const CAP_REVIEW = 'angebot_review_eligibility';

    public static function register_hooks(): void
    {
        add_action('init', [self::class, 'grant_admin_capability']);

        add_shortcode('angebot_eligibility_form', [self::class, 'shortcode_form']);

        add_action('admin_post_angebot_submit_eligibility', [self::class, 'handle_submit']);
        add_action('admin_post_nopriv_angebot_submit_eligibility', [self::class, 'handle_submit']);
        add_action('admin_post_angebot_eligibility_approve', [self::class, 'handle_approve']);
        add_action('admin_post_angebot_eligibility_reject', [self::class, 'handle_reject']);
        add_action('admin_post_angebot_eligibility_delete', [self::class, 'handle_delete']);
        add_action('admin_post_angebot_eligibility_document', [self::class, 'handle_document']);

        add_action('admin_menu', [self::class, 'admin_menu']);

        add_action('angebot_deals_eligibility_retention', [self::class, 'run_retention_cleanup']);
        if (!wp_next_scheduled('angebot_deals_eligibility_retention')) {
            wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', 'angebot_deals_eligibility_retention');
        }

        add_filter('wp_privacy_personal_data_exporters', [self::class, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'register_eraser']);
    }

    public static function grant_admin_capability(): void
    {
        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap(self::CAP_REVIEW)) {
            $admin->add_cap(self::CAP_REVIEW);
        }
    }

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'angebot_eligibility';
    }

    /* ---------------------------------------------------------------
     * Reference data
     * ------------------------------------------------------------- */

    public static function benefit_types(): array
    {
        return [
            'universal_credit' => __('Universal Credit', 'angebot-deals'),
            'pip'               => __("PIP (Personal Independence Payment)", 'angebot-deals'),
            'esa'               => __('ESA (Employment and Support Allowance)', 'angebot-deals'),
            'pension_credit'    => __('Pension Credit', 'angebot-deals'),
            'housing_benefit'   => __('Housing Benefit', 'angebot-deals'),
            'income_support'    => __('Income Support', 'angebot-deals'),
            'jsa'               => __("Jobseeker's Allowance (JSA)", 'angebot-deals'),
            'other'             => __('Other qualifying support', 'angebot-deals'),
        ];
    }

    public static function proof_types(): array
    {
        return [
            'entitlement_letter'        => __('Recent benefit entitlement letter', 'angebot-deals'),
            'account_screenshot'        => __('Screenshot / PDF from your official benefits account', 'angebot-deals'),
            'council_letter'            => __('Council-issued eligibility letter', 'angebot-deals'),
            'pip_award_letter'          => __('PIP award letter', 'angebot-deals'),
            'housing_benefit_statement' => __('Housing Benefit statement', 'angebot-deals'),
        ];
    }

    public static function status_label(string $status): string
    {
        $labels = [
            self::STATUS_PENDING  => __('Pending review', 'angebot-deals'),
            self::STATUS_VERIFIED => __('Approved', 'angebot-deals'),
            self::STATUS_REJECTED => __('Rejected', 'angebot-deals'),
        ];
        return $labels[$status] ?? $status;
    }

    /* ---------------------------------------------------------------
     * Queries
     * ------------------------------------------------------------- */

    public static function get_by_id(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
        return $row ?: null;
    }

    public static function get_latest_for_user(int $user_id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY created_at DESC LIMIT 1',
            $user_id
        ));
        return $row ?: null;
    }

    public static function get_for_user(int $user_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY created_at DESC',
            $user_id
        )) ?: [];
    }

    public static function get_all(array $args = []): array
    {
        global $wpdb;
        $status = $args['status'] ?? '';
        $sql    = 'SELECT * FROM ' . self::table() . ' WHERE 1=1';
        $params = [];

        if ($status !== '') {
            $sql     .= ' AND status = %s';
            $params[] = $status;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 200';

        return $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
    }

    public static function counts(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT status, COUNT(*) AS c FROM ' . self::table() . ' GROUP BY status', ARRAY_A);
        $out  = [self::STATUS_PENDING => 0, self::STATUS_VERIFIED => 0, self::STATUS_REJECTED => 0];
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['c'];
        }
        return $out;
    }

    /* ---------------------------------------------------------------
     * Front-end form
     * ------------------------------------------------------------- */

    public static function shortcode_form(): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to verify your eligibility.', 'angebot-deals') . '</p>';
        }
        return self::render_form(get_current_user_id());
    }

    public static function render_form(int $user_id): string
    {
        $benefit_types = self::benefit_types();
        $proof_types   = self::proof_types();
        $privacy_page  = get_page_by_path('privacy');
        $privacy_url   = $privacy_page ? get_permalink($privacy_page) : '';
        $notice        = self::consume_session_notice();

        ob_start();
        include ANGEBOT_DEALS_PATH . 'templates/eligibility-form.php';
        return (string) ob_get_clean();
    }

    private static function consume_session_notice(): array
    {
        $code = isset($_GET['angebot_notice']) ? sanitize_key(wp_unslash($_GET['angebot_notice'])) : '';
        $map  = [
            'submitted'     => ['type' => 'success', 'text' => __('Thanks — your eligibility submission has been received and is awaiting review. We will email you once it has been checked.', 'angebot-deals')],
            'error_upload'  => ['type' => 'error', 'text' => __('We could not process your document. Please upload a PDF, JPG or PNG under 8MB and try again.', 'angebot-deals')],
            'error_fields'  => ['type' => 'error', 'text' => __('Please choose a benefit type, a proof type, tick the consent box, and attach your document.', 'angebot-deals')],
            'error_state'   => ['type' => 'error', 'text' => __('You already have a submission awaiting review or an approved membership.', 'angebot-deals')],
        ];
        return $map[$code] ?? [];
    }

    /* ---------------------------------------------------------------
     * Submission handling
     * ------------------------------------------------------------- */

    private static function redirect_back(string $notice): void
    {
        $url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url(Angebot_Deals_Membership::ENDPOINT)
            : home_url('/');
        wp_safe_redirect(add_query_arg('angebot_notice', $notice, $url));
        exit;
    }

    public static function handle_submit(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url());
            exit;
        }

        check_admin_referer('angebot_eligibility_submit', 'angebot_eligibility_nonce');

        $user_id = get_current_user_id();
        $current = Angebot_Deals_Membership::get_status($user_id);
        if (in_array($current, [Angebot_Deals_Membership::STATUS_PENDING, Angebot_Deals_Membership::STATUS_VERIFIED], true)) {
            self::redirect_back('error_state');
        }

        $benefit_type = isset($_POST['benefit_type']) ? sanitize_key(wp_unslash($_POST['benefit_type'])) : '';
        $proof_type   = isset($_POST['proof_type']) ? sanitize_key(wp_unslash($_POST['proof_type'])) : '';
        $consent      = !empty($_POST['consent']);

        if (!isset(self::benefit_types()[$benefit_type]) || !isset(self::proof_types()[$proof_type]) || !$consent) {
            self::redirect_back('error_fields');
        }

        if (empty($_FILES['proof_document']['name'])) {
            self::redirect_back('error_fields');
        }

        $stored = self::store_upload($_FILES['proof_document']);
        if (!$stored) {
            self::redirect_back('error_upload');
        }

        global $wpdb;
        $inserted = $wpdb->insert(
            self::table(),
            [
                'user_id'      => $user_id,
                'benefit_type' => $benefit_type,
                'proof_type'   => $proof_type,
                'file_path'    => $stored['file_path'],
                'file_name'    => $stored['file_name'],
                'file_mime'    => $stored['file_mime'],
                'status'       => self::STATUS_PENDING,
                'consent_given'=> 1,
                'consent_at'   => current_time('mysql'),
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if (!$inserted) {
            self::redirect_back('error_upload');
        }

        // Capture the insert ID before any other query (e.g. the user meta
        // update below) can overwrite $wpdb->insert_id.
        $submission_id = (int) $wpdb->insert_id;

        Angebot_Deals_Membership::set_status($user_id, Angebot_Deals_Membership::STATUS_PENDING);

        self::notify_admin_new_submission($user_id, $submission_id);
        self::notify_user_submitted($user_id);

        self::redirect_back('submitted');
    }

    public static function handle_approve(): void
    {
        self::handle_decision(self::STATUS_VERIFIED);
    }

    public static function handle_reject(): void
    {
        self::handle_decision(self::STATUS_REJECTED);
    }

    private static function handle_decision(string $status): void
    {
        if (!current_user_can(self::CAP_REVIEW)) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'));
        }
        check_admin_referer('angebot_eligibility_review');

        $id   = absint($_POST['submission_id'] ?? 0);
        $note = isset($_POST['admin_note']) ? sanitize_textarea_field(wp_unslash($_POST['admin_note'])) : '';

        $submission = self::get_by_id($id);
        if (!$submission) {
            wp_die(esc_html__('Submission not found.', 'angebot-deals'));
        }

        global $wpdb;
        $wpdb->update(
            self::table(),
            [
                'status'      => $status,
                'admin_note'  => $note,
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        Angebot_Deals_Membership::set_status((int) $submission->user_id, $status);
        self::notify_user_decision((int) $submission->user_id, $status, $note);

        wp_safe_redirect(add_query_arg(['page' => 'angebot-eligibility', 'angebot_reviewed' => $id], admin_url('edit.php?post_type=deal')));
        exit;
    }

    public static function handle_delete(): void
    {
        if (!current_user_can(self::CAP_REVIEW)) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'));
        }
        check_admin_referer('angebot_eligibility_delete');

        $id         = absint($_POST['submission_id'] ?? 0);
        $submission = self::get_by_id($id);
        if ($submission) {
            self::delete_file($submission);
            global $wpdb;
            $wpdb->delete(self::table(), ['id' => $id], ['%d']);
        }

        wp_safe_redirect(add_query_arg(['page' => 'angebot-eligibility', 'angebot_deleted' => 1], admin_url('edit.php?post_type=deal')));
        exit;
    }

    public static function handle_document(): void
    {
        $id    = absint($_GET['id'] ?? 0);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (!wp_verify_nonce($nonce, 'angebot_eligibility_document_' . $id)) {
            wp_die(esc_html__('This link has expired. Please go back and try again.', 'angebot-deals'));
        }

        $submission = self::get_by_id($id);
        if (!$submission || $submission->file_path === '') {
            wp_die(esc_html__('Document not found.', 'angebot-deals'));
        }

        $is_owner  = is_user_logged_in() && (int) $submission->user_id === get_current_user_id();
        $can_review = current_user_can(self::CAP_REVIEW);
        if (!$is_owner && !$can_review) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'), '', ['response' => 403]);
        }

        $full_path = self::protected_dir() . '/' . $submission->file_path;
        if (!is_readable($full_path)) {
            wp_die(esc_html__('Document not found.', 'angebot-deals'));
        }

        nocache_headers();
        header('Content-Type: ' . $submission->file_mime);
        header('Content-Disposition: inline; filename="' . sanitize_file_name($submission->file_name) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize($full_path));
        readfile($full_path);
        exit;
    }

    /* ---------------------------------------------------------------
     * Protected storage
     * ------------------------------------------------------------- */

    public static function protected_dir(): string
    {
        $base = wp_upload_dir();
        $dir  = trailingslashit($base['basedir']) . 'angebot-eligibility';

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        if (!file_exists($dir . '/.htaccess')) {
            file_put_contents(
                $dir . '/.htaccess',
                "Options -Indexes\ndeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
            );
        }
        if (!file_exists($dir . '/index.php')) {
            file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
        }

        return $dir;
    }

    private static function allowed_mimes(): array
    {
        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'pdf'          => 'application/pdf',
        ];
    }

    private static function store_upload(array $file): ?array
    {
        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $max_bytes = 8 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $max_bytes || (int) ($file['size'] ?? 0) <= 0) {
            return null;
        }

        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], self::allowed_mimes());
        if (empty($checked['ext']) || empty($checked['type'])) {
            return null;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $dir = self::protected_dir();

        add_filter('upload_dir', [self::class, 'filter_upload_dir']);
        $moved = wp_handle_upload($file, [
            'test_form' => false,
            'mimes'     => self::allowed_mimes(),
        ]);
        remove_filter('upload_dir', [self::class, 'filter_upload_dir']);

        if (empty($moved['file'])) {
            return null;
        }

        // Rename to an unguessable token, independent of the original
        // filename, as defense-in-depth on top of the deny-all folder.
        $token    = bin2hex(random_bytes(16));
        $ext      = pathinfo($moved['file'], PATHINFO_EXTENSION);
        $final    = trailingslashit($dir) . $token . '.' . $ext;

        if (!@rename($moved['file'], $final)) {
            $final = $moved['file'];
        }

        return [
            'file_path' => basename($final),
            'file_name' => sanitize_file_name($file['name']),
            'file_mime' => (string) $moved['type'],
        ];
    }

    public static function filter_upload_dir(array $dirs): array
    {
        $dirs['subdir'] = '';
        $dirs['path']   = trailingslashit($dirs['basedir']) . 'angebot-eligibility';
        $dirs['url']    = trailingslashit($dirs['baseurl']) . 'angebot-eligibility';
        return $dirs;
    }

    private static function delete_file(object $submission): void
    {
        if ($submission->file_path === '') {
            return;
        }
        $path = self::protected_dir() . '/' . $submission->file_path;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /* ---------------------------------------------------------------
     * Retention: sensitive documents are not kept forever.
     * ------------------------------------------------------------- */

    public static function retention_days(): int
    {
        return max(1, (int) get_option('angebot_eligibility_retention_days', 90));
    }

    public static function run_retention_cleanup(): void
    {
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::retention_days() * DAY_IN_SECONDS);

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . " WHERE file_path != '' AND reviewed_at IS NOT NULL AND reviewed_at < %s",
            $cutoff
        ));

        foreach ($rows as $row) {
            self::delete_file($row);
            $wpdb->update(
                self::table(),
                ['file_path' => '', 'file_name' => '', 'file_mime' => ''],
                ['id' => $row->id],
                ['%s', '%s', '%s'],
                ['%d']
            );
        }
    }

    /* ---------------------------------------------------------------
     * Admin
     * ------------------------------------------------------------- */

    public static function admin_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=deal',
            __('Eligibility review', 'angebot-deals'),
            __('Eligibility', 'angebot-deals'),
            self::CAP_REVIEW,
            'angebot-eligibility',
            [self::class, 'render_admin']
        );
    }

    public static function render_admin(): void
    {
        if (!current_user_can(self::CAP_REVIEW)) {
            wp_die(esc_html__('Permission denied.', 'angebot-deals'));
        }

        $status      = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : self::STATUS_PENDING;
        $submissions = self::get_all(['status' => $status === 'all' ? '' : $status]);
        $counts      = self::counts();

        include ANGEBOT_DEALS_PATH . 'admin/views/eligibility-review.php';
    }

    /* ---------------------------------------------------------------
     * Emails
     * ------------------------------------------------------------- */

    private static function notify_admin_new_submission(int $user_id, int $submission_id): void
    {
        $to      = (string) get_option('angebot_eligibility_notify_email', get_option('admin_email'));
        $user    = get_userdata($user_id);
        $review  = admin_url('edit.php?post_type=deal&page=angebot-eligibility');
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] New eligibility submission to review', 'angebot-deals'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );
        $body = sprintf(
            /* translators: 1: user display name, 2: user email, 3: review URL */
            __("A new eligibility verification has been submitted by %1\$s (%2\$s).\n\nReview it here: %3\$s\n\nThis email deliberately does not include the uploaded document — sign in to review it securely.", 'angebot-deals'),
            $user ? $user->display_name : '#' . $user_id,
            $user ? $user->user_email : '',
            $review
        );
        wp_mail($to, $subject, $body);
    }

    private static function notify_user_submitted(int $user_id): void
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        $subject = sprintf(__('[%s] We received your eligibility submission', 'angebot-deals'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $body    = __("Thanks for submitting your eligibility details. A member of our team will review your document and email you the outcome as soon as possible.", 'angebot-deals');
        wp_mail($user->user_email, $subject, $body);
    }

    private static function notify_user_decision(int $user_id, string $status, string $note): void
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        if ($status === self::STATUS_VERIFIED) {
            $subject = sprintf(__('[%s] You are now a verified Highbridge member', 'angebot-deals'), $site);
            $shop    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
            $body    = sprintf(
                __("Good news — your eligibility has been verified and your Highbridge membership is now active.\n\nYou can browse and buy deals here: %s", 'angebot-deals'),
                $shop
            );
        } else {
            $subject = sprintf(__('[%s] Your eligibility submission needs another look', 'angebot-deals'), $site);
            $account = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url(Angebot_Deals_Membership::ENDPOINT) : home_url('/');
            $body    = sprintf(
                __("We were not able to verify your eligibility from the document you provided.\n\n%s\n\nYou're welcome to submit a new document here: %s", 'angebot-deals'),
                $note !== '' ? __('Reviewer note: ', 'angebot-deals') . $note : __('No specific reason was given — please make sure the document clearly shows your name and current benefit entitlement.', 'angebot-deals'),
                $account
            );
        }

        wp_mail($user->user_email, $subject, $body);
    }

    /* ---------------------------------------------------------------
     * Privacy: export / erase
     * ------------------------------------------------------------- */

    public static function register_exporter(array $exporters): array
    {
        $exporters['angebot-eligibility'] = [
            'exporter_friendly_name' => __('Highbridge eligibility submissions', 'angebot-deals'),
            'callback'                => [self::class, 'export_data'],
        ];
        return $exporters;
    }

    public static function export_data(string $email, int $page = 1): array
    {
        $user = get_user_by('email', $email);
        if (!$user) {
            return ['data' => [], 'done' => true];
        }

        $items = [];
        foreach (self::get_for_user($user->ID) as $row) {
            $items[] = [
                'group_id'    => 'angebot-eligibility',
                'group_label' => __('Highbridge eligibility submissions', 'angebot-deals'),
                'item_id'     => 'submission-' . $row->id,
                'data'        => [
                    ['name' => __('Benefit type', 'angebot-deals'), 'value' => self::benefit_types()[$row->benefit_type] ?? $row->benefit_type],
                    ['name' => __('Proof type', 'angebot-deals'), 'value' => self::proof_types()[$row->proof_type] ?? $row->proof_type],
                    ['name' => __('Status', 'angebot-deals'), 'value' => self::status_label($row->status)],
                    ['name' => __('Submitted', 'angebot-deals'), 'value' => $row->created_at],
                    ['name' => __('Document on file', 'angebot-deals'), 'value' => $row->file_path !== '' ? __('Yes (available to you via your membership page)', 'angebot-deals') : __('No', 'angebot-deals')],
                ],
            ];
        }

        return ['data' => $items, 'done' => true];
    }

    public static function register_eraser(array $erasers): array
    {
        $erasers['angebot-eligibility'] = [
            'eraser_friendly_name' => __('Highbridge eligibility submissions', 'angebot-deals'),
            'callback'              => [self::class, 'erase_data'],
        ];
        return $erasers;
    }

    public static function erase_data(string $email, int $page = 1): array
    {
        $user = get_user_by('email', $email);
        if (!$user) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        $rows = self::get_for_user($user->ID);
        foreach ($rows as $row) {
            self::delete_file($row);
            global $wpdb;
            $wpdb->update(
                self::table(),
                ['file_path' => '', 'file_name' => '', 'file_mime' => '', 'admin_note' => ''],
                ['id' => $row->id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
        }

        $messages = $rows
            ? [__('Uploaded proof documents were deleted. A minimal audit record (benefit type, decision, dates) is retained to demonstrate compliance with our membership eligibility process.', 'angebot-deals')]
            : [];

        return [
            'items_removed'  => (bool) $rows,
            'items_retained' => (bool) $rows,
            'messages'       => $messages,
            'done'           => true,
        ];
    }
}
