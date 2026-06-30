<?php
defined('ABSPATH') || exit;
if (class_exists('WD_Kook')) return;

class WD_Kook {
    private const API_BASE = 'https://www.kookapp.cn/api/v3';

    /** 发送频道消息（KMarkdown type=9） */
    public static function send(string $channel_id, string $markdown): array|WP_Error {
        $token = self::get_token();
        if (!$token) return new WP_Error('no_token', 'KOOK Bot Token 未配置');
        $response = wp_remote_post(self::API_BASE . '/message/create', [
            'headers'   => [
                'Authorization' => 'Bot ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'      => wp_json_encode([
                'type'      => 9,
                'target_id' => $channel_id,
                'content'   => $markdown,
            ]),
            'timeout'   => 15,
            'sslverify' => false,
        ]);
        if (is_wp_error($response)) return $response;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http = (int) wp_remote_retrieve_response_code($response);
        if ($http !== 200) {
            return new WP_Error('http_' . $http,
                "KOOK HTTP {$http}：" . ($body['message'] ?? '未知错误'));
        }
        if (($body['code'] ?? -1) !== 0) {
            return new WP_Error('kook_' . ($body['code'] ?? 'err'),
                "KOOK 错误 {$body['code']}：" . ($body['message'] ?? '未知'));
        }
        return $body;
    }

    /** 验证 Token，返回机器人信息或 WP_Error */
    public static function check_bot(): array|WP_Error {
        $token = self::get_token();
        if (!$token) return new WP_Error('no_token', 'Bot Token 未配置');
        $response = wp_remote_get(self::API_BASE . '/user/me', [
            'headers'   => ['Authorization' => 'Bot ' . $token],
            'timeout'   => 10,
            'sslverify' => false,
        ]);
        if (is_wp_error($response)) return $response;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http = (int) wp_remote_retrieve_response_code($response);
        if ($http !== 200) {
            return new WP_Error('http_' . $http,
                "HTTP {$http}：" . ($body['message'] ?? '请检查 Token 是否正确'));
        }
        if (($body['code'] ?? -1) !== 0) {
            return new WP_Error('kook_err',
                "Token 无效（{$body['code']}）：" . ($body['message'] ?? ''));
        }
        return $body['data'] ?? [];
    }

    public static function notify(string $event_type, string $markdown): void {
        $rules   = self::get_rules();
        $default = self::get_default_channel();

        if (!empty($rules)) {
            // 用户自己配了规则 → 那就按规矩来，不该推的不推
            $rule = $rules[$event_type] ?? null;
            if (!$rule || !($rule['enabled'] ?? false)) return;
            $channel = !empty($rule['channel_id']) ? $rule['channel_id'] : $default;
        } else {
            // 还没配规则？只要有默认频道就先推着，但那些刷屏级别的事件得拦一下
            if (in_array($event_type, ['process_start'], true)) return;
            $channel = $default;
        }

        if ($channel) self::send($channel, $markdown);
    }

    public static function notify_host_online(array $host): void {
        self::notify('host_status', ":white_check_mark: **[WatchDog]** 主机 `{$host['name']}` 已**上线** > IP：{$host['ip_last']}");
    }

    public static function notify_host_offline(array $host): void {
        self::notify('host_status', ":red_circle: **[WatchDog]** 主机 `{$host['name']}` 已**离线**");
    }

    public static function notify_log(string $type, string $host, string $content): void {
        self::notify($type, ":bell: **[WatchDog / {$host}]** {$content}");
    }

    public static function get_settings(): array {
        global $wpdb;
        return $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}watchdog_kook_settings WHERE id=1", ARRAY_A
        ) ?? [];
    }

    public static function save_settings(string $token, string $default_channel, array $rules): void {
        global $wpdb;
        $wpdb->replace("{$wpdb->prefix}watchdog_kook_settings", [
            'id'                 => 1,
            'bot_token'          => $token ? self::encrypt($token)
                                           : (self::get_settings()['bot_token'] ?? ''),
            'default_channel_id' => $default_channel,
            'notify_rules'       => wp_json_encode($rules),
        ], ['%d','%s','%s','%s']);
    }

    private static function get_token(): string {
        $s = self::get_settings();
        return empty($s['bot_token']) ? '' : self::decrypt($s['bot_token']);
    }

    private static function get_default_channel(): string {
        return self::get_settings()['default_channel_id'] ?? '';
    }

    private static function get_rules(): array {
        return json_decode(self::get_settings()['notify_rules'] ?? '{}', true) ?? [];
    }

    private static function encrypt(string $v): string {
        $k  = wp_salt('auth');
        $iv = random_bytes(16);
        return base64_encode($iv . openssl_encrypt($v, 'aes-256-cbc', $k, 0, $iv));
    }

    private static function decrypt(string $v): string {
        $k = wp_salt('auth');
        $d = base64_decode($v);
        return openssl_decrypt(substr($d, 16), 'aes-256-cbc', $k, 0, substr($d, 0, 16)) ?: '';
    }
}
