<?php
if (!defined('ABSPATH')) exit;

class TMD_EBM_Slider_Helper {
    const MASTER_ALIAS_OPTION = 'tmd_ebm_master_alias';
    const EVENT_SLIDE_META_KEY = 'tmd_ebm_event_slide';

    /**
     * Check if Rev Slider PHP API classes are available.
     */
    private static function rs_available(): bool {
        return class_exists('RevSliderSlide')
            && class_exists('RevSliderSlider')
            && class_exists('RevSliderGlobals');
    }

    /**
     * Get the master slider alias (default: 'banner').
     */
    private static function get_master_alias(): string {
        return get_option(self::MASTER_ALIAS_OPTION, 'banner');
    }

    /**
     * Get a RevSliderSlider instance by alias, or null if not found.
     */
    private static function get_slider(string $alias): ?object {
        $slider = new RevSliderSlider();
        $slider->init_by_alias($alias);
        return $slider->inited ? $slider : null;
    }

    /**
     * Find the DB ID of an existing event slide by scanning params for tmd_event_slug.
     * Uses direct DB query for read-only efficiency (no cache issues with reads).
     */
    private static function find_event_slide_id(int $slider_id, string $event_slug): int {
        global $wpdb;
        $table = $wpdb->prefix . 'revslider_slides7';
        $slides = $wpdb->get_results($wpdb->prepare(
            "SELECT id, params FROM {$table} WHERE slider_id = %d AND static = 0",
            $slider_id
        ), ARRAY_A);

        foreach ($slides as $slide) {
            $params = json_decode($slide['params'], true);
            if (!empty($params['tmd_event_slug']) && $params['tmd_event_slug'] === $event_slug) {
                return (int) $slide['id'];
            }
        }
        return 0;
    }

    /**
     * Get template slide ID: first non-event, non-global slide with layers.
     */
    private static function get_template_slide_id(int $slider_id): int {
        global $wpdb;
        $table = $wpdb->prefix . 'revslider_slides7';
        $slides = $wpdb->get_results($wpdb->prepare(
            "SELECT id, params, layers FROM {$table} WHERE slider_id = %d AND static = 0 ORDER BY slide_order ASC",
            $slider_id
        ), ARRAY_A);

        foreach ($slides as $slide) {
            $params = json_decode($slide['params'], true);
            $layers = json_decode($slide['layers'], true);
            if (empty($params['tmd_event_slug']) && empty($params['global']) && !empty($layers) && count($layers) > 0) {
                return (int) $slide['id'];
            }
        }
        return 0;
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
     * Build layers array for an event slide based on a template slide's layers.
     */
    private static function build_event_layers(array $template_layers, array $event): array {
        $layers = $template_layers;

        $text_layers = [];
        $bg_layer_key = null;
        $button_layer_key = null;

        foreach ($layers as $key => $layer) {
            $type = $layer['type'] ?? ($layer['subtype'] ?? '');
            $alias = strtolower($layer['alias'] ?? '');
            $text = $layer['content']['text'] ?? '';

            // Background layer
            if ((!empty($layer['subtype']) && $layer['subtype'] === 'slidebg')
                || (!empty($layer['rTo']) && $layer['rTo'] === 'slide')) {
                $bg_layer_key = $key;
                continue;
            }

            // Button
            if ($type === 'text' && (
                (!empty($layer['subtype']) && $layer['subtype'] === 'button') ||
                stripos($alias, 'button') !== false ||
                stripos($text, 'Shop Now') !== false ||
                stripos($text, 'shop now') !== false
            )) {
                $button_layer_key = $key;
                continue;
            }

            // Text layers
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

        // Sort by font size descending: biggest = headline, second = subheadline
        uasort($text_layers, function($a, $b) {
            return $b['font_size'] - $a['font_size'];
        });

        $text_keys = array_keys($text_layers);
        $headline_key = $text_keys[0] ?? null;
        $subheadline_key = $text_keys[1] ?? null;
        $extra_text_key = $text_keys[2] ?? null;

        // 1. Headline
        if ($headline_key !== null && !empty($event['headline'])) {
            $layers[$headline_key]['content']['text'] = $event['headline'];

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

        // 3. Extra text layer (eyebrow or discount)
        if ($extra_text_key !== null) {
            if (!empty($event['eyebrow_text'])) {
                $layers[$extra_text_key]['content']['text'] = $event['eyebrow_text'];
                if (!empty($event['eyebrow_color'])) {
                    $color = $event['eyebrow_color'];
                    $layers[$extra_text_key]['color'] = [$color, $color, $color, $color, $color];
                }
                if (!empty($event['eyebrow_font_family'])) {
                    $layers[$extra_text_key]['font']['family'] = $event['eyebrow_font_family'];
                }
                if (!empty($event['eyebrow_font_size_desktop'])) {
                    $d = $event['eyebrow_font_size_desktop'] . 'px';
                    $t = ($event['eyebrow_font_size_tablet'] ?? $event['eyebrow_font_size_desktop']) . 'px';
                    $m = ($event['eyebrow_font_size_mobile'] ?? 12) . 'px';
                    $layers[$extra_text_key]['font']['size'] = [$d, $d, $t, $t, $m];
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

        // 6. Text position per device
        $pos_desktop = $event['text_position'] ?? 'left';
        $pos_tablet = $event['text_position_tablet'] ?? $pos_desktop;
        $pos_mobile = $event['text_position_mobile'] ?? $pos_desktop;

        $all_text_keys = array_filter(
            [$headline_key, $subheadline_key, $extra_text_key, $button_layer_key],
            function($k) { return $k !== null; }
        );

        $pos_map = [
            'left'   => ['x' => null, 'aO' => 'ml'],
            'center' => ['x' => 'center', 'aO' => 'mc'],
            'right'  => ['x' => 'right', 'aO' => 'mr'],
        ];

        $d = $pos_map[$pos_desktop] ?? $pos_map['left'];
        $t = $pos_map[$pos_tablet] ?? $pos_map['left'];
        $m = $pos_map[$pos_mobile] ?? $pos_map['left'];

        if ($pos_desktop !== 'left' || $pos_tablet !== 'left' || $pos_mobile !== 'left') {
            foreach ($all_text_keys as $lk) {
                if (!isset($layers[$lk])) continue;

                $cur_x = $layers[$lk]['pos']['x'] ?? ['0px', '0px', '#a', '#a', '0px'];

                $dx = $d['x'] ?? $cur_x[0];
                $tx = $t['x'] ?? ($cur_x[2] ?? '#a');
                $mx = $m['x'] ?? ($cur_x[4] ?? $cur_x[0]);

                $layers[$lk]['pos']['x'] = [$dx, $dx, $tx, $tx, $mx];

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

        if ($slide_id > 0) {
            $params['id'] = $slide_id;
        }
        $params['title'] = $event['event_name'] ?? $event['headline'] ?? 'Event Banner';

        // Mark as event slide
        $params['tmd_event_slug'] = $event['event_slug'];
        $params['tmd_event_id'] = $event['id'] ?? 0;

        // Slide action (button link)
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

        // Background image in params
        if (!empty($event['background_image_url'])) {
            $params['thumb'] = $params['thumb'] ?? [];
            $params['thumb']['dimension'] = 'slider';
            $params['thumb']['default'] = [
                'image' => [
                    'src' => $event['background_image_url'],
                    'repeat' => '',
                    'size' => 'cover',
                    'pos' => ['x' => '50%', 'y' => '50%'],
                ],
            ];
        }

        // Publish state
        $params['publish'] = [
            'from' => '',
            'to' => '',
            'state' => 'published',
            'sch' => true,
        ];

        // Ensure version
        $params['version'] = '7.0.0';

        return $params;
    }

    /**
     * Main method: update the master slider with event content.
     * Uses Rev Slider PHP API for proper cache invalidation and compatibility.
     */
    public static function update_master_slider(array $event): array {
        $payload = self::merge_event_into_payload($event);
        update_option('tmd_ebm_last_payload', $payload);

        if (!self::rs_available()) {
            return [
                'success' => false,
                'message' => 'Rev Slider classes not available.',
                'payload' => $payload,
            ];
        }

        $alias = self::get_master_alias();
        $slider = self::get_slider($alias);

        if (!$slider) {
            return [
                'success' => false,
                'message' => "Slider with alias '{$alias}' not found.",
                'payload' => $payload,
            ];
        }

        $slider_id = $slider->get_id();
        $event_slug = $event['event_slug'] ?? 'default';

        // Find existing event slide
        $existing_slide_id = self::find_event_slide_id($slider_id, $event_slug);

        // Get template slide
        $template_slide_id = self::get_template_slide_id($slider_id);
        if (!$template_slide_id) {
            return [
                'success' => false,
                'message' => 'No template slide found in the Banner slider.',
                'payload' => $payload,
            ];
        }

        // Load template via RS API
        $template = new RevSliderSlide();
        $template->init_by_id($template_slide_id);
        $template_layers = $template->get_layers();
        $template_params = $template->get_params();

        // Build event layers and params from template
        $event_layers = self::build_event_layers($template_layers, $event);

        if ($existing_slide_id) {
            // UPDATE existing event slide via RS API
            $event_params = self::build_event_params($template_params, $event, $existing_slide_id);

            // Preserve the existing slide's order
            $existing_slide = new RevSliderSlide();
            $existing_slide->init_by_id($existing_slide_id);
            $cur_order = $existing_slide->get_param('order', '0');
            $event_params['order'] = $cur_order;

            $data = [
                'version' => '7.0.0',
                'slide' => $event_params,
                'layers' => $event_layers,
            ];

            $slide = new RevSliderSlide();
            $slide->save_slide_v7($existing_slide_id, $data, $slider_id);

            self::clear_rs_cache($slider_id);

            return [
                'success' => true,
                'message' => "Updated event slide #{$existing_slide_id} for '{$event_slug}' via RS API.",
                'slide_id' => $existing_slide_id,
                'payload' => $payload,
            ];
        } else {
            // CREATE new event slide via RS API
            $slide = new RevSliderSlide();
            $new_id = $slide->create_slide($slider_id);

            if (!$new_id) {
                return [
                    'success' => false,
                    'message' => 'Failed to create slide via Rev Slider API.',
                    'payload' => $payload,
                ];
            }

            $event_params = self::build_event_params($template_params, $event, (int) $new_id);
            $event_params['order'] = '0'; // Place at beginning

            $data = [
                'version' => '7.0.0',
                'slide' => $event_params,
                'layers' => $event_layers,
            ];

            $slide->save_slide_v7((int) $new_id, $data, $slider_id);

            self::clear_rs_cache($slider_id);

            return [
                'success' => true,
                'message' => "Created event slide #{$new_id} for '{$event_slug}' via RS API.",
                'slide_id' => (int) $new_id,
                'payload' => $payload,
            ];
        }
    }

    /**
     * Remove an event slide from the Banner slider using RS API.
     */
    public static function remove_event_slide(string $event_slug): array {
        if (!self::rs_available()) {
            return ['success' => false, 'message' => 'Rev Slider classes not available.'];
        }

        $alias = self::get_master_alias();
        $slider = self::get_slider($alias);
        if (!$slider) {
            return ['success' => false, 'message' => 'Slider not found.'];
        }

        $slider_id = $slider->get_id();
        $slide_id = self::find_event_slide_id($slider_id, $event_slug);

        if (!$slide_id) {
            return ['success' => true, 'message' => 'No event slide found to remove.'];
        }

        // Delete via RS API (handles both v7 and preview tables)
        $slide = new RevSliderSlide();
        $slide->delete_slide_by_id($slide_id);

        self::clear_rs_cache($slider_id);

        return [
            'success' => true,
            'message' => "Removed event slide #{$slide_id} for '{$event_slug}' via RS API.",
        ];
    }

    /**
     * Clear Slider Revolution caches using the RS PHP API.
     */
    private static function clear_rs_cache(int $slider_id): void {
        // Use RS's own cache clearing API
        try {
            $cache = RevSliderGlobals::instance()->get('RevSliderCache');
            if ($cache && method_exists($cache, 'clear_transients_by_slider')) {
                $cache->clear_transients_by_slider($slider_id);
            }
        } catch (Exception $e) {
            // Fallback: clear transients manually
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_revslider_slider_{$slider_id}%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_revslider_slider_{$slider_id}%'");
        }

        // Flush object cache for SiteGround Redis
        wp_cache_flush();
    }

    /**
     * Standardize template aliases (optional utility).
     */
    public static function maybe_standardize_template(string $alias): array {
        if (!self::rs_available()) {
            return ['success' => false, 'message' => 'Rev Slider classes not available.'];
        }

        $slider = self::get_slider($alias);
        if (!$slider) {
            return ['success' => false, 'message' => "Slider '{$alias}' not found."];
        }

        return [
            'success' => true,
            'message' => "Slider '{$alias}' (ID: {$slider->get_id()}) found. Layers auto-detected by type/size.",
        ];
    }
}
