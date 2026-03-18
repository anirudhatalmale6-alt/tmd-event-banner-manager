<?php
if (!defined('ABSPATH')) exit;

class TMD_EBM_Cron {
    const SYNC_TRANSIENT = 'tmd_ebm_last_date_sync';

    public function __construct() {
        // Scheduled cron: runs twice daily
        add_action('tmd_ebm_refresh_event', [$this, 'refresh']);

        // Auto-sync on page load (throttled to once per hour)
        add_action('init', [$this, 'maybe_sync_on_load'], 20);
    }

    /**
     * Cron-triggered refresh: full date-based sync.
     */
    public function refresh() {
        TMD_EBM_Slider_Helper::sync_slides_by_date();
        set_transient(self::SYNC_TRANSIENT, time(), HOUR_IN_SECONDS);
    }

    /**
     * Auto-sync on page load, throttled to once per hour.
     * Uses a lightweight transient check to avoid running on every request.
     */
    public function maybe_sync_on_load() {
        // Skip AJAX, REST, and cron requests for performance
        if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $last_sync = get_transient(self::SYNC_TRANSIENT);
        if ($last_sync) {
            return; // Already synced within the last hour
        }

        // Run sync
        TMD_EBM_Slider_Helper::sync_slides_by_date();
        set_transient(self::SYNC_TRANSIENT, time(), HOUR_IN_SECONDS);
    }
}