<?php
if (!defined('ABSPATH')) exit;

class TMD_EBM_Product_Sync {
    public function __construct() {
        add_action('wp_head', [$this, 'output_current_event_slug']);
    }

    public function output_current_event_slug() {
        $slug = TMD_EBM_Event_Resolver::get_current_event_slug();
        echo '<div id="tmd-current-event" data-event="' . esc_attr($slug) . '"></div>';
    }
}
