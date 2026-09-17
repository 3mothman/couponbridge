<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Legal_Pages
{
    public static function register_hooks(): void
    {
        add_action('admin_init', [self::class, 'maybe_create_pages']);
    }

    public static function maybe_create_pages(): void
    {
        if (get_option('angebot_legal_pages_created')) {
            return;
        }

        if (!current_user_can('manage_options') && !defined('WP_CLI')) {
            // On activation, current_user_can may work; seed via activator flag check.
        }

        $pages = [
            'imprint' => [
                'title'   => 'Imprint',
                'content' => self::impressum_content(),
            ],
            'terms' => [
                'title'   => 'Terms & Conditions',
                'content' => self::agb_content(),
            ],
            'cancellation' => [
                'title'   => 'Cancellation Policy',
                'content' => self::widerruf_content(),
            ],
            'privacy' => [
                'title'   => 'Privacy Policy',
                'content' => self::datenschutz_content(),
            ],
        ];

        $ids = [];
        foreach ($pages as $key => $page) {
            $existing = get_page_by_path($key);
            if ($existing) {
                $ids[$key] = $existing->ID;
                continue;
            }

            $ids[$key] = wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $key,
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);
        }

        update_option('angebot_legal_page_ids', $ids);
        update_option('angebot_legal_pages_created', 1);
    }

    private static function impressum_content(): string
    {
        return <<<HTML
<!-- wp:paragraph -->
<p><strong>Legal notice / company information</strong></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>[Company Name]<br>[Street Address]<br>[Postal Code City]</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>Represented by:</strong><br>[First Name Last Name]</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>Contact:</strong><br>Phone: [Phone]<br>Email: [Email]</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>VAT ID:</strong><br>VAT identification number (if applicable): [VAT ID]</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><em>Please replace the placeholders with your real company details before going live.</em></p>
<!-- /wp:paragraph -->
HTML;
    }

    private static function agb_content(): string
    {
        return <<<HTML
<!-- wp:heading -->
<h2>Terms &amp; Conditions</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>§ 1 Scope — These terms apply to all purchases of vouchers/deals through this website.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>§ 2 Contract — Completing an order creates a purchase contract for the voucher. The local merchant provides the service upon redemption.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>§ 3 Vouchers — Each voucher is single-use, transferable as stated in the deal terms, and valid only until the stated expiry date.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>§ 4 Prices &amp; payment — All prices are in euros including applicable VAT where shown. Payment is processed via the available WooCommerce payment methods.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>§ 5 Liability — We broker the deal; fulfilment of the service is the responsibility of the respective merchant. Details are in the deal description.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><em>Have these terms reviewed by a lawyer before going live.</em></p>
<!-- /wp:paragraph -->
HTML;
    }

    private static function widerruf_content(): string
    {
        return <<<HTML
<!-- wp:heading -->
<h2>Cancellation / Right of Withdrawal</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Consumers may have a statutory right of withdrawal. For leisure vouchers with a fixed date, the right of withdrawal may be excluded under applicable law.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>Withdrawal period:</strong> 14 days from contract conclusion, unless a statutory exclusion applies.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>Effects of withdrawal:</strong> We will refund all payments without undue delay, and no later than 14 days.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><em>Adapt this text to your business model and have it legally reviewed. This is a template, not legal advice.</em></p>
<!-- /wp:paragraph -->
HTML;
    }

    private static function datenschutz_content(): string
    {
        return <<<HTML
<!-- wp:heading -->
<h2>Privacy Policy</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We process personal data (name, email, order data, payment data via the payment provider) to fulfil contracts and communicate with customers.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Legal basis: Art. 6(1)(b) GDPR (contract) and Art. 6(1)(f) GDPR (legitimate interest in IT security), or equivalent applicable law.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3>Membership eligibility verification</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Membership on this site is restricted to UK benefit recipients. To verify this we ask you to tell us which benefit or qualifying support you receive and to upload a supporting document (for example a benefit entitlement letter or award letter).</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>This information can reveal disability status, health-related circumstances, or financial/welfare status, which is <strong>special category data</strong> under Article 9 UK GDPR. We only process it with your <strong>explicit consent</strong>, given when you submit the verification form (Art. 9(2)(a) UK GDPR), for the sole purpose of deciding whether to grant you membership. It is reviewed manually by an authorised member of our team (or, in future, a partner organisation acting on our behalf under a data processing agreement) and is never used for marketing or shared with merchants.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Uploaded documents are stored in a restricted location that is not publicly accessible and are automatically deleted a limited number of days after a decision is made; a minimal record of the decision (benefit category, outcome, dates) is kept for audit purposes. You can withdraw consent, and request access to, correction of, or erasure of this data, at any time by contacting us.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>You have rights of access, rectification, erasure, restriction, and data portability, as well as the right to lodge a complaint with a supervisory authority (in the UK, the Information Commissioner's Office).</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><em>Add hosting, cookies, analytics, and payment providers (Stripe/PayPal) before going live. Before collecting real eligibility submissions, complete a Data Protection Impact Assessment (DPIA) covering the special category data above — this template does not replace one.</em></p>
<!-- /wp:paragraph -->
HTML;
    }
}
