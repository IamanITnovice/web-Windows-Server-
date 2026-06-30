<?php
defined('ABSPATH') || exit;
if (class_exists('WD_Log_Manager')) return;

class WD_Log_Manager {

    public static function batch_insert(int $host_id, array $entries): int {
        global $wpdb;
        if (empty($entries)) return 0;
        $values = []; $placeholders = []; $now = current_time('mysql');
        foreach ($entries as $entry) {
            $type    = sanitize_key($entry['log_type'] ?? '');
            $payload = wp_json_encode($entry['payload'] ?? $entry);
            $ts      = sanitize_text_field($entry['created_at'] ?? $now);
            if (!$type) continue;
            $placeholders[] = '(%d,%s,%s,%s,%s)';
            array_push($values, $host_id, $type, $payload, $ts, $now);
        }
        if (empty($placeholders)) return 0;
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}watchdog_logs (host_id,log_type,payload,created_at,received_at) VALUES " . implode(',', $placeholders),
            $values
        ));
        return $wpdb->rows_affected;
    }

    public static function query(array $args = []): array {
        global $wpdb;
        $host_id  = isset($args['host_id'])  ? (int)$args['host_id']        : 0;
        $log_type = isset($args['log_type']) ? sanitize_key($args['log_type']) : '';
        $df       = $args['date_from'] ?? '';
        $dt       = $args['date_to']   ?? '';
        $page     = max(1, (int)($args['page']     ?? 1));
        $per      = min(200, max(1, (int)($args['per_page'] ?? 50)));
        $offset   = ($page - 1) * $per;

        $conditions = []; $params = [];
        if ($host_id)  { $conditions[] = 'host_id=%d';    $params[] = $host_id; }
        if ($log_type) { $conditions[] = 'log_type=%s';   $params[] = $log_type; }
        if ($df)       { $conditions[] = 'created_at>=%s'; $params[] = $df; }
        if ($dt)       { $conditions[] = 'created_at<=%s'; $params[] = $dt; }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}watchdog_logs $where", ...$params));
        $rows  = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}watchdog_logs $where ORDER BY created_at DESC LIMIT %d OFFSET %d", ...[...$params, $per, $offset]),
            ARRAY_A
        );
        foreach ($rows as &$row) $row['payload'] = json_decode($row['payload'], true);
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per];
    }
}
