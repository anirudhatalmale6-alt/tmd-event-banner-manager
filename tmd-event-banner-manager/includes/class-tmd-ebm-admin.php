<?php
if (!defined('ABSPATH')) exit;

class TMD_EBM_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_post_tmd_ebm_save_event', [$this, 'save_event']);
        add_action('admin_post_tmd_ebm_run_update', [$this, 'run_update']);
        add_action('admin_post_tmd_ebm_delete_event', [$this, 'delete_event']);
        add_action('admin_post_tmd_ebm_toggle_publish', [$this, 'toggle_publish']);
        add_action('admin_post_tmd_ebm_toggle_slide', [$this, 'toggle_slide']);
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

        // Fetch all slides from the target slider for the overview panel
        $banner_slides = [];
        $target_alias = TMD_EBM_Slider_Helper::get_target_alias();
        // Try v7 table first (RS 7.x), fall back to v6
        $sliders_table = $wpdb->prefix . 'revslider_sliders7';
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$sliders_table}'")) {
            $sliders_table = $wpdb->prefix . 'revslider_sliders';
        }
        $slider_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$sliders_table} WHERE alias = %s LIMIT 1", $target_alias));
        if ($slider_id) {
            $slides_table = $wpdb->prefix . 'revslider_slides7';
            if (!$wpdb->get_var("SHOW TABLES LIKE '{$slides_table}'")) {
                $slides_table = $wpdb->prefix . 'revslider_slides';
            }
            $raw_slides = $wpdb->get_results($wpdb->prepare(
                "SELECT id, slide_order, params, layers, static AS is_static FROM {$slides_table} WHERE slider_id = %d ORDER BY slide_order ASC",
                $slider_id
            ), ARRAY_A);
            foreach ($raw_slides as $rs) {
                $p = json_decode($rs['params'], true) ?: [];
                $l = json_decode($rs['layers'], true) ?: [];
                $event_slug = $p['tmd_event_slug'] ?? '';
                $title = $p['title'] ?? 'Untitled';
                $is_global = !empty($p['global']) || !empty($rs['is_static']);
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
                // Skip static/global layers from overview (they appear on all slides, managed by RS)
                if ($is_global) continue;
                $banner_slides[] = [
                    'id' => (int)$rs['id'],
                    'order' => (int)$rs['slide_order'],
                    'title' => $title,
                    'event_slug' => $event_slug,
                    'is_global' => false,
                    'bg_url' => $bg_url,
                    'layer_count' => count($l),
                    'publish_state' => $p['publish']['state'] ?? 'published',
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
                "SELECT id, event_slug FROM " . TMD_EBM_TABLE . " WHERE id = %d",
                $event_id
            ), ARRAY_A);
            if ($event) {
                TMD_EBM_Slider_Helper::remove_event_slide($event['event_slug'], (int) $event['id']);
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

    /**
     * Return intval if non-empty, or null to inherit from template.
     */
    private function nullable_int($key, $fallback = null) {
        $val = $_POST[$key] ?? '';
        return ($val === '' || $val === null) ? $fallback : intval($val);
    }

    /**
     * Return sanitized color if non-empty, or null to inherit from template.
     */
    private function nullable_color($key) {
        $val = trim($_POST[$key] ?? '');
        if ($val === '') return null;
        // Accept #hex6 or rgba(r,g,b,a) formats
        if (preg_match('/^#[A-Fa-f0-9]{6}$/', $val)) return $val;
        if (preg_match('/^rgba?\(\s*\d+/', $val)) return $val;
        return null;
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
            'headline' => wp_kses(str_replace(["\r\n", "\r", "\n"], '<br>', trim($_POST['headline'] ?? '')), ['br' => []]),
            'subheadline' => wp_kses(str_replace(["\r\n", "\r", "\n"], '<br>', trim($_POST['subheadline'] ?? '')), ['br' => []]),
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
            'eyebrow_font_family' => sanitize_text_field($_POST['eyebrow_font_family'] ?? '') ?: null,
            'eyebrow_font_size_desktop' => $this->nullable_int('eyebrow_font_size_desktop'),
            'eyebrow_font_size_tablet' => $this->nullable_int('eyebrow_font_size_tablet'),
            'eyebrow_font_size_mobile' => $this->nullable_int('eyebrow_font_size_mobile'),
            'eyebrow_font_weight' => sanitize_text_field($_POST['eyebrow_font_weight'] ?? '') ?: null,
            'eyebrow_color' => $this->nullable_color('eyebrow_color'),
            'eyebrow_x' => intval($_POST['eyebrow_x'] ?? 80),
            'eyebrow_y' => intval($_POST['eyebrow_y'] ?? 70),
            'headline_font_family' => sanitize_text_field($_POST['headline_font_family'] ?? '') ?: null,
            'headline_font_size_desktop' => $this->nullable_int('headline_font_size_desktop'),
            'headline_font_size_tablet' => $this->nullable_int('headline_font_size_tablet'),
            'headline_font_size_mobile' => $this->nullable_int('headline_font_size_mobile'),
            'headline_font_weight' => sanitize_text_field($_POST['headline_font_weight'] ?? '') ?: null,
            'headline_color' => $this->nullable_color('headline_color'),
            'headline_x' => intval($_POST['headline_x'] ?? 80),
            'headline_y' => intval($_POST['headline_y'] ?? 110),
            'subheadline_font_family' => sanitize_text_field($_POST['subheadline_font_family'] ?? '') ?: null,
            'subheadline_font_size_desktop' => $this->nullable_int('subheadline_font_size_desktop'),
            'subheadline_font_size_tablet' => $this->nullable_int('subheadline_font_size_tablet'),
            'subheadline_font_size_mobile' => $this->nullable_int('subheadline_font_size_mobile'),
            'subheadline_font_weight' => sanitize_text_field($_POST['subheadline_font_weight'] ?? '') ?: null,
            'subheadline_color' => $this->nullable_color('subheadline_color'),
            'subheadline_x' => intval($_POST['subheadline_x'] ?? 80),
            'subheadline_y' => intval($_POST['subheadline_y'] ?? 190),
            'discount_font_family' => sanitize_text_field($_POST['discount_font_family'] ?? '') ?: null,
            'discount_font_size' => $this->nullable_int('discount_font_size'),
            'discount_font_size_tablet' => $this->nullable_int('discount_font_size_tablet'),
            'discount_font_size_mobile' => $this->nullable_int('discount_font_size_mobile'),
            'discount_font_weight' => sanitize_text_field($_POST['discount_font_weight'] ?? '') ?: null,
            'discount_text_color' => $this->nullable_color('discount_text_color'),
            'discount_bg_color' => $this->nullable_color('discount_bg_color'),
            'discount_border_radius' => $this->nullable_int('discount_border_radius'),
            'discount_x' => intval($_POST['discount_x'] ?? 80),
            'discount_y' => intval($_POST['discount_y'] ?? 245),
            'button_font_family' => sanitize_text_field($_POST['button_font_family'] ?? '') ?: null,
            'button_font_size' => $this->nullable_int('button_font_size'),
            'button_font_size_tablet' => $this->nullable_int('button_font_size_tablet'),
            'button_font_size_mobile' => $this->nullable_int('button_font_size_mobile'),
            'button_font_weight' => sanitize_text_field($_POST['button_font_weight'] ?? '') ?: null,
            'button_text_color' => $this->nullable_color('button_text_color'),
            'button_bg_color' => $this->nullable_color('button_bg_color'),
            'button_hover_bg_color' => $this->nullable_color('button_hover_bg_color'),
            'button_border_radius' => $this->nullable_int('button_border_radius'),
            'button_x' => intval($_POST['button_x'] ?? 80),
            'button_y' => intval($_POST['button_y'] ?? 295),
            'trust_text' => sanitize_text_field($_POST['trust_text'] ?? ''),
            'trust_font_family' => sanitize_text_field($_POST['trust_font_family'] ?? '') ?: null,
            'trust_font_size' => $this->nullable_int('trust_font_size'),
            'trust_font_size_tablet' => $this->nullable_int('trust_font_size_tablet'),
            'trust_font_size_mobile' => $this->nullable_int('trust_font_size_mobile'),
            'trust_font_weight' => sanitize_text_field($_POST['trust_font_weight'] ?? '') ?: null,
            'trust_color' => $this->nullable_color('trust_color'),
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
        ];

        // Handle is_active based on which button was clicked:
        // "Publish Event" / "Update Event" = save_as=publish = active
        // "Save as Draft" / "Unpublish" = save_as=draft = inactive
        $event_id = intval($_POST['event_id'] ?? 0);
        $save_as = sanitize_text_field($_POST['save_as'] ?? 'publish');
        $data['is_active'] = ($save_as === 'publish') ? 1 : 0;

        if ($event_id > 0) {
            $wpdb->update($table, $data, ['id' => $event_id]);
        } else {
            $wpdb->insert($table, $data);
            $event_id = (int) $wpdb->insert_id;
        }

        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id), ARRAY_A);

        // Buffer output: RS API / cache clearing may produce warnings that prevent redirect
        ob_start();
        try {
            if ($event && !empty($event['is_active'])) {
                update_option('tmd_current_event_slug', $event['event_slug']);
                TMD_EBM_Slider_Helper::update_master_slider($event);
            } elseif ($event && empty($event['is_active'])) {
                TMD_EBM_Slider_Helper::remove_event_slide($event['event_slug'], (int) $event['id']);
                $current = get_option('tmd_current_event_slug', '');
                if ($current === $event['event_slug']) {
                    $next = TMD_EBM_Event_Resolver::get_active_event();
                    if ($next) {
                        update_option('tmd_current_event_slug', $next['event_slug']);
                        TMD_EBM_Slider_Helper::update_master_slider($next);
                    } else {
                        update_option('tmd_current_event_slug', 'default');
                    }
                }
            }
        } catch (\Exception $ex) {
            // Swallow RS errors so redirect still works
        }
        ob_end_clean();

        wp_safe_redirect(admin_url('admin.php?page=tmd-ebm&edit=' . $event_id . '&saved=1'));
        exit;
    }

    public function run_update() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('tmd_ebm_run_update');

        ob_start();
        try {
            $event = TMD_EBM_Event_Resolver::get_active_event();
            if ($event) {
                update_option('tmd_current_event_slug', $event['event_slug']);
                TMD_EBM_Slider_Helper::update_master_slider($event);
            }
        } catch (\Exception $ex) {}
        ob_end_clean();

        wp_safe_redirect(admin_url('admin.php?page=tmd-ebm&updated=1'));
        exit;
    }

    public function toggle_publish() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('tmd_ebm_toggle_publish');

        global $wpdb;
        $table = TMD_EBM_TABLE;
        $event_id = intval($_POST['event_id'] ?? 0);
        $new_state = intval($_POST['new_state'] ?? 0);

        if ($event_id > 0) {
            $wpdb->update($table, ['is_active' => $new_state], ['id' => $event_id], ['%d'], ['%d']);

            ob_start();
            try {
                $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id), ARRAY_A);
                if ($event) {
                    if ($new_state) {
                        update_option('tmd_current_event_slug', $event['event_slug']);
                        TMD_EBM_Slider_Helper::update_master_slider($event);
                    } else {
                        TMD_EBM_Slider_Helper::remove_event_slide($event['event_slug'], (int) $event['id']);
                        $current = get_option('tmd_current_event_slug', '');
                        if ($current === $event['event_slug']) {
                            $next = TMD_EBM_Event_Resolver::get_active_event();
                            if ($next) {
                                update_option('tmd_current_event_slug', $next['event_slug']);
                                TMD_EBM_Slider_Helper::update_master_slider($next);
                            } else {
                                update_option('tmd_current_event_slug', 'default');
                            }
                        }
                    }
                }
            } catch (\Exception $ex) {}
            ob_end_clean();
        }

        $action = $new_state ? 'published' : 'unpublished';
        wp_safe_redirect(admin_url('admin.php?page=tmd-ebm&' . $action . '=1'));
        exit;
    }

    public function toggle_slide() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('tmd_ebm_toggle_slide');

        $slide_id = intval($_POST['slide_id'] ?? 0);
        $new_state = sanitize_text_field($_POST['new_state'] ?? 'published');
        if (!in_array($new_state, ['published', 'unpublished'], true)) {
            $new_state = 'published';
        }

        if ($slide_id > 0) {
            global $wpdb;
            $table = $wpdb->prefix . 'revslider_slides7';
            $params_json = $wpdb->get_var($wpdb->prepare("SELECT params FROM {$table} WHERE id = %d", $slide_id));
            if ($params_json) {
                $params = json_decode($params_json, true);
                if (!isset($params['publish'])) {
                    $params['publish'] = [];
                }
                $params['publish']['state'] = $new_state;
                $wpdb->update($table, ['params' => wp_json_encode($params)], ['id' => $slide_id], ['%s'], ['%d']);

                ob_start();
                try {
                    $slider_id = $wpdb->get_var($wpdb->prepare("SELECT slider_id FROM {$table} WHERE id = %d", $slide_id));
                    if ($slider_id) {
                        TMD_EBM_Slider_Helper::clear_rs_cache((int) $slider_id);
                    }
                } catch (\Exception $ex) {}
                ob_end_clean();
            }
        }

        $label = ($new_state === 'published') ? 'slide_on' : 'slide_off';
        wp_safe_redirect(admin_url('admin.php?page=tmd-ebm&' . $label . '=1'));
        exit;
    }
}
