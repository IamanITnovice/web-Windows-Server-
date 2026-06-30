<?php
defined('ABSPATH') || exit;
if (class_exists('WD_Database')) return;

class WD_Database {

    public static function install(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sqls = [];

        $sqls[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}watchdog_hosts (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(100) NOT NULL DEFAULT '',
            machine_id VARCHAR(64) NOT NULL,
            api_key_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ip_last    VARCHAR(45) NOT NULL DEFAULT '',
            os_info    VARCHAR(255) NOT NULL DEFAULT '',
            status     ENUM('online','offline') NOT NULL DEFAULT 'offline',
            last_seen  DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY machine_id (machine_id),
            KEY api_key_id (api_key_id)
        ) $charset;";

        $sqls[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}watchdog_api_keys (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(100) NOT NULL,
            api_key    VARCHAR(64) NOT NULL,
            category   VARCHAR(50) NOT NULL DEFAULT '',
            permissions LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            is_active  TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY api_key (api_key)
        ) $charset;";

        $sqls[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}watchdog_logs (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            host_id    BIGINT UNSIGNED NOT NULL,
            log_type   VARCHAR(32) NOT NULL,
            payload    LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY host_log_type (host_id, log_type),
            KEY created_at (created_at)
        ) $charset;";

        $sqls[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}watchdog_kook_settings (
            id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
            bot_token          TEXT NOT NULL,
            default_channel_id VARCHAR(64) NOT NULL DEFAULT '',
            notify_rules       LONGTEXT NOT NULL,
            updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        $sqls[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}watchdog_cmd_queue (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            host_id    BIGINT UNSIGNED NOT NULL,
            cmd_type   VARCHAR(32) NOT NULL,
            payload    TEXT NOT NULL,
            status     ENUM('pending','sent','ack','failed') NOT NULL DEFAULT 'pending',
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            executed_at DATETIME,
            result     TEXT,
            PRIMARY KEY  (id),
            KEY host_status (host_id, status)
        ) $charset;";

        $sqls[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}watchdog_drop_tokens (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token          VARCHAR(64)  NOT NULL,
            file_url       TEXT         NOT NULL,
            redirect_url   TEXT         NOT NULL DEFAULT '',
            label          VARCHAR(128) NOT NULL DEFAULT '',
            uses           INT UNSIGNED NOT NULL DEFAULT 0,
            max_uses       INT UNSIGNED NOT NULL DEFAULT 1,
            expires_at     DATETIME,
            created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_visit_ip  VARCHAR(45) NOT NULL DEFAULT '',
            last_visit_at  DATETIME,
            dl_count       INT UNSIGNED NOT NULL DEFAULT 0,
            last_dl_at     DATETIME,
            PRIMARY KEY    (id),
            UNIQUE KEY token (token)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sqls as $sql) dbDelta($sql);
        $wpdb->query(
            "INSERT IGNORE INTO {$wpdb->prefix}watchdog_kook_settings (id, bot_token, notify_rules)
             VALUES (1, '', '{}')"
        );
        update_option('watchdog_db_version', WD_DB_VERSION);
    }

    public static function uninstall(): void {
        global $wpdb;
        foreach (['watchdog_hosts','watchdog_api_keys','watchdog_logs','watchdog_kook_settings','watchdog_cmd_queue','watchdog_drop_tokens'] as $t) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$t}");
        }
        delete_option('watchdog_db_version');
    }
}
