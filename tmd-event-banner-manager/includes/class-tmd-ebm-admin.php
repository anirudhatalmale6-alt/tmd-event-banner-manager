<?php
if (!defined('ABSPATH')) exit;

class TMD_EBM_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_post_tmd_ebm_save_event', [$this, 'save_event']);
        add_action('admin_post_tmd_ebm_run_update', [$this, 'run_update']);
        add_action('admin_post_tmd_ebm_delete_event', [$this, 'delete_event']);
    }

    public function menu() {
        add_menu_page(
            'Event Banner Manager',
            'Event Banner Manager',
            'manage_options',
            'tmd-ebm',
            [$this, 'render'],
            'dashicons-images-alt2',
            58
        );
    }

    public function assets($hook) {
        if ($hook !== 'toplevel_page_tmd-ebm') return;
        wp_enqueue_style('tmd-ebm-admin', TMD_EBM_URL . 'assets/admin.css', [], TMD_EBM_VERSION);
        wp_enqueue_script('tmd-ebm-admin', TMD_EBM_URL . 'assets/admin.js', ['jquery'], TMD_EBM_VERSION, true);
        wp_enqueue_media();
    }

    public function render() {
        global $wpdb;
        $table = TMD_EBM_TABLE;
        $events = $wpdb->get_results("SELECT * FROM {$table} ORDER BY start_date DESC, priority ASC LIMIT 50", ARRAY_A);
        $master_alias = get_option(TMD_EBM_Slider_Helper::MASTER_ALIAS_OPTION, 'MASTER_EVENT_TEMPLATE');
        $edit_event = null;
        if (!empty($_GET['edit'])) {
            $edit_id = intval($_GET['edit']);
            $edit_event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $edit_id), ARRAY_A);
        }
        $active_slug = get_option('tmd_current_event_slug', '');

        // Fetch all slides from the Banner slider for the overview panel
        $banner_slides = [];
        $slider_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}revslider_sliders WHERE alias = 'banner' LIMIT 1");
        if ($slider_id) {
            $slides_table = $wpdb->prefix . 'revslider_slides7';
            if (!$wpdb->get_var("SHOW TABLES LIKE '{$slides_table}'")) {
                $slides_table = $wpdb->prefix . 'revslider_slides';
            }
            $raw_slides = $wpdb->get_results($wpdb->prepare(
                "SELECT id, slide_order, params, layers FROM {$slides_table} WHERE slider_id = %d ORDER BY slide_order ASC",
                $slider_id
            ), ARRAY_A);
            foreach ($raw_slides as $rs) {
                $p = json_decode($rs['params'], true) ?: [];
                $l = json_decode($rs['layers'], true) ?: [];
                $event_slug = $p['tmd_event_slug'] ?? '';
                $title = $p['title'] ?? 'Untitled';
                $is_global = !empty($p['global']);
                // Try to get background image from layers
                $bg_url = '';
                foreach ($l as $layer) {
                    if (isset($layer['subtype']) && $layer['subtype'] === 'slidebg') {
                        $bg_url = $layer['bg']['image']['src'] ?? '';
                        break;
                    }
                }
                // Also check params thumb
                if (!$bg_url && !empty($p['thumb']['default']['image']['src'])) {
                    $bg_url = str_replace('\\/', '/', $p['thumb']['default']['image']['src']);
                }
                $banner_slides[] = [
                    'id' => (int)$rs['id'],
                    'order' => (int)$rs['slide_order'],
                    'title' => $title,
                    'event_slug' => $event_slug,
                    'is_global' => $is_global,
                    'bg_url' => $bg_url,
                    'layer_count' => count($l),
                ];
            }
        }

        include TMD_EBM_PATH . 'templates/admin-page.php';
    }

    public function delete_event() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('tmd_ebm_delete_event');
        global $wpdb;
        $event_id = intval($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            // Get the event slug before deleting so we can remove its slider slide
            $event = $wpdb->get_row($wpdb->prepare(
                "SELECT event_slug FROM " . TMD_EBM_TABLE . " WHERE id = %d",
                $event_id
            ), ARRAY_A);
            if ($event) {
                TMD_EBM_Slider_Helper::remove_event_slide($event['event_slug']);
                // Clear current event if this was it
                $current = get_option('tmd_current_event_slug', '');
                if ($current === $event['event_slug']) {
                    update_option('tmd_current_event_slug', 'default');
                }
            }
            $wpdb->delete(TMD_EBM_TABLE, ['id' => $event_id], ['%d']);
        }
        wp_safe_redirect(admin_url('admin.php?page=tmd-ebm&deleted=1'));
        exit;
    }

    private function sanitize_color($value, $default = '') {
        $value = trim((string) $value);
        if ($value === '') return $default;
        return preg_match('/^#[A-Fa-f0-9]{6}$/', $value) ? $value : $default;
    }

    public function save_event() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('tmd_ebm_save_event');

        global $wpdb;
        $table = TMD_EBM_TABLE;

        $data = [
            'event_name' => sanitize_text_field($_POST['event_name'] ?? ''),
            'event_slug' => sanitize_title($_POST['event_slug'] ?? ''),
            'banner_type' => sanitize_text_field($_POST['banner_type'] ?? 'event'),
            'phase' => sanitize_text_field($_POST['phase'] ?? 'main'),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
            'priority' => intval($_POST['priority'] ?? 10),
            'headline' => sanitize_text_field($_POST['headline'] ?? ''),
            'subheadline' => sanitize_text_field($_POST['subheadline'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'discount_text' => sanitize_text_field($_POST['discount_text'] ?? ''),
            'button_text' => sanitize_text_field($_POST['button_text'] ?? ''),
            'button_link' => esc_url_raw($_POST['button_link'] ?? ''),
            'background_image_id' => intval($_POST['background_image_id'] ?? 0),
            'background_image_url' => esc_url_raw($_POST['background_image_url'] ?? ''),
            'countdown_date' => sanitize_text_field($_POST['countdown_date'] ?? ''),
            'canvas_width' => intval($_POST['canvas_width'] ?? 1200),
            'canvas_height' => intval($_POST['canvas_height'] ?? 400),
            'overlay_color' => $this->sanitize_color($_POST['overlay_color'] ?? '', '#000000'),
            'overlay_opacity' => floatval($_POST['overlay_opacity'] ?? 0.35),
            'eyebrow_text' => sanitize_text_field($_POST['eyebrow_text'] ?? ''),
            'eyebrow_font_family' => sanitize_text_field($_POST['eyebrow_font_family'] ?? ''),
            'eyebrow_font_size_desktop' => intval($_POST['eyebrow_font_size_desktop'] ?? 16),
            'eyebrow_font_size_tablet' => intval($_POST['eyebrow_font_size_tablet'] ?? 14),
            'eyebrow_font_size_mobile' => intval($_POST['eyebrow_font_size_mobile'] ?? 12),
            'eyebrow_font_weight' => sanitize_text_field($_POST['eyebrow_font_weight'] ?? ''),
            'eyebrow_color' => $this->sanitize_color($_POST['eyebrow_color'] ?? '', '#FFD400'),
            'eyebrow_x' => intval($_POST['eyebrow_x'] ?? 80),
            'eyebrow_y' => intval($_POST['eyebrow_y'] ?? 70),
            'headline_font_family' => sanitize_text_field($_POST['headline_font_family'] ?? ''),
            'headline_font_size_desktop' => intval($_POST['headline_font_size_desktop'] ?? 58),
            'headline_font_size_tablet' => intval($_POST['headline_font_size_tablet'] ?? 42),
            'headline_font_size_mobile' => intval($_POST['headline_font_size_mobile'] ?? 32),
            'headline_font_weight' => sanitize_text_field($_POST['headline_font_weight'] ?? ''),
            'headline_color' => $this->sanitize_color($_POST['headline_color'] ?? '', '#FFFFFF'),
            'headline_x' => intval($_POST['headline_x'] ?? 80),
            'headline_y' => intval($_POST['headline_y'] ?? 110),
            'subheadline_font_family' => sanitize_text_field($_POST['subheadline_font_family'] ?? ''),
            'subheadline_font_size_desktop' => intval($_POST['subheadline_font_size_desktop'] ?? 22),
            'subheadline_font_size_tablet' => intval($_POST['subheadline_font_size_tablet'] ?? 18),
            'subheadline_font_size_mobile' => intval($_POST['subheadline_font_size_mobile'] ?? 16),
            'subheadline_font_weight' => sanitize_text_field($_POST['subheadline_font_weight'] ?? ''),
            'subheadline_color' => $this->sanitize_color($_POST['subheadline_color'] ?? '', '#FFFFFF'),
            'subheadline_x' => intval($_POST['subheadline_x'] ?? 80),
            'subheadline_y' => intval($_POST['subheadline_y'] ?? 190),
            'discount_font_family' => sanitize_text_field($_POST['discount_font_family'] ?? ''),
            'discount_font_size' => intval($_POST['discount_font_size'] ?? 16),
            'discount_font_size_tablet' => intval($_POST['discount_font_size_tablet'] ?? 14),
            'discount_font_size_mobile' => intval($_POST['discount_font_size_mobile'] ?? 12),
            'discount_font_weight' => sanitize_text_field($_POST['discount_font_weight'] ?? ''),
            'discount_text_color' => $this->sanitize_color($_POST['discount_text_color'] ?? '', '#FFFFFF'),
            'discount_bg_color' => $this->sanitize_color($_POST['discount_bg_color'] ?? '', '#FF5A36'),
            'discount_border_radius' => intval($_POST['discount_border_radius'] ?? 20),
            'discount_x' => intval($_POST['discount_x'] ?? 80),
            'discount_y' => intval($_POST['discount_y'] ?? 245),
            'button_font_family' => sanitize_text_field($_POST['button_font_family'] ?? ''),
            'button_font_size' => intval($_POST['button_font_size'] ?? 17),
            'button_font_size_tablet' => intval($_POST['button_font_size_tablet'] ?? 15),
            'button_font_size_mobile' => intval($_POST['button_font_size_mobile'] ?? 14),
            'button_font_weight' => sanitize_text_field($_POST['button_font_weight'] ?? ''),
            'button_text_color' => $this->sanitize_color($_POST['button_text_color'] ?? '', '#FFFFFF'),
            'button_bg_color' => $this->sanitize_color($_POST['button_bg_color'] ?? '', '#0B2C48'),
            'button_hover_bg_color' => $this->sanitize_color($_POST['button_hover_bg_color'] ?? '', '#154A75'),
            'button_border_radius' => intval($_POST['button_border_radius'] ?? 6),
            'button_x' => intval($_POST['button_x'] ?? 80),
            'button_y' => intval($_POST['button_y'] ?? 295),
            'trust_text' => sanitize_text_field($_POST['trust_text'] ?? ''),
            'trust_font_family' => sanitize_text_field($_POST['trust_font_family'] ?? ''),
            'trust_font_size' => intval($_POST['trust_font_size'] ?? 14),
            'trust_font_size_tablet' => intval($_POST['trust_font_size_tablet'] ?? 12),
            'trust_font_size_mobile' => intval($_POST['trust_font_size_mobile'] ?? 11),
            'trust_font_weight' => sanitize_text_field($_POST['trust_font_weight'] ?? ''),
            'trust_color' => $this->sanitize_color($_POST['trust_color'] ?? '', '#D9D9D9'),
            'trust_x' => intval($_POST['trust_x'] ?? 80),
            'trust_y' => intval($_POST['trust_y'] ?? 345),
            'style_preset' => sanitize_text_field($_POST['style_preset'] ?? 'default_sale'),
            'headline_animation_in' => sanitize_text_field($_POST['headline_animation_in'] ?? 'fadeInUp'),
            'headline_animation_duration' => intval($_POST['headline_animation_duration'] ?? 600),
            'subheadline_animation_in' => sanitize_text_field($_POST['subheadline_animation_in'] ?? 'fadeIn'),
            'subheadline_animation_duration' => intval($_POST['subheadline_animation_duration'] ?? 800),
            'discount_animation_in' => sanitize_text_field($_POST['discount_animation_in'] ?? 'zoomIn'),
            'button_animation_in' => sanitize_text_field($_POST['button_animation_in'] ?? 'slideInUp'),
            'trust_animation_in' => sanitize_text_field($_POST['trust_animation_in'] ?? 'fadeIn'),
            'image_animation_in' => sanitize_text_field($_POST['image_animation_in'] ?? 'zoomIn'),
            'show_discount' => !empty($_POST['show_discount']) ? 1 : 0,
            'show_countdown' => !empty($_POST['show_countdown']) ? 1 : 0,
            'show_trust' => !empty($_POST['show_trust']) ? 1 : 0,
            'coupon_code' => sanitize_text_field($_POST['coupon_code'] ?? ''),
            'text_position' => sanitize_text_field($_POST['text_position'] ?? 'left'),
            'text_position_tablet' => sanitize_text_field($_POST['text_position_tablet'] ?? 'left'),
            'text_position_mobile' => sanitize_text_field($_POST['text_position_mobile'] ?? 'left'),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ];

        $event_id = intval($_POST['event_id'] ?? 0);

        if ($event_id > 0) {
            $wpdb->update($table, $data, ['id' => $event_id]);
        } else {
            $wpdb->insert($table, $data);
            $event_id = (int) $wpdb->insert_id;
        }

        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id), ARRAY_A);
        if ($event && !empty($event['is_active'])) {
            update_option('tmd_current_event_slug', $event['event_slug']);
            TMD_EBM_Slider_Helper::update_master_slider($event);
        } elseif ($event && empty($event['is_active'])) {
            // If event was deactivated, remove its slide from the banner
            TMD_EBM_Slider_Helper::remove_event_slide($event['event_slug']);
            // If this was the current event, clear the slug
            $current = get_option('tmd_current_event_slug', '');
            if ($current === $event['event_slug']) {
                // Try to find another active event
                $next = TMD_EBM_Event_Resolver::get_active_event();
                if ($next) {
                    update_option('tmd_current_event_slug', $next['event_slug']);
                    TMD_EBM_Slider_Helper::update_master_slider($next);
                } else {
                    update_option('tmd_current_event_slug', 'default');
                }
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=tmd-ebm&saved=1'));
        exit;
    }

    public function run_update() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('tmd_ebm_run_update');

        $event = TMD_EBM_Event_Resolver::get_active_event();
        if ($event) {
            update_option('tmd_current_event_slug', $event['event_slug']);
            TMD_EBM_Slider_Helper::update_master_slider($event);
        }

        wp_safe_redirect(admin_url('admin.php?page=tmd-ebm&updated=1'));
        exit;
    }
}
