<?php
if (!defined('ABSPATH')) exit;

class TMD_EBM_Event_Resolver {
    public static function get_active_event() {
        global $wpdb;
        $table = TMD_EBM_TABLE;
        $today = current_time('Y-m-d');

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE is_active = 1
                   AND start_date <= %s
                   AND end_date >= %s
                 ORDER BY priority ASC, start_date ASC
                 LIMIT 1",
                $today,
                $today
            ),
            ARRAY_A
        );
    }

    public static function get_current_event_slug() {
        $cached = get_option('tmd_current_event_slug', '');
        if ($cached) {
            return $cached;
        }

        $event = self::get_active_event();
        return $event['event_slug'] ?? 'default';
    }
}
