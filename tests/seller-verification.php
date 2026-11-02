<?php
// Isolated regression harness: WordPress services are stubs; uploads use real PHP HTTP handling.
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/.runtime/public_html/');
@mkdir(ABSPATH, 0777, true);
$_SERVER['DOCUMENT_ROOT'] = rtrim(ABSPATH, '/');
$base = dirname(__DIR__) . '/plugin/angebot-deals (3)/angebot-deals/includes/';
$users = [
    1 => (object) ['ID' => 1, 'roles' => ['customer', 'angebot_merchant'], 'display_name' => 'Seller', 'user_email' => 'seller@example.test'],
    2 => (object) ['ID' => 2, 'roles' => ['customer'], 'display_name' => 'Buyer', 'user_email' => 'buyer@example.test'],
    3 => (object) ['ID' => 3, 'roles' => ['administrator'], 'display_name' => 'Admin', 'user_email' => 'admin@example.test'],
    4 => (object) ['ID' => 4, 'roles' => ['customer', 'angebot_merchant'], 'display_name' => 'Other seller', 'user_email' => 'other@example.test'],
];
$state_file = __DIR__ . '/.runtime/state.json';
$meta = is_file($state_file) ? json_decode(file_get_contents($state_file), true) : [];
if (PHP_SAPI === 'cli') { $meta = []; file_put_contents($state_file, '{}'); }
$current = (int) ($_GET['actor'] ?? 1);
$mails = [];
class TestRedirect extends Exception {}
class TestDenied extends Exception {}
class WP_Error { public array $codes = []; public function add($code, $message) { $this->codes[] = $code; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
$categories = [10 => (object) ['term_id' => 10, 'name' => 'Food & Drink'], 11 => (object) ['term_id' => 11, 'name' => 'Services']];
$offers = [100 => 1, 101 => 1, 102 => 4];
$offer_terms = [100 => [11], 101 => [11], 102 => [11]];
function get_term($id, $tax) { return $tax === 'deal_category' ? ($GLOBALS['categories'][$id] ?? null) : null; }
function get_terms($args) { return array_values($GLOBALS['categories']); }
function get_post_type($id) { return isset($GLOBALS['offers'][$id]) ? 'deal' : 'post'; }
function get_post_meta($id, $key, $single = true) { return $key === '_angebot_merchant_user_id' ? ($GLOBALS['offers'][$id] ?? 0) : ''; }
function get_post_field($field, $id) { return $GLOBALS['offers'][$id] ?? 0; }
function get_posts($args) { return array_keys(array_filter($GLOBALS['offers'], fn($id) => $id === (int) ($args['author'] ?? $args['meta_value'] ?? 0))); }
function wp_set_object_terms($id, $terms, $tax, $append = false) { $GLOBALS['offer_terms'][$id] = $append ? array_unique(array_merge($GLOBALS['offer_terms'][$id] ?? [], $terms)) : $terms; Angebot_Deals_Seller_Category::sync_deal($id, $terms, [], $tax); }
function esc_attr($s) { return htmlspecialchars((string) $s); }
function selected($a, $b) { if ($a == $b) { echo 'selected'; } }
function get_userdata($id) { return $GLOBALS['users'][$id] ?? false; }
function get_current_user_id() { return $GLOBALS['current']; }
function user_can($id, $cap) { return $id === 3 || (in_array($cap, ['angebot_redeem_voucher', 'angebot_submit_deal'], true) && Angebot_Deals_Seller_Documents::is_seller($id)); }
function current_user_can($cap) { return user_can(get_current_user_id(), $cap); }
function is_user_logged_in() { return get_current_user_id() > 0; }
function get_user_meta($id, $key, $single = true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_user_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; file_put_contents($GLOBALS['state_file'], json_encode($GLOBALS['meta'])); return true; }
function delete_user_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); file_put_contents($GLOBALS['state_file'], json_encode($GLOBALS['meta'])); }
function wp_delete_file($path) { unlink($path); }
function get_option($key, $default = false) { return $key === 'admin_email' ? 'admin@example.test' : $default; }
function sanitize_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL) ?: ''; }
function sanitize_file_name($name) { return preg_replace('/[^a-zA-Z0-9._-]/', '', $name); }
function __($s, $domain = '') { return $s; }
function esc_html__($s, $domain = '') { return htmlspecialchars($s); }
function esc_html_e($s, $domain = '') { echo htmlspecialchars($s); }
function esc_html($s) { return htmlspecialchars($s); }
function esc_url($s) { return htmlspecialchars($s); }
function admin_url($s) { return '/admin/' . $s; }
function wc_get_account_endpoint_url($s) { return '/my-account/' . $s; }
function add_query_arg($key, $value, $url = null) { if (is_array($key)) { return $value . '?' . http_build_query($key); } return $url . '?' . http_build_query([$key => $value]); }
function wp_nonce_url($url, $action) { return $url . '&_wpnonce=test'; }
function wp_nonce_field($action) { echo '<input name="_wpnonce" value="test">'; }
function check_admin_referer($action, $name = '_wpnonce') { if (($_REQUEST[$name] ?? '') !== 'test') { throw new TestDenied('CSRF', 403); } }
function wp_die($s, $title = '', $args = []) { throw new TestDenied($s, $args['response'] ?? 403); }
function absint($s) { return abs((int) $s); }
function wp_safe_redirect($url) { throw new TestRedirect($url); }
function wp_mail($to, $subject, $body) { $GLOBALS['mails'][] = compact('to', 'subject', 'body'); return true; }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0700, true); }
function wp_check_filetype_and_ext($path, $name, $mimes) { $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)); $types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png']; return isset($types[$ext]) ? ['ext' => $ext, 'type' => $types[$ext]] : []; }
function nocache_headers() { header('Cache-Control: no-store'); }
require $base . 'class-merchant-role.php';
require $base . 'class-deal-cpt.php';
require $base . 'class-seller-category.php';
require $base . 'class-seller-documents.php';
require $base . 'class-membership.php';
require $base . 'class-merchant-portal.php';
require $base . 'class-voucher.php';
require $base . 'class-eligibility.php';
if (PHP_SAPI === 'cli-server') {
    try {
        switch ($_GET['action'] ?? '') {
            case 'upload': Angebot_Deals_Seller_Documents::handle_upload(); break;
            case 'view': Angebot_Deals_Seller_Documents::handle_view(); break;
            case 'state': header('Content-Type: application/json'); echo json_encode($meta); break;
        }
    } catch (TestRedirect $e) { header('Content-Type: application/json'); echo json_encode(['redirect' => $e->getMessage()]); }
    catch (TestDenied $e) { http_response_code($e->getCode()); echo $e->getMessage(); }
    exit;
}
function expect($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS $message\n"; }
$menu = ['dashboard' => 'Dashboard', 'orders' => 'Orders', 'downloads' => 'Downloads', 'edit-account' => 'Details'];
$seller_menu = Angebot_Deals_Merchant_Portal::account_menu_items(Angebot_Deals_Voucher::account_menu_items(Angebot_Deals_Membership::account_menu_items($menu)));
expect(isset($seller_menu['merchant-portal']) && !isset($seller_menu['membership'], $seller_menu['my-deals']), 'seller menu separates buyer verification and vouchers');
expect(!isset($seller_menu['membership']) && !isset($seller_menu['my-deals']), 'neither buyer tab reappears after later hooks');
expect(str_contains(Angebot_Deals_Merchant_Portal::shortcode(), 'name="business_document"'), 'pending seller can access optional upload');
expect(str_contains(Angebot_Deals_Eligibility::render_form(1), 'name="business_document"'), 'direct buyer verification page shows seller form');
expect(Angebot_Deals_Membership::registration_redirect('/') === '/my-account/merchant-portal', 'seller registration routes to portal');
update_user_meta(1, '_angebot_membership_status', 'verified');
expect(!Angebot_Deals_Membership::can_purchase(1), 'seller role does not bypass buyer purchase restrictions');
$current = 2;
expect(!isset(Angebot_Deals_Merchant_Portal::account_menu_items(Angebot_Deals_Membership::account_menu_items($menu))['merchant-portal']), 'buyer has no seller portal');
expect(isset(Angebot_Deals_Membership::account_menu_items($menu)['membership']), 'buyer keeps Membership');
expect(Angebot_Deals_Membership::registration_redirect('/') === '/my-account/membership', 'buyer registration routes to evidence');
expect(!Angebot_Deals_Membership::can_purchase(2), 'unverified buyer cannot purchase');
update_user_meta(2, '_angebot_membership_status', 'verified');
expect(Angebot_Deals_Membership::can_purchase(2), 'verified buyer may purchase');
try { Angebot_Deals_Seller_Documents::handle_upload(); throw new RuntimeException('buyer upload allowed'); } catch (TestDenied $e) { expect($e->getCode() === 403, 'buyer cannot upload seller evidence'); }
$current = 1;
try { Angebot_Deals_Seller_Documents::handle_upload(); throw new RuntimeException('CSRF allowed'); } catch (TestDenied $e) { expect($e->getCode() === 403, 'seller upload requires nonce'); }
$current = 3;
$_REQUEST['_wpnonce'] = 'test';
$_POST['user_id'] = 1;
Angebot_Deals_Seller_Documents::delete_document(1);
try { Angebot_Deals_Merchant_Portal::handle_merchant_decision(); } catch (TestRedirect $e) {}
expect(Angebot_Deals_Merchant_Portal::is_merchant_approved(1), 'administrator may approve without any document');
expect(count($mails) === 1 && $mails[0]['to'] === 'seller@example.test', 'approval notifies seller');
$current = 1;
$_POST['user_id'] = 4;
try { Angebot_Deals_Merchant_Portal::handle_merchant_decision(); throw new RuntimeException('self approval allowed'); } catch (TestDenied $e) { expect($e->getCode() === 403, 'seller cannot approve another seller'); }
Angebot_Deals_Seller_Documents::notify_registration(1);
expect(count($mails) === 2 && str_contains($mails[1]['body'], 'angebot-merchants'), 'new seller notification links administrator to review');
Angebot_Deals_Seller_Documents::notify_registration(2);
expect(count($mails) === 2, 'buyer registration does not trigger seller review');
echo "All isolated role and approval tests passed.\n";
$current = 1;
$_POST = ['angebot_account_type' => 'merchant'];
$errors = Angebot_Deals_Seller_Category::validate_registration(new WP_Error(), '', '');
expect(in_array('angebot_business_category', $errors->codes, true), 'seller registration requires category');
$_POST['angebot_business_category'] = 999;
expect(count(Angebot_Deals_Seller_Category::validate_registration(new WP_Error(), '', '')->codes) === 1, 'invalid category rejected');
$_POST['angebot_business_category'] = 10;
expect(count(Angebot_Deals_Seller_Category::validate_registration(new WP_Error(), '', '')->codes) === 0, 'valid seller category accepted');
Angebot_Deals_Seller_Category::save_registration(1);
expect(Angebot_Deals_Seller_Category::get(1) === 10, 'category saved against seller account');
expect(Angebot_Deals_Seller_Category::label(1) === 'Food & Drink', 'saved category has correct display label');
$_POST = ['angebot_account_type' => 'customer'];
expect(count(Angebot_Deals_Seller_Category::validate_registration(new WP_Error(), '', '')->codes) === 0, 'buyers do not need business category');
$_POST['angebot_business_category'] = 10;
Angebot_Deals_Seller_Category::save_registration(2);
expect(Angebot_Deals_Seller_Category::get(2) === 0, 'buyer cannot acquire business category through posted field');
Angebot_Deals_Seller_Category::sync_deal(100, [11], [], 'deal_category');
expect(in_array(10, $offer_terms[100]) && in_array(11, $offer_terms[100]), 'business category appended while offer category retained');
expect($offer_terms[102] === [11], 'other seller offers unchanged');
Angebot_Deals_Seller_Category::sync_deal(101, [], [], 'deal_location');
expect($offer_terms[101] === [11], 'location assignment does not trigger category sync');
$current = 4;
$_POST = ['angebot_business_category' => 10];
$_REQUEST['_wpnonce'] = 'test';
try { Angebot_Deals_Seller_Category::handle_save(); } catch (TestRedirect $e) {}
expect(Angebot_Deals_Seller_Category::get(4) === 10 && in_array(10, $offer_terms[102]), 'existing seller category backfills existing offers');
$current = 2;
try { Angebot_Deals_Seller_Category::handle_save(); throw new RuntimeException('buyer category save allowed'); } catch (TestDenied $e) { expect($e->getCode() === 403, 'buyer cannot submit seller category endpoint'); }
echo "All 12 business-category regression checks passed.\n";
