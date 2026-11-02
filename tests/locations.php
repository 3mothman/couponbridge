<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
$options = ['angebot_locations_seeded' => 1, 'angebot_default_location' => 'berlin'];
$terms = ['berlin' => (object) ['slug' => 'berlin', 'name' => 'Berlin']];
$inserts = 0;
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; }
function sanitize_title($s) { return strtolower(trim($s)); }
function sanitize_key($s) { return strtolower(trim($s)); }
function wp_unslash($s) { return $s; }
function absint($s) { return abs((int) $s); }
function get_term_by($field, $slug, $tax) { return $GLOBALS['terms'][$slug] ?? false; }
function term_exists($s, $tax) { return false; }
function wp_insert_term($name, $tax, $args = []) { $slug = $args['slug'] ?? sanitize_title($name); $GLOBALS['terms'][$slug] = (object) ['slug' => $slug, 'name' => $name]; ++$GLOBALS['inserts']; return ['term_id' => $GLOBALS['inserts']]; }
function is_wp_error($v) { return false; }
function check_ajax_referer(...$args) {}
function wp_send_json_error($data) { throw new RuntimeException($data['message']); }
class WP_Query { public function __construct(public array $args) {} }
require dirname(__DIR__) . '/plugin/angebot-deals (3)/angebot-deals/includes/class-deal-cpt.php';
require dirname(__DIR__) . '/plugin/angebot-deals (3)/angebot-deals/includes/class-location.php';
require dirname(__DIR__) . '/plugin/angebot-deals (3)/angebot-deals/includes/class-shortcodes.php';
function expect($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS $message\n"; }
Angebot_Deals_Location::seed_default_locations();
expect(isset($terms['coventry'], $terms['nuneaton'], $terms['rugby'], $terms['birmingham']), 'all four cities seeded on an existing installation');
expect(isset($terms['berlin']), 'migration preserves old term records');
expect($options['angebot_default_location'] === 'coventry', 'old default replaced by Coventry');
$before = $inserts;
Angebot_Deals_Location::seed_default_locations();
expect($inserts === $before, 'migration is idempotent');
$options['angebot_default_location'] = 'rugby';
$_COOKIE[Angebot_Deals_Location::COOKIE] = 'berlin';
expect(Angebot_Deals_Location::current() === 'rugby', 'obsolete cookie falls back to configured city');
$_COOKIE[Angebot_Deals_Location::COOKIE] = 'birmingham';
expect(Angebot_Deals_Location::current() === 'birmingham', 'supported cookie retained');
expect(Angebot_Deals_Location::sanitize_default('London') === 'coventry', 'unsupported settings sanitized');
$args = Angebot_Deals_Location::filter_city_choices(['hide_empty' => false], ['deal_location']);
expect($args['slug'] === ['coventry', 'nuneaton', 'rugby', 'birmingham'], 'city choices restricted to four cities');
expect(Angebot_Deals_Location::filter_city_choices(['object_ids' => [1]], ['deal_location']) === ['object_ids' => [1]], 'existing object relationships remain readable');
expect(Angebot_Deals_Location::filter_city_choices([], ['deal_category']) === [], 'categories unaffected');
$_POST['location'] = 'berlin';
try { Angebot_Deals_Location::ajax_set(); throw new LogicException('unsupported city accepted'); } catch (RuntimeException $e) { expect($e->getMessage() === 'unsupported_location', 'AJAX rejects unsupported city'); }
$query = Angebot_Deals_Shortcodes::query_deals(['location' => 'all']);
expect($query->args['tax_query'][0]['terms'] === array_keys(Angebot_Deals_Location::CITIES), 'All cities query remains inside service area');
$query = Angebot_Deals_Shortcodes::query_deals(['location' => 'berlin']);
expect($query->args['tax_query'][0]['terms'] === 'coventry', 'unsupported shortcode query uses safe default');
echo "All 13 location regression checks passed.\n";
