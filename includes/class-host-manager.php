<?php
defined('ABSPATH') || exit;
if (class_exists('WD_Host_Manager')) return;

class WD_Host_Manager {

    public static function register(string $machine_id, int $api_key_id, string $ip, string $os_info, string $name = ''): int {
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}watchdog_hosts WHERE machine_id=%s", $machine_id
        ));
        if ($existing) {
            $wpdb->update("{$wpdb->prefix}watchdog_hosts",
                ['ip_last' => $ip, 'os_info' => $os_info, 'status' => 'online', 'last_seen' => current_time('mysql'), 'api_key_id' => $api_key_id],
                ['id' => $existing], ['%s','%s','%s','%s','%d'], ['%d']
            );
            return (int)$existing;
        }
        $wpdb->insert("{$wpdb->prefix}watchdog_hosts", [
            'name' => $name ?: $machine_id, 'machine_id' => $machine_id,
            'api_key_id' => $api_key_id, 'ip_last' => $ip,
            'os_info' => $os_info, 'status' => 'online', 'last_seen' => current_time('mysql'),
        ], ['%s','%s','%d','%s','%s','%s','%s']);
        return (int)$wpdb->insert_id;
    }

    public static function heartbeat(int $host_id, string $ip): void {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}watchdog_hosts",
            ['status' => 'online', 'last_seen' => current_time('mysql'), 'ip_last' => $ip],
            ['id' => $host_id], ['%s','%s','%s'], ['%d']
        );
    }

    public static function mark_offline_stale(): void {
        global $wpdb;
        // 客户端每 30 秒报一次心跳，超时设 60 秒（两倍间隔），网络抖一下也能扛住
        // 机器真跪了的话，最多 60 秒就能发现它离线了，反应还算及时吧
        $wpdb->query("UPDATE {$wpdb->prefix}watchdog_hosts SET status='offline'
                      WHERE status='online' AND last_seen < DATE_SUB(NOW(), INTERVAL 60 SECOND)");
    }

    public static function get_list(int $page = 1, int $per_page = 20, string $status = '', ?array $allowed_ids = null): array {
        global $wpdb;
        $offset = ($page - 1) * $per_page;
        $conds  = [];
        if ($status) $conds[] = $wpdb->prepare('status=%s', $status);
        if ($allowed_ids !== null) {
            if (empty($allowed_ids)) {
                // 白名单是空数组？那就一台主机都看不了，老老实实找管理员开权限吧
                return ['items' => [], 'total' => 0];
            }
            $ids_in = implode(',', array_map('intval', $allowed_ids));
            $conds[] = "id IN ($ids_in)";
        }
        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
        $rows  = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}watchdog_hosts $where ORDER BY last_seen DESC LIMIT $per_page OFFSET $offset", ARRAY_A);
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}watchdog_hosts $where");
        return ['items' => $rows, 'total' => $total];
    }

    public static function get_all_simple(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT id, name, status FROM {$wpdb->prefix}watchdog_hosts ORDER BY last_seen DESC", ARRAY_A) ?: [];
    }

    public static function get_by_id(int $id): ?array {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}watchdog_hosts WHERE id=%d", $id), ARRAY_A) ?: null;
    }

    public static function update_name(int $id, string $name): void {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}watchdog_hosts", ['name' => $name], ['id' => $id], ['%s'], ['%d']);
    }

    public static function delete(int $id): void {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}watchdog_hosts",     ['id' => $id],      ['%d']);
        $wpdb->delete("{$wpdb->prefix}watchdog_logs",      ['host_id' => $id], ['%d']);
        $wpdb->delete("{$wpdb->prefix}watchdog_cmd_queue", ['host_id' => $id], ['%d']);
    }
}

add_action('wd_cron_mark_offline', ['WD_Host_Manager', 'mark_offline_stale']);
if (!wp_next_scheduled('wd_cron_mark_offline')) wp_schedule_event(time(), 'every_minute', 'wd_cron_mark_offline');
add_filter('cron_schedules', function ($s) { $s['every_minute'] = ['interval' => 60, 'display' => 'Every Minute']; return $s; });
