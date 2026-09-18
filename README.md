# Angebot — Local Deals Platform

WordPress plugin + theme for a Groupon-style deals site with **its own branding** (brand name, logo, colors). Layout and UX follow common local-deals patterns (e.g. groupon.de-style flows) without copying Groupon’s trademarks.

## Stack

- WordPress 6.4+
- WooCommerce 8+
- Custom plugin `angebot-deals` (PHP 8, OOP)
- Custom theme `angebot`
- Hosting: Hostinger (or any WordPress host)

## Installation (Hostinger)

1. Install WordPress + WooCommerce.
2. Copy folders:
   - `plugin/angebot-deals` → `wp-content/plugins/angebot-deals`
   - `theme/angebot` → `wp-content/themes/angebot`
3. Activate the **Angebot Deals** plugin and the **Angebot** theme.
4. Open **Settings → Permalinks** and save once (flush rewrite rules).
5. Complete WooCommerce setup (**currency GBP £**, store address in the UK).
6. Set **Settings → General → Site Language** to **English (UK)** — required so WordPress menus/admin are fully English (not German).
7. Payments: install **WooCommerce Stripe Gateway** and/or **PayPal Payments**, add live keys, turn off test mode when going live.
8. Optional: **Deals → Settings → Create demo deals** (London, Manchester, Birmingham, Edinburgh, Bath).
9. Open legal pages (`/company-info`, `/terms`, `/cancellation`, `/privacy`) and replace placeholders with real UK company details — ideally have a solicitor review them.
10. Upload your logo under **Appearance → Customizer → Logo**. Set the brand name under **Deals → Settings**.

On first load the plugin migrates old German cities/categories to English UK cities, creates an English **Main Menu** (Home, All Deals, categories), and prefers `en_GB` locale.

Default city is **London**. Seeded UK cities include London, Manchester, Birmingham, Leeds, Glasgow, Liverpool, Bristol, Sheffield, Edinburgh, Cardiff, Belfast, Newcastle, Nottingham, Southampton, Leicester, Brighton, Oxford, Cambridge, York, and Bath.

## Plugin features

| Feature | Status |
|--------|--------|
| CPT `deal` + categories + locations | ✅ |
| Meta: prices, discount, merchant, stock, expiry | ✅ |
| Auto WooCommerce product (hidden) | ✅ |
| Checkout via WooCommerce | ✅ |
| Voucher table + email with QR | ✅ |
| Merchant role + redeem (admin & frontend) | ✅ |
| Filters (category, city, price) + location cookie | ✅ |
| Reviews with moderation | ✅ |
| Legal pages (English templates) | ✅ |
| Demo deals | ✅ |
| Extended registration (name, DOB, phone, address) on its own `/register/` page | ✅ |
| Eligibility verification (benefit + proof document, manual review) | ✅ |
| Digital membership status gate (pending/verified/rejected) on checkout | ✅ |
| "My Deals" redemption history (My Account tab) | ✅ |
| Impact dashboard (admin + public `[angebot_impact]`) | ✅ |

### Shortcodes

- `[deals_grid]` / `[angebot_deals]` — deal grid (optional `show_filters="1"`)
- `[angebot_featured]` — featured deals
- `[angebot_filters]` — filter bar
- `[angebot_location_picker]` — city picker
- `[angebot_categories]` — category bar
- `[angebot_reviews]` — reviews (deal detail)
- `[angebot_merchant_portal]` — redeem UI for merchants
- `[angebot_eligibility_form]` — standalone eligibility submission form (also embedded automatically in My Account → Membership)
- `[angebot_impact]` — public impact stats (auto-added to the "Our Impact" page created on activation)

### Membership & eligibility workflow

1. A visitor registers via the normal WooCommerce `/my-account/` form, now extended with full name, date of birth, phone, and address (required). Every new account starts as **unverified**.
2. My Account gets two new tabs:
   - **Membership** — shows status (unverified / pending / verified / rejected) and, unless verified, the eligibility form: benefit type, proof type, one uploaded document (PDF/JPG/PNG, 8MB max), and a required explicit-consent checkbox.
   - **My Deals** — every voucher the member has bought, with live status (active/redeemed/expired) and its QR code, beyond WooCommerce's default order history.
3. Submitting the form sets status to **pending**, emails the admin (a review link only — never the document itself) and a confirmation to the member.
4. **Deals → Eligibility** (admin, capability `angebot_review_eligibility`, granted to Administrators only) lists pending/approved/rejected submissions. Approve or reject with an optional note; the member is emailed the decision automatically. Approving sets status to **verified**.
5. Only **verified** members (or admins) can add a deal to the cart or complete checkout — enforced at add-to-cart, cart, and checkout, not just in the UI. Deal cards/detail pages show "Log in to buy", "Verify your eligibility", or "Verification pending" instead of the buy button until then.
6. **Deals → Impact** (admin) and `[angebot_impact]` (public) show aggregate, privacy-safe totals: verified members, people helped, deals redeemed, and total savings provided.

### GDPR & eligibility data (read before going live)

Benefit type, disability-related proof, and the uploaded document are **special category / sensitive personal data** under UK GDPR (Art. 9). This plugin was built with that in mind, but **you are still responsible for compliance**:

- **Not legal advice.** Complete a Data Protection Impact Assessment (DPIA) before collecting real submissions.
- **Lawful basis**: explicit consent, captured (with a timestamp) when the member ticks the consent box and submits the form.
- **Storage**: uploaded documents live in `wp-content/uploads/angebot-eligibility/`, a folder with a deny-all `.htaccess`/`index.php` (the same pattern WooCommerce uses for its own protected downloads) and randomised filenames — never linkable or listable directly.
- **Access**: documents are only ever served through a capability-checked, nonced endpoint, viewable solely by the submitting member and admins with the `angebot_review_eligibility` capability. Never emailed or attached anywhere.
- **Retention**: `Deals → Settings` lets you set how many days after a decision the document is auto-deleted (default 90). A minimal audit record (benefit type, decision, dates) is kept.
- **Access/erasure requests**: both membership identity fields and eligibility submissions are wired into WordPress's built-in **Tools → Export/Erase Personal Data** so subject access and erasure requests are handled through the standard WP flow.
- Update the auto-generated Privacy Policy page with your organisation's real details, and get it reviewed by a solicitor.

### Merchant workflow

1. A business gets the **Merchant** role either by choosing "I run a local business and want to list deals" on the normal registration form, or by an admin assigning the Merchant role in **Users** later.
2. On the page using `[angebot_merchant_portal]` (or `page-templates/merchant-portal.php`), a merchant can now:
   - **Submit a new deal** — title, category, city, prices, quota, description, optional photo. It's saved as `pending`; the admin is emailed and publishes it from wp-admin like any other post. (Submitting while logged in as an admin publishes immediately, for quick testing.)
   - See **Your deals** with live status (Live / Pending review / Draft).
   - **Check & redeem** a customer's voucher code/QR, as before.
3. Merchants only ever see and edit their own deals (`merchant_user_id` is locked to themselves, both in this frontend form and in the wp-admin metabox).
4. QR opens `/voucher/{token}/` — logged-in merchants can approve redemption there.

## Branding (important)

- Do **not** use “Groupon” as a name, logo, or domain.
- Colors are intentionally **navy + coral** (not Groupon green).
- Layout/UX (header, cards, buy box, footer cities) may follow marketplace conventions.

## Recommended live checklist

- [ ] Imprint, terms, cancellation, and privacy finalized
- [ ] Stripe/PayPal live
- [ ] SSL (Hostinger)
- [ ] Test email delivery (SMTP plugin recommended)
- [ ] First test purchase including voucher email and redemption
- [ ] Brand name + logo finalized
- [ ] Tax/VAT settings in WooCommerce
- [ ] DPIA completed for eligibility verification; retention days + review notification email set in **Deals → Settings**
- [ ] Test the full flow once: register → submit eligibility → approve as admin → buy a deal → redeem → check My Deals + Impact dashboard
- [ ] After updating the plugin on an existing install, open **Settings → Permalinks** and save once if the new "Membership"/"My Deals" account tabs 404

## Project structure

```
angebot-web/
├── plugin/angebot-deals/     # Custom plugin
└── theme/angebot/            # Frontend theme
```

## Notes for further development

- Reviews start as `pending` (admin must approve).
- QR images come from `api.qrserver.com` (email/frontend). For offline / stricter privacy: add a local QR library.
- n8n: hook a webhook to `woocommerce_order_status_completed` or the custom action `angebot_deals_vouchers_created`.

Not legal advice — templates do not replace a lawyer’s review.
