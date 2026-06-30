<?php
defined('ABSPATH') || exit;
if (class_exists('WD_Auth')) return;

class WD_Auth {

    public static function verify_request(WP_REST_Request $request): array|WP_Error {
        $key = $request->get_header('X-WatchDog-Key');
        if (empty($key)) return new WP_Error('missing_key', 'Missing X-WatchDog-Key header', ['status' => 401]);
        return self::find_active_key($key);
    }

    public static function find_active_key(string $key): array|WP_Error {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}watchdog_api_keys WHERE api_key=%s AND is_active=1", $key),
            ARRAY_A
        );
        return $row ?: new WP_Error('invalid_key', 'Invalid or disabled API key', ['status' => 403]);
    }

    public static function generate(): string { return bin2hex(random_bytes(32)); }

    public static function create(string $name, string $category = '', array $permissions = []): array|WP_Error {
        global $wpdb;
        if (!$name) return new WP_Error('empty_name', 'Name is required');
        $key    = self::generate();
        $result = $wpdb->insert("{$wpdb->prefix}watchdog_api_keys", [
            'name' => $name, 'api_key' => $key,
            'category' => $category, 'permissions' => wp_json_encode($permissions),
            'created_by' => get_current_user_id(),
        ], ['%s','%s','%s','%s','%d']);
        return $result ? ['id' => $wpdb->insert_id, 'api_key' => $key]
                       : new WP_Error('db_error', 'Failed to create API key');
    }

    public static function generate_ws_token(int $host_id): string {
        $payload = $host_id . '|' . (time() + 300) . '|' . wp_generate_password(16, false);
        return base64_encode($payload . '|' . hash_hmac('sha256', $payload, self::ws_secret()));
    }

    public static function verify_ws_token(string $token): int|false {
        $d = base64_decode($token, true);
        if (!$d) return false;
        $p = explode('|', $d);
        if (count($p) !== 4) return false;
        [$hid, $exp, $nonce, $sig] = $p;
        if ((int)$exp < time()) return false;
        $payload = $hid . '|' . $exp . '|' . $nonce;
        if (!hash_equals(hash_hmac('sha256', $payload, self::ws_secret()), $sig)) return false;
        return (int)$hid;
    }

    private static function ws_secret(): string {
        $s = get_option('watchdog_ws_secret');
        if (!$s) { $s = bin2hex(random_bytes(32)); update_option('watchdog_ws_secret', $s); }
        return $s;
    }
}
