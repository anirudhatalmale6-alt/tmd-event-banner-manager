<?php
if (!defined('ABSPATH')) exit;

class TMD_EBM_Cron {
    public function __construct() {
        add_action('tmd_ebm_refresh_event', [$this, 'refresh']);
    }

    public function refresh() {
        $event = TMD_EBM_Event_Resolver::get_active_event();
        $slug  = $event['event_slug'] ?? 'default';
        update_option('tmd_current_event_slug', $slug);

        if ($event) {
            TMD_EBM_Slider_Helper::update_master_slider($event);
        }
    }
}
