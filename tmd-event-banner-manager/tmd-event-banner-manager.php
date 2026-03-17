<?php
/**
 * Plugin Name: TMD Event Banner Manager
 * Description: Backend event interface + event resolver + product sync + Slider Revolution master-template automation hooks for seasonal banners.
 * Version: 1.1.0
 * Author: Naeem Sufi
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: tmd-event-banner-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TMD_EBM_VERSION', '1.1.0');
define('TMD_EBM_FILE', __FILE__);
define('TMD_EBM_PATH', plugin_dir_path(__FILE__));
define('TMD_EBM_URL', plugin_dir_url(__FILE__));
define('TMD_EBM_TABLE', $GLOBALS['wpdb']->prefix . 'tmd_event_banners');

require_once TMD_EBM_PATH . 'includes/class-tmd-ebm-activator.php';
require_once TMD_EBM_PATH . 'includes/class-tmd-ebm-admin.php';
require_once TMD_EBM_PATH . 'includes/class-tmd-ebm-event-resolver.php';
require_once TMD_EBM_PATH . 'includes/class-tmd-ebm-slider-helper.php';
require_once TMD_EBM_PATH . 'includes/class-tmd-ebm-cron.php';
require_once TMD_EBM_PATH . 'includes/class-tmd-ebm-product-sync.php';

register_activation_hook(__FILE__, ['TMD_EBM_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['TMD_EBM_Activator', 'deactivate']);

add_action('plugins_loaded', function () {
    if (is_admin()) {
        new TMD_EBM_Admin();
    }

    new TMD_EBM_Cron();
    new TMD_EBM_Product_Sync();
});

// Hide Rev Slider image_lists elements that can show as broken icons
add_action('wp_head', function () {
    echo '<style>image_lists,image_lists img{display:none!important;visibility:hidden!important;width:0!important;height:0!important;position:absolute!important;overflow:hidden!important}</style>' . "\n";
}, 1);
