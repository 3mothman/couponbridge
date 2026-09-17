<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Angebot_Deals_Voucher
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_REDEEMED  = 'redeemed';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const ENDPOINT = 'my-deals';

    public static function register_hooks(): void
    {
        add_action('woocommerce_order_status_completed', [self::class, 'generate_for_order']);
        add_action('woocommerce_order_status_processing', [self::class, 'generate_for_order']);
        add_action('woocommerce_order_status_cancelled', [self::class, 'cancel_for_order']);
        add_action('woocommerce_order_status_refunded', [self::class, 'cancel_for_order']);
        add_action('angebot_deals_daily_expiry', [self::class, 'expire_outdated']);

        if (!wp_next_scheduled('angebot_deals_daily_expiry')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'angebot_deals_daily_expiry');
        }

        // "My redeemed deals" — a My Account tab beyond WooCommerce's
        // default order history, focused on voucher/redemption status.
        add_action('init', [self::class, 'add_endpoint']);
        add_filter('woocommerce_account_menu_items', [self::class, 'account_menu_items'], 21);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [self::class, 'account_endpoint_content']);
        add_filter('woocommerce_endpoint_' . self::ENDPOINT . '_title', [self::class, 'account_endpoint_title']);
    }

    public static function add_endpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public static function account_menu_items(array $items): array
    {
        $insert = ['my-deals' => __('My Deals', 'angebot-deals')];
        $after  = array_key_exists('membership', $items) ? 'membership' : 'orders';
        $pos    = array_search($after, array_keys($items), true);

        if ($pos === false) {
            return $insert + $items;
        }

        return array_slice($items, 0, $pos + 1, true)
            + $insert
            + array_slice($items, $pos + 1, null, true);
    }

    public static function account_endpoint_title(string $title): string
    {
        return __('My Deals', 'angebot-deals');
    }

    public static function account_endpoint_content(): void
    {
        $vouchers = self::get_for_user(get_current_user_id());
        include ANGEBOT_DEALS_PATH . 'templates/my-deals.php';
    }

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'angebot_vouchers';
    }

    public static function generate_for_order(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->get_meta('_angebot_vouchers_generated') === 'yes') {
            return;
        }

        $created = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = (int) $item->get_product_id();
            $deal_id    = Angebot_Deals_WooCommerce_Sync::get_deal_id_from_product($product_id);
            if (!$deal_id) {
                continue;
            }

            $qty = max(1, (int) $item->get_quantity());
            for ($i = 0; $i < $qty; $i++) {
                $voucher = self::create([
                    'deal_id'         => $deal_id,
                    'product_id'      => $product_id,
                    'order_id'        => $order_id,
                    'order_item_id'   => (int) $item_id,
                    'user_id'         => (int) $order->get_customer_id(),
                    'customer_email'  => $order->get_billing_email(),
                    'customer_name'   => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                    'merchant_user_id'=> (int) Angebot_Deals_Deal_Meta::get($deal_id, 'merchant_user_id', 0),
                    'expires_at'      => self::resolve_expiry($deal_id),
                ]);

                if ($voucher) {
                    $created[] = $voucher;
                }
            }

            $sold = (int) Angebot_Deals_Deal_Meta::get($deal_id, 'sold_count', 0);
            update_post_meta($deal_id, '_angebot_sold_count', $sold + $qty);

            // Refresh WC stock mirror.
            Angebot_Deals_WooCommerce_Sync::sync_product($deal_id, get_post($deal_id));
        }

        if ($created) {
            $order->update_meta_data('_angebot_vouchers_generated', 'yes');
            $order->save();
            do_action('angebot_deals_vouchers_created', $created, $order);
        }
    }

    public static function cancel_for_order(int $order_id): void
    {
        global $wpdb;
        $wpdb->update(
            self::table(),
            ['status' => self::STATUS_CANCELLED],
            [
                'order_id' => $order_id,
                'status'   => self::STATUS_ACTIVE,
            ],
            ['%s'],
            ['%d', '%s']
        );
    }

    public static function create(array $args): ?object
    {
        global $wpdb;

        $code     = self::generate_unique_code();
        $qr_token = self::generate_unique_token();

        $inserted = $wpdb->insert(
            self::table(),
            [
                'code'             => $code,
                'deal_id'          => (int) $args['deal_id'],
                'product_id'       => (int) ($args['product_id'] ?? 0),
                'order_id'         => (int) $args['order_id'],
                'order_item_id'    => (int) ($args['order_item_id'] ?? 0),
                'user_id'          => (int) ($args['user_id'] ?? 0),
                'customer_email'   => sanitize_email((string) $args['customer_email']),
                'customer_name'    => sanitize_text_field((string) ($args['customer_name'] ?? '')),
                'merchant_user_id' => (int) ($args['merchant_user_id'] ?? 0),
                'status'           => self::STATUS_ACTIVE,
                'expires_at'       => $args['expires_at'] ?? null,
                'qr_token'         => $qr_token,
                'created_at'       => current_time('mysql'),
            ],
            ['%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return null;
        }

        return self::get_by_id((int) $wpdb->insert_id);
    }

    public static function generate_unique_code(): string
    {
        global $wpdb;
        $table = self::table();

        do {
            $code = strtoupper(wp_generate_password(4, false, false))
                . '-' . strtoupper(wp_generate_password(4, false, false))
                . '-' . strtoupper(wp_generate_password(4, false, false));
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE code = %s", $code));
        } while ($exists);

        return $code;
    }

    public static function generate_unique_token(): string
    {
        global $wpdb;
        $table = self::table();

        do {
            $token = bin2hex(random_bytes(16));
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE qr_token = %s", $token));
        } while ($exists);

        return $token;
    }

    public static function resolve_expiry(int $deal_id): ?string
    {
        $date = (string) Angebot_Deals_Deal_Meta::get($deal_id, 'voucher_expires', '');
        if ($date === '') {
            return null;
        }
        return $date . ' 23:59:59';
    }

    public static function get_by_id(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
        return $row ?: null;
    }

    public static function get_by_code(string $code): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE code = %s',
            strtoupper(trim($code))
        ));
        return $row ?: null;
    }

    public static function get_by_token(string $token): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE qr_token = %s',
            sanitize_text_field($token)
        ));
        return $row ?: null;
    }

    public static function get_for_order(int $order_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE order_id = %d ORDER BY id ASC',
            $order_id
        )) ?: [];
    }

    public static function get_for_user(int $user_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY created_at DESC',
            $user_id
        )) ?: [];
    }

    public static function get_for_merchant(int $merchant_user_id, array $args = []): array
    {
        global $wpdb;

        $status = $args['status'] ?? '';
        $search = $args['search'] ?? '';
        $limit  = absint($args['limit'] ?? 50);
        $offset = absint($args['offset'] ?? 0);

        $sql    = 'SELECT * FROM ' . self::table() . ' WHERE merchant_user_id = %d';
        $params = [$merchant_user_id];

        if ($status !== '') {
            $sql     .= ' AND status = %s';
            $params[] = $status;
        }

        if ($search !== '') {
            $sql     .= ' AND (code LIKE %s OR customer_email LIKE %s)';
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
    }

    public static function refresh_status(object $voucher): object
    {
        if ($voucher->status === self::STATUS_ACTIVE && $voucher->expires_at) {
            $expires = strtotime($voucher->expires_at . ' ' . wp_timezone_string());
            if ($expires && $expires < time()) {
                self::update_status((int) $voucher->id, self::STATUS_EXPIRED);
                $voucher->status = self::STATUS_EXPIRED;
            }
        }
        return $voucher;
    }

    public static function redeem(int $voucher_id, int $user_id): array
    {
        $voucher = self::get_by_id($voucher_id);
        if (!$voucher) {
            return ['success' => false, 'message' => __('Voucher not found.', 'angebot-deals')];
        }

        $voucher = self::refresh_status($voucher);

        if ($voucher->status === self::STATUS_REDEEMED) {
            return ['success' => false, 'message' => __('This voucher has already been redeemed.', 'angebot-deals')];
        }

        if ($voucher->status === self::STATUS_EXPIRED) {
            return ['success' => false, 'message' => __('This voucher has expired.', 'angebot-deals')];
        }

        if ($voucher->status === self::STATUS_CANCELLED) {
            return ['success' => false, 'message' => __('This voucher is invalid.', 'angebot-deals')];
        }

        if (!user_can($user_id, 'manage_options')) {
            if ((int) $voucher->merchant_user_id !== $user_id) {
                return ['success' => false, 'message' => __('You do not have permission for this voucher.', 'angebot-deals')];
            }
        }

        global $wpdb;
        $wpdb->update(
            self::table(),
            [
                'status'      => self::STATUS_REDEEMED,
                'redeemed_at' => current_time('mysql'),
                'redeemed_by' => $user_id,
            ],
            ['id' => $voucher_id],
            ['%s', '%s', '%d'],
            ['%d']
        );

        return [
            'success' => true,
            'message' => __('Voucher redeemed successfully.', 'angebot-deals'),
            'voucher' => self::get_by_id($voucher_id),
        ];
    }

    public static function update_status(int $id, string $status): void
    {
        global $wpdb;
        $wpdb->update(self::table(), ['status' => $status], ['id' => $id], ['%s'], ['%d']);
    }

    public static function expire_outdated(): void
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::table() . " SET status = %s WHERE status = %s AND expires_at IS NOT NULL AND expires_at < %s",
                self::STATUS_EXPIRED,
                self::STATUS_ACTIVE,
                current_time('mysql')
            )
        );
    }

    public static function status_label(string $status): string
    {
        $labels = [
            self::STATUS_ACTIVE    => __('Active', 'angebot-deals'),
            self::STATUS_REDEEMED  => __('Redeemed', 'angebot-deals'),
            self::STATUS_EXPIRED   => __('Expired', 'angebot-deals'),
            self::STATUS_CANCELLED => __('Cancelled', 'angebot-deals'),
        ];
        return $labels[$status] ?? $status;
    }
}
