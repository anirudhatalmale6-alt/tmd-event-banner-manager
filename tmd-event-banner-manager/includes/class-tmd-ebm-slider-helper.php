<?php
if (!defined('ABSPATH')) exit;

class TMD_EBM_Slider_Helper {
    const MASTER_ALIAS_OPTION = 'tmd_ebm_master_alias';
    const EVENT_SLIDE_META_KEY = 'tmd_ebm_event_slide';

    /**
     * Layer alias names we look for in the template slide.
     * These map to the event DB fields.
     */
    public static function get_required_layer_map() {
        return [
            'layer_eyebrow',
            'layer_headline',
            'layer_subheadline',
            'layer_discount',
            'layer_button',
            'layer_background',
            'layer_countdown',
            'layer_trust',
        ];
    }

    /**
     * Build a payload from event data for logging/debugging.
     */
    public static function merge_event_into_payload(array $event): array {
        return [
            'event_slug'           => $event['event_slug'] ?? 'default',
            'eyebrow_text'         => $event['eyebrow_text'] ?? '',
            'headline'             => $event['headline'] ?? 'Trending Deals',
            'subheadline'          => $event['subheadline'] ?? '',
            'discount_text'        => $event['discount_text'] ?? '',
            'button_text'          => $event['button_text'] ?? 'SHOP NOW',
            'button_link'          => $event['button_link'] ?? '/shop',
            'background_image_url' => $event['background_image_url'] ?? '',
            'background_image_id'  => $event['background_image_id'] ?? 0,
            'countdown_date'       => $event['countdown_date'] ?? '',
            'trust_text'           => $event['trust_text'] ?? '',
            'coupon_code'          => $event['coupon_code'] ?? '',
            'style_preset'         => $event['style_preset'] ?? 'default_sale',
        ];
    }

    /**
     * Get the master slider alias (default: 'banner').
     */
    private static function get_master_alias(): string {
        return get_option(self::MASTER_ALIAS_OPTION, 'banner');
    }

    /**
     * Find the RevSlider slider ID by alias using direct DB query.
     * This avoids loading the full RS class which can cause issues in CLI.
     */
    private static function get_slider_id_by_alias(string $alias): int {
        global $wpdb;
        $table = $wpdb->prefix . 'revslider_sliders';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE alias = %s LIMIT 1",
            $alias
        ));
    }

    /**
     * Get all slides for a slider from the v7 table.
     */
    private static function get_slides_v7(int $slider_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'revslider_slides7';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE slider_id = %d ORDER BY slide_order ASC",
            $slider_id
        ), ARRAY_A);
    }

    /**
     * Get a single slide by ID from v7 table.
     */
    private static function get_slide_v7(int $slide_id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'revslider_slides7';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $slide_id
        ), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Find an existing event slide in the slider.
     * We mark event slides by storing 'tmd_event_slug' in the params JSON.
     */
    private static function find_event_slide(int $slider_id, string $event_slug): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'revslider_slides7';
        $slides = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE slider_id = %d",
            $slider_id
        ), ARRAY_A);

        foreach ($slides as $slide) {
            $params = json_decode($slide['params'], true);
            if (!empty($params['tmd_event_slug']) && $params['tmd_event_slug'] === $event_slug) {
                return $slide;
            }
        }
        return null;
    }

    /**
     * Get a template slide to clone layer structure from.
     * Picks the first non-event slide that has actual layers.
     */
    private static function get_template_slide(int $slider_id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'revslider_slides7';
        $slides = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE slider_id = %d ORDER BY slide_order ASC",
            $slider_id
        ), ARRAY_A);

        foreach ($slides as $slide) {
            $params = json_decode($slide['params'], true);
            $layers = json_decode($slide['layers'], true);
            // Skip event slides and slides with empty layers
            if (empty($params['tmd_event_slug']) && !empty($layers) && count($layers) > 0) {
                return $slide;
            }
        }
        // Fallback: any slide with layers
        foreach ($slides as $slide) {
            $layers = json_decode($slide['layers'], true);
            if (!empty($layers) && count($layers) > 0) {
                return $slide;
            }
        }
        return null;
    }

    /**
     * Build layers JSON for an event slide based on a template slide's layers.
     * Maps event data onto the existing layer structure.
     */
    private static function build_event_layers(array $template_layers, array $event): array {
        $layers = $template_layers;

        // Identify layers by their role based on common patterns:
        // - Largest font / first text = headline
        // - Second text = subheadline or description
        // - Text with "Shop Now" or button-like = button
        // - BG layer (subtype=slidebg or rTo=slide) = background

        $text_layers = [];
        $bg_layer_key = null;
        $button_layer_key = null;

        foreach ($layers as $key => $layer) {
            $type = $layer['type'] ?? ($layer['subtype'] ?? '');
            $alias = strtolower($layer['alias'] ?? '');
            $text = $layer['content']['text'] ?? '';

            // Background layer detection
            if ((!empty($layer['subtype']) && $layer['subtype'] === 'slidebg')
                || (!empty($layer['rTo']) && $layer['rTo'] === 'slide')) {
                $bg_layer_key = $key;
                continue;
            }

            // Button detection
            if ($type === 'text' && (
                stripos($alias, 'button') !== false ||
                stripos($text, 'Shop Now') !== false ||
                stripos($text, 'shop now') !== false
            )) {
                $button_layer_key = $key;
                continue;
            }

            // Collect text layers
            if ($type === 'text') {
                $font_size = 0;
                if (!empty($layer['font']['size'][0])) {
                    $font_size = intval($layer['font']['size'][0]);
                }
                $text_layers[$key] = [
                    'font_size' => $font_size,
                    'layer' => $layer,
                ];
            }
        }

        // Sort text layers by font size descending to identify headline (biggest) and subheadline (second)
        uasort($text_layers, function($a, $b) {
            return $b['font_size'] - $a['font_size'];
        });

        $text_keys = array_keys($text_layers);
        $headline_key = $text_keys[0] ?? null;
        $subheadline_key = $text_keys[1] ?? null;
        // If there's a third text layer, use it for eyebrow/discount
        $extra_text_key = $text_keys[2] ?? null;

        // Apply event data to layers

        // 1. Headline
        if ($headline_key !== null && !empty($event['headline'])) {
            $layers[$headline_key]['content']['text'] = $event['headline'];

            // Apply font customizations if set
            if (!empty($event['headline_font_family'])) {
                $layers[$headline_key]['font']['family'] = $event['headline_font_family'];
            }
            if (!empty($event['headline_color'])) {
                $color = $event['headline_color'];
                $layers[$headline_key]['color'] = [$color, $color, $color, $color, $color];
            }
            if (!empty($event['headline_font_weight'])) {
                $w = $event['headline_font_weight'];
                $layers[$headline_key]['font']['weight'] = [$w, $w, $w, $w, $w];
            }
            if (!empty($event['headline_font_size_desktop'])) {
                $d = $event['headline_font_size_desktop'] . 'px';
                $t = ($event['headline_font_size_tablet'] ?? $event['headline_font_size_desktop']) . 'px';
                $m = ($event['headline_font_size_mobile'] ?? 32) . 'px';
                $layers[$headline_key]['font']['size'] = [$d, $d, $t, $t, $m];
            }
        }

        // 2. Subheadline
        if ($subheadline_key !== null && !empty($event['subheadline'])) {
            $layers[$subheadline_key]['content']['text'] = $event['subheadline'];

            if (!empty($event['subheadline_font_family'])) {
                $layers[$subheadline_key]['font']['family'] = $event['subheadline_font_family'];
            }
            if (!empty($event['subheadline_color'])) {
                $color = $event['subheadline_color'];
                $layers[$subheadline_key]['color'] = [$color, $color, $color, $color, $color];
            }
            if (!empty($event['subheadline_font_weight'])) {
                $w = $event['subheadline_font_weight'];
                $layers[$subheadline_key]['font']['weight'] = [$w, $w, $w, $w, $w];
            }
            if (!empty($event['subheadline_font_size_desktop'])) {
                $d = $event['subheadline_font_size_desktop'] . 'px';
                $t = ($event['subheadline_font_size_tablet'] ?? $event['subheadline_font_size_desktop']) . 'px';
                $m = ($event['subheadline_font_size_mobile'] ?? 16) . 'px';
                $layers[$subheadline_key]['font']['size'] = [$d, $d, $t, $t, $m];
            }
        }

        // 3. Extra text layer (eyebrow or discount text)
        if ($extra_text_key !== null) {
            if (!empty($event['eyebrow_text'])) {
                $layers[$extra_text_key]['content']['text'] = $event['eyebrow_text'];
                if (!empty($event['eyebrow_color'])) {
                    $color = $event['eyebrow_color'];
                    $layers[$extra_text_key]['color'] = [$color, $color, $color, $color, $color];
                }
            } elseif (!empty($event['discount_text'])) {
                $layers[$extra_text_key]['content']['text'] = $event['discount_text'];
                if (!empty($event['discount_text_color'])) {
                    $color = $event['discount_text_color'];
                    $layers[$extra_text_key]['color'] = [$color, $color, $color, $color, $color];
                }
            }
        }

        // 4. Button
        if ($button_layer_key !== null) {
            if (!empty($event['button_text'])) {
                $layers[$button_layer_key]['content']['text'] = $event['button_text'];
            }
            if (!empty($event['button_text_color'])) {
                $color = $event['button_text_color'];
                $layers[$button_layer_key]['color'] = [$color, $color, $color, $color, $color];
            }
            if (!empty($event['button_bg_color'])) {
                $layers[$button_layer_key]['bg']['color'] = [
                    'orig' => $event['button_bg_color'],
                    'type' => 'solid',
                    'string' => $event['button_bg_color'],
                ];
            }
            if (!empty($event['button_font_family'])) {
                $layers[$button_layer_key]['font']['family'] = $event['button_font_family'];
            }
        }

        // 5. Background image
        if ($bg_layer_key !== null && !empty($event['background_image_url'])) {
            if (!isset($layers[$bg_layer_key]['bg'])) {
                $layers[$bg_layer_key]['bg'] = [];
            }
            if (!isset($layers[$bg_layer_key]['bg']['image'])) {
                $layers[$bg_layer_key]['bg']['image'] = [];
            }
            $layers[$bg_layer_key]['bg']['image']['src'] = $event['background_image_url'];
            if (!empty($event['background_image_id'])) {
                $layers[$bg_layer_key]['bg']['image']['lib_id'] = (int) $event['background_image_id'];
                $layers[$bg_layer_key]['bg']['image']['lib'] = 'medialibrary';
            }
            $layers[$bg_layer_key]['bg']['image']['size'] = 'cover';
            $layers[$bg_layer_key]['bg']['image']['pos'] = ['x' => '50%', 'y' => '50%'];
        }

        // Apply overlay if set
        if ($bg_layer_key !== null && !empty($event['overlay_color'])) {
            $opacity = floatval($event['overlay_opacity'] ?? 0.35);
            // Overlay is typically handled via slide params, not layers
        }

        // 6. Apply text position (left, center, right) per device
        // RS responsive array: [desktop, laptop, tablet, tablet-phone, mobile]
        $pos_desktop = $event['text_position'] ?? 'left';
        $pos_tablet = $event['text_position_tablet'] ?? $pos_desktop;
        $pos_mobile = $event['text_position_mobile'] ?? $pos_desktop;

        $all_text_keys = array_filter(
            [$headline_key, $subheadline_key, $extra_text_key, $button_layer_key],
            function($k) { return $k !== null; }
        );

        // Map position name to RS x-value and alignment origin
        $pos_map = [
            'left'   => ['x' => null, 'aO' => 'ml'], // null = keep template default
            'center' => ['x' => 'center', 'aO' => 'mc'],
            'right'  => ['x' => 'right', 'aO' => 'mr'],
        ];

        $d = $pos_map[$pos_desktop] ?? $pos_map['left'];
        $t = $pos_map[$pos_tablet] ?? $pos_map['left'];
        $m = $pos_map[$pos_mobile] ?? $pos_map['left'];

        // Only modify if at least one is non-left
        if ($pos_desktop !== 'left' || $pos_tablet !== 'left' || $pos_mobile !== 'left') {
            foreach ($all_text_keys as $lk) {
                if (!isset($layers[$lk])) continue;

                // Get current x positions from template
                $cur_x = $layers[$lk]['pos']['x'] ?? ['0px', '0px', '#a', '#a', '0px'];

                // Desktop positions (indices 0,1 = desktop, laptop)
                $dx = $d['x'] ?? $cur_x[0]; // keep template value if left
                // Tablet positions (indices 2,3 = tablet, tablet-phone)
                $tx = $t['x'] ?? ($cur_x[2] ?? '#a'); // keep template value if left
                // Mobile position (index 4)
                $mx = $m['x'] ?? ($cur_x[4] ?? $cur_x[0]); // keep template value if left

                $layers[$lk]['pos']['x'] = [$dx, $dx, $tx, $tx, $mx];

                // Alignment origin - pick the most specific non-left value
                // Priority: desktop > tablet > mobile
                if ($pos_desktop !== 'left') {
                    $layers[$lk]['attr']['aO'] = $d['aO'];
                    $layers[$lk]['attr']['tO'] = $d['aO'];
                } elseif ($pos_tablet !== 'left') {
                    $layers[$lk]['attr']['aO'] = $t['aO'];
                    $layers[$lk]['attr']['tO'] = $t['aO'];
                } elseif ($pos_mobile !== 'left') {
                    $layers[$lk]['attr']['aO'] = $m['aO'];
                    $layers[$lk]['attr']['tO'] = $m['aO'];
                }
            }
        }

        return $layers;
    }

    /**
     * Build slide params for the event slide.
     */
    private static function build_event_params(array $template_params, array $event, int $slide_id = 0): array {
        $params = $template_params;

        // Set slide identity - RS requires integer id matching DB row
        if ($slide_id > 0) {
            $params['id'] = $slide_id;
        }
        $params['title'] = $event['event_name'] ?? $event['headline'] ?? 'Event Banner';

        // Mark this as an event slide
        $params['tmd_event_slug'] = $event['event_slug'];
        $params['tmd_event_id'] = $event['id'] ?? 0;

        // Set the button link as slide action
        if (!empty($event['button_link'])) {
            $params['actions'] = [[
                'a' => 'link',
                'evt' => 'click',
                'http' => 'keep',
                'target' => '_self',
                'flw' => 'follow',
                'ltype' => 'a',
                'link' => $event['button_link'],
                'src' => [1],
            ]];
        }

        // Set thumb/bg image in params
        if (!empty($event['background_image_url'])) {
            $params['thumb'] = $params['thumb'] ?? [];
            $params['thumb']['default'] = [
                'image' => [
                    'src' => $event['background_image_url'],
                    'repeat' => '',
                    'size' => 'cover',
                    'pos' => ['x' => '50%', 'y' => '50%'],
                ],
            ];
        }

        // Set publish state
        $params['publish'] = [
            'from' => '',
            'to' => '',
            'state' => 'published',
            'sch' => true,
        ];

        return $params;
    }

    /**
     * Main method: update the master slider with event content.
     * Creates or updates an event slide in the Banner slider.
     */
    public static function update_master_slider(array $event): array {
        $payload = self::merge_event_into_payload($event);
        update_option('tmd_ebm_last_payload', $payload);

        // Check if RS plugin is loaded (class may not exist in all contexts)
        // We use direct DB operations so we don't strictly need the class
        global $wpdb;

        $alias = self::get_master_alias();
        $slider_id = self::get_slider_id_by_alias($alias);

        if (!$slider_id) {
            return [
                'success' => false,
                'message' => "Slider with alias '{$alias}' not found.",
                'payload' => $payload,
            ];
        }

        $slides_table = $wpdb->prefix . 'revslider_slides7';

        // Check if v7 table exists, fall back to v6
        $v7_exists = $wpdb->get_var("SHOW TABLES LIKE '{$slides_table}'");
        if (!$v7_exists) {
            $slides_table = $wpdb->prefix . 'revslider_slides';
        }

        $event_slug = $event['event_slug'] ?? 'default';

        // 1. Find existing event slide for this event
        $existing_slide = self::find_event_slide($slider_id, $event_slug);

        // 2. Get a template slide to clone structure from
        $template = self::get_template_slide($slider_id);
        if (!$template) {
            return [
                'success' => false,
                'message' => 'No template slide found in the Banner slider.',
                'payload' => $payload,
            ];
        }

        $template_layers = json_decode($template['layers'], true) ?: [];
        $template_params = json_decode($template['params'], true) ?: [];

        // 3. Build event layers
        $event_layers = self::build_event_layers($template_layers, $event);

        // RS stores layers as a JSON object with string keys, not an array.
        // Use json_encode with JSON_UNESCAPED_SLASHES to match RS format.
        $layers_json = json_encode((object) $event_layers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($existing_slide) {
            // Build params with correct slide ID
            $event_params = self::build_event_params($template_params, $event, (int) $existing_slide['id']);
            $params_json = wp_json_encode($event_params);

            // Update existing event slide
            $wpdb->update(
                $slides_table,
                [
                    'layers' => $layers_json,
                    'params' => $params_json,
                    'slide_order' => 0, // Keep it first
                ],
                ['id' => $existing_slide['id']],
                ['%s', '%s', '%d'],
                ['%d']
            );

            // Clear RS cache
            self::clear_rs_cache($slider_id);

            return [
                'success' => true,
                'message' => "Updated event slide #{$existing_slide['id']} for '{$event_slug}'.",
                'slide_id' => (int) $existing_slide['id'],
                'payload' => $payload,
            ];
        } else {
            // Build params initially without slide ID (will update after insert)
            $event_params = self::build_event_params($template_params, $event, 0);
            $params_json = wp_json_encode($event_params);
            $settings_json = $template['settings'] ?? '{"version":"7.0.0"}';

            $wpdb->insert(
                $slides_table,
                [
                    'slider_id' => $slider_id,
                    'slide_order' => 0,
                    'params' => $params_json,
                    'layers' => $layers_json,
                    'settings' => $settings_json,
                ],
                ['%d', '%d', '%s', '%s', '%s']
            );

            $new_slide_id = (int) $wpdb->insert_id;

            if (!$new_slide_id) {
                return [
                    'success' => false,
                    'message' => 'Failed to create event slide: ' . $wpdb->last_error,
                    'payload' => $payload,
                ];
            }

            // Update params with the correct slide ID (RS requires id in params to match DB id)
            $event_params['id'] = $new_slide_id;
            $params_json = wp_json_encode($event_params);
            $wpdb->update(
                $slides_table,
                ['params' => $params_json],
                ['id' => $new_slide_id],
                ['%s'],
                ['%d']
            );

            // Clear RS cache
            self::clear_rs_cache($slider_id);

            return [
                'success' => true,
                'message' => "Created event slide #{$new_slide_id} for '{$event_slug}'.",
                'slide_id' => $new_slide_id,
                'payload' => $payload,
            ];
        }
    }

    /**
     * Remove an event slide from the Banner slider.
     */
    public static function remove_event_slide(string $event_slug): array {
        global $wpdb;

        $alias = self::get_master_alias();
        $slider_id = self::get_slider_id_by_alias($alias);
        if (!$slider_id) {
            return ['success' => false, 'message' => 'Slider not found.'];
        }

        $slide = self::find_event_slide($slider_id, $event_slug);
        if (!$slide) {
            return ['success' => true, 'message' => 'No event slide found to remove.'];
        }

        // Delete from both v7 and v6 tables
        $v7_table = $wpdb->prefix . 'revslider_slides7';
        $v6_table = $wpdb->prefix . 'revslider_slides';

        $wpdb->delete($v7_table, ['id' => $slide['id']], ['%d']);
        if ($wpdb->get_var("SHOW TABLES LIKE '{$v6_table}'")) {
            $wpdb->delete($v6_table, ['id' => $slide['id']], ['%d']);
        }

        self::clear_rs_cache($slider_id);

        return [
            'success' => true,
            'message' => "Removed event slide #{$slide['id']} for '{$event_slug}'.",
        ];
    }

    /**
     * Clear Slider Revolution internal caches.
     */
    private static function clear_rs_cache(int $slider_id): void {
        // Delete RS transient caches
        delete_transient('revslider_slider_' . $slider_id);

        // If RS cache class is available, use it
        if (class_exists('RevSliderCache')) {
            try {
                $cache = new RevSliderCache();
                if (method_exists($cache, 'clear_all_transients')) {
                    $cache->clear_all_transients();
                }
            } catch (Exception $e) {
                // Silently ignore
            }
        }

        // Clear object cache
        wp_cache_flush();
    }

    /**
     * Standardize an existing slider's layer aliases to our naming convention.
     * Only needed if the admin wants to manually set up layer names.
     */
    public static function maybe_standardize_template(string $alias): array {
        $result = [
            'success' => false,
            'message' => '',
        ];

        $slider_id = self::get_slider_id_by_alias($alias);
        if (!$slider_id) {
            $result['message'] = "Slider '{$alias}' not found.";
            return $result;
        }

        $result['success'] = true;
        $result['message'] = "Slider '{$alias}' (ID: {$slider_id}) found. The system auto-detects layers by type/size, so manual alias standardization is optional.";
        return $result;
    }
}
