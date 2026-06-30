<?php
defined('ABSPATH') || exit;
if (class_exists('WD_API')) return;

class WD_API {

    public static function register_routes(): void {
        $ns = 'watchdog/v1';

        register_rest_route($ns, '/register',                ['methods' => 'POST', 'callback' => [self::class,'handle_register'],          'permission_callback' => '__return_true']);
        register_rest_route($ns, '/heartbeat',               ['methods' => 'POST', 'callback' => [self::class,'handle_heartbeat'],          'permission_callback' => '__return_true']);
        register_rest_route($ns, '/logs/batch',              ['methods' => 'POST', 'callback' => [self::class,'handle_logs_batch'],          'permission_callback' => '__return_true']);
        register_rest_route($ns, '/commands/pending',        ['methods' => 'GET',  'callback' => [self::class,'handle_commands_pending'],    'permission_callback' => '__return_true']);
        register_rest_route($ns, '/commands/(?P<id>\d+)/ack',['methods' => 'POST', 'callback' => [self::class,'handle_command_ack'],         'permission_callback' => '__return_true']);
        register_rest_route($ns, '/screen/token',            ['methods' => 'POST', 'callback' => [self::class,'handle_screen_token'],        'permission_callback' => '__return_true']);
        register_rest_route($ns, '/ws-auth',                 ['methods' => 'POST', 'callback' => [self::class,'handle_ws_auth'],            'permission_callback' => '__return_true']);
    }

    public static function handle_register(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $key_row = WD_Auth::verify_request($r);
        if (is_wp_error($key_row)) return $key_row;
        $machine_id = sanitize_text_field($r->get_param('machine_id'));
        if (!$machine_id) return new WP_Error('missing_param','machine_id required',['status'=>400]);
        $host_id = WD_Host_Manager::register($machine_id,(int)$key_row['id'],self::ip($r),sanitize_text_field($r->get_param('os_info')??''),sanitize_text_field($r->get_param('name')??''));
        $host = WD_Host_Manager::get_by_id($host_id);
        WD_Kook::notify_host_online($host);
        return new WP_REST_Response(['host_id'=>$host_id,'status'=>'ok'],200);
    }

    public static function handle_heartbeat(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $key_row = WD_Auth::verify_request($r);
        if (is_wp_error($key_row)) return $key_row;
        $host_id = (int)$r->get_param('host_id');
        if (!$host_id) return new WP_Error('missing_param','host_id required',['status'=>400]);
        WD_Host_Manager::heartbeat($host_id, self::ip($r));
        return new WP_REST_Response(['status'=>'ok','server_time'=>current_time('mysql')],200);
    }

    public static function handle_logs_batch(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $key_row = WD_Auth::verify_request($r);
        if (is_wp_error($key_row)) return $key_row;
        $host_id = (int)$r->get_param('host_id');
        $entries = $r->get_param('logs');
        if (!$host_id || !is_array($entries)) return new WP_Error('missing_param','host_id and logs[] required',['status'=>400]);
        $inserted = WD_Log_Manager::batch_insert($host_id, $entries);
        self::process_kook($host_id, $entries);
        return new WP_REST_Response(['inserted'=>$inserted],200);
    }

    public static function handle_commands_pending(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $key_row = WD_Auth::verify_request($r);
        if (is_wp_error($key_row)) return $key_row;
        $host_id = (int)$r->get_param('host_id');
        if (!$host_id) return new WP_Error('missing_param','host_id required',['status'=>400]);
        global $wpdb;
        $cmds = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}watchdog_cmd_queue WHERE host_id=%d AND status='pending' ORDER BY created_at ASC LIMIT 10", $host_id
        ),ARRAY_A);
        foreach ($cmds as $c) $wpdb->update("{$wpdb->prefix}watchdog_cmd_queue",['status'=>'sent'],['id'=>$c['id']],['%s'],['%d']);
        return new WP_REST_Response(['commands'=>$cmds],200);
    }

    public static function handle_command_ack(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $key_row = WD_Auth::verify_request($r);
        if (is_wp_error($key_row)) return $key_row;
        $cmd_id  = (int)$r->get_url_params()['id'];
        $success = (bool)$r->get_param('success');
        $result  = sanitize_textarea_field($r->get_param('result') ?? '');
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}watchdog_cmd_queue",
            ['status' => $success ? 'ack' : 'failed', 'result' => $result, 'executed_at' => current_time('mysql')],
            ['id' => $cmd_id], ['%s','%s','%s'], ['%d']
        );
        // Allow functions.php (KOOK relay / notifications) to react
        do_action('wd_command_completed', $cmd_id, $result, $success);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    /** WS Relay 那边调这个接口来校验浏览器传过来的 Token，过没过期一查便知 */
    public static function handle_ws_auth(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $token = $r->get_param('token');
        if (!$token) return new WP_Error('missing_token', 'token required', ['status' => 400]);
        $host_id = WD_Auth::verify_ws_token($token);
        if (!$host_id) return new WP_Error('invalid_token', 'Invalid or expired token', ['status' => 401]);
        return new WP_REST_Response(['host_id' => $host_id], 200);
    }

    public static function handle_screen_token(WP_REST_Request $r): WP_REST_Response|WP_Error {
        if (!wd_is_logged_in()) {
            $k = WD_Auth::verify_request($r);
            if (is_wp_error($k)) return new WP_Error('unauthorized','Auth required',['status'=>401]);
        }
        $host_id = (int)$r->get_param('host_id');
        if (!$host_id) return new WP_Error('missing_param','host_id required',['status'=>400]);
        return new WP_REST_Response(['token'=>WD_Auth::generate_ws_token($host_id),'host_id'=>$host_id],200);
    }

    private static function ip(WP_REST_Request $r): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) return sanitize_text_field(explode(',',$_SERVER[$h])[0]);
        }
        return '';
    }

    private static function process_kook(int $host_id, array $entries): void {
        $host = WD_Host_Manager::get_by_id($host_id);
        if (!$host) return;
        foreach ($entries as $e) {
            $payload = $e['payload'] ?? [];
            switch ($e['log_type'] ?? '') {
                case 'login':
                    $user = $payload['username'] ?? '未知';
                    $ip   = $payload['ip']       ?? '未知';
                    WD_Kook::notify_log('login', $host['name'], "新登录：用户 `{$user}` IP `{$ip}`");
                    break;
                case 'process_start':
                    $name = $payload['name'] ?? ($e['payload'] ?? '?');
                    WD_Kook::notify_log('process_start', $host['name'], "进程启动：`{$name}`");
                    break;
                case 'process_kill':
                    $name = $payload['name'] ?? '?';
                    WD_Kook::notify_log('process_kill', $host['name'], "进程终止：`{$name}`");
                    break;
                case 'clipboard':
                    $text = is_array($payload) ? ($payload['text'] ?? '') : (string)$payload;
                    WD_Kook::notify_log('clipboard', $host['name'], '剪贴板变化：`' . mb_substr($text, 0, 200) . '`');
                    break;
                case 'keyboard':
                    $text = is_array($payload) ? ($payload['text'] ?? '') : (string)$payload;
                    WD_Kook::notify_log('keyboard', $host['name'], "键盘摘要：\n```\n" . mb_substr($text, 0, 400) . "\n```");
                    break;
                case 'screenshot':
                    $url = is_array($payload) ? ($payload['url'] ?? '') : '';
                    $msg = $url ? "截图捕获：[查看]({$url})" : '截图已捕获';
                    WD_Kook::notify_log('screenshot', $host['name'], $msg);
                    break;
                case 'file_op':
                    $op   = $payload['op']   ?? '?';
                    $path = $payload['path']  ?? '?';
                    WD_Kook::notify_log('file_op', $host['name'], "文件操作：`{$op}` → `{$path}`");
                    break;
                case 'registry_mod':
                    $key = $payload['key'] ?? '?';
                    $val = $payload['value'] ?? '';
                    WD_Kook::notify_log('registry_mod', $host['name'], "注册表修改：`{$key}` = `{$val}`");
                    break;
                case 'winuser_mod':
                    $act  = $payload['action'] ?? '?';
                    $user = $payload['username'] ?? '?';
                    WD_Kook::notify_log('winuser_mod', $host['name'], "Win 用户变更：{$act} `{$user}`");
                    break;
            }
        }
    }
}
