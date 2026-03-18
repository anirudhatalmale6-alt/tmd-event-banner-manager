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
     * Get the master slider alias (default: 'slider-1-1').
     */
    private static function get_master_alias(): string {
        return get_option(self::MASTER_ALIAS_OPTION, 'slider-1-1');
    }

    /**
     * Public accessor for the target slider alias (used by admin).
     */
    public static function get_target_alias(): string {
        return self::get_master_alias();
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
     * Find the DB ID of an existing event slide by scanning params.
     * Matches by tmd_event_slug first, then falls back to tmd_event_id.
     * This prevents orphaned slides when the event slug is changed.
     */
    private static function find_event_slide_id(int $slider_id, string $event_slug, int $event_id = 0): int {
        global $wpdb;
        $table = $wpdb->prefix . 'revslider_slides7';
        $slides = $wpdb->get_results($wpdb->prepare(
            "SELECT id, params FROM {$table} WHERE slider_id = %d AND (static = '' OR static = 0 OR static IS NULL)",
            $slider_id
        ), ARRAY_A);

        $id_match = 0;
        foreach ($slides as $slide) {
            $params = json_decode($slide['params'], true);
            // Exact slug match - best match
            if (!empty($params['tmd_event_slug']) && $params['tmd_event_slug'] === $event_slug) {
                return (int) $slide['id'];
            }
            // Event ID match - fallback when slug was changed
            if ($event_id > 0 && !empty($params['tmd_event_id']) && (int) $params['tmd_event_id'] === $event_id) {
                $id_match = (int) $slide['id'];
            }
        }
        return $id_match;
    }

    /**
     * Get template slide ID: newest non-event, non-global slide with layers.
     * Prefers newer slides (higher ID) to match the latest design style.
     */
    private static function get_template_slide_id(int $slider_id): int {
        global $wpdb;
        $table = $wpdb->prefix . 'revslider_slides7';
        $slides = $wpdb->get_results($wpdb->prepare(
            "SELECT id, params, layers FROM {$table} WHERE slider_id = %d AND (static = '' OR static IS NULL) ORDER BY id DESC",
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

        // Classify layers by their role in the template
        // Template structure (from slides 84-87): eyebrow, headline, subheadline, discount, button, trust, slidebg
        $bg_layer_key = null;
        $button_layer_key = null;
        $text_layer_keys = []; // All non-button, non-bg text layers in original order

        foreach ($layers as $key => $layer) {
            $type = $layer['type'] ?? ($layer['subtype'] ?? '');
            $text = $layer['content']['text'] ?? '';

            // Background layer (must be slidebg specifically, not zone containers)
            if (!empty($layer['subtype']) && $layer['subtype'] === 'slidebg') {
                $bg_layer_key = $key;
                continue;
            }

            // Button (has link action or contains SHOP)
            if ($type === 'text' && (
                (!empty($layer['subtype']) && $layer['subtype'] === 'button') ||
                !empty($layer['actions']) ||
                stripos($text, 'SHOP') !== false
            )) {
                $button_layer_key = $key;
                continue;
            }

            // Regular text layer
            if ($type === 'text') {
                $text_layer_keys[] = $key;
            }
        }

        // Map text layers by position order (as they appear in template):
        // Template order: [0]=eyebrow, [1]=headline, [2]=subheadline, [3]=discount, [4]=trust
        // We identify headline as the layer with the largest font size
        $headline_key = null;
        $max_size = 0;
        foreach ($text_layer_keys as $key) {
            $size = intval($layers[$key]['font']['size'][0] ?? 0);
            if ($size > $max_size) {
                $max_size = $size;
                $headline_key = $key;
            }
        }

        // Split remaining text layers into before-headline and after-headline groups
        $before_headline = [];
        $after_headline = [];
        $found_headline = false;
        foreach ($text_layer_keys as $key) {
            if ($key === $headline_key) {
                $found_headline = true;
                continue;
            }
            if (!$found_headline) {
                $before_headline[] = $key;
            } else {
                $after_headline[] = $key;
            }
        }

        // Map: before headline = eyebrow(s), after headline = subheadline, discount, trust
        $eyebrow_key = $before_headline[0] ?? null;
        $subheadline_key = $after_headline[0] ?? null;
        $discount_key = $after_headline[1] ?? null;
        $trust_key = $after_headline[2] ?? null;

        // 1. Eyebrow - replace or hide when empty
        // Helper: apply font size override per breakpoint (preserves template values when not set)
        $apply_font_size = function(&$layer, $desktop, $tablet, $mobile) {
            if (empty($desktop) && empty($tablet) && empty($mobile)) return;
            $cur = $layer['font']['size'] ?? ['16px', '16px', '#a', '#a', '12px'];
            if (!empty($desktop)) { $cur[0] = $desktop . 'px'; $cur[1] = $desktop . 'px'; }
            if (!empty($tablet))  { $cur[2] = $tablet . 'px';  $cur[3] = $tablet . 'px'; }
            if (!empty($mobile))  { $cur[4] = $mobile . 'px'; }
            $layer['font']['size'] = $cur;
        };

        // Helper: apply font overrides (family, weight, color, size)
        $apply_font = function(&$layer, $prefix, $event) use ($apply_font_size) {
            if (!empty($event[$prefix . '_font_family'])) {
                $layer['font']['family'] = $event[$prefix . '_font_family'];
            }
            if (!empty($event[$prefix . '_font_weight'])) {
                $layer['font']['weight'] = $event[$prefix . '_font_weight'];
            }
            $color_key = $prefix . '_color';
            if (!empty($event[$color_key])) {
                $c = $event[$color_key];
                $layer['color'] = [$c, $c, $c, $c, $c];
            }
            $apply_font_size(
                $layer,
                $event[$prefix . '_font_size_desktop'] ?? null,
                $event[$prefix . '_font_size_tablet'] ?? null,
                $event[$prefix . '_font_size_mobile'] ?? null
            );
        };

        // 1. Eyebrow
        if ($eyebrow_key !== null) {
            $eyebrow_text = $event['eyebrow_text'] ?? '';
            $layers[$eyebrow_key]['content']['text'] = $eyebrow_text;
            if (empty(trim($eyebrow_text))) {
                $layers[$eyebrow_key]['visibility'] = [false, false, false, false, false];
            } else {
                $apply_font($layers[$eyebrow_key], 'eyebrow', $event);
            }
        }

        // 2. Headline
        if ($headline_key !== null) {
            $layers[$headline_key]['content']['text'] = $event['headline'] ?? '';
            $apply_font($layers[$headline_key], 'headline', $event);
        }

        // 3. Subheadline
        if ($subheadline_key !== null) {
            $subheadline_text = $event['subheadline'] ?? '';
            $layers[$subheadline_key]['content']['text'] = $subheadline_text;
            if (empty(trim($subheadline_text))) {
                $layers[$subheadline_key]['visibility'] = [false, false, false, false, false];
            } else {
                $apply_font($layers[$subheadline_key], 'subheadline', $event);
            }
        }

        // 4. Discount
        if ($discount_key !== null) {
            $discount_text = $event['discount_text'] ?? '';
            $layers[$discount_key]['content']['text'] = $discount_text;
            if (empty(trim($discount_text))) {
                $layers[$discount_key]['bg']['color'] = [
                    'orig' => 'transparent', 'type' => 'solid', 'string' => 'transparent',
                ];
                $layers[$discount_key]['visibility'] = [false, false, false, false, false];
            } else {
                // Discount uses _text_color instead of _color
                if (!empty($event['discount_text_color'])) {
                    $c = $event['discount_text_color'];
                    $layers[$discount_key]['color'] = [$c, $c, $c, $c, $c];
                }
                if (!empty($event['discount_font_family'])) {
                    $layers[$discount_key]['font']['family'] = $event['discount_font_family'];
                }
                if (!empty($event['discount_font_weight'])) {
                    $layers[$discount_key]['font']['weight'] = $event['discount_font_weight'];
                }
                $apply_font_size(
                    $layers[$discount_key],
                    $event['discount_font_size'] ?? null,
                    $event['discount_font_size_tablet'] ?? null,
                    $event['discount_font_size_mobile'] ?? null
                );
                if (!empty($event['discount_bg_color'])) {
                    $layers[$discount_key]['bg']['color'] = [
                        'orig' => $event['discount_bg_color'],
                        'type' => 'solid',
                        'string' => $event['discount_bg_color'],
                    ];
                }
            }
            // Fix responsive: template has tablet widths of 481/367px (too wide).
            // Use auto width so badge fits its content on all devices.
            $layers[$discount_key]['size']['w'] = ['auto', 'auto', 'auto', 'auto', 'auto'];
            $layers[$discount_key]['size']['h'] = ['auto', 'auto', 'auto', 'auto', 'auto'];
            // Fix line-height to match font size (template has 40px lh with 14px font)
            $layers[$discount_key]['lh'] = ['22px', '22px', '20px', '18px', '16px'];
            // Fix padding for smaller devices
            $layers[$discount_key]['p'] = [
                't' => [5, 5, 4, 4, 3],
                'b' => [5, 5, 4, 4, 3],
                'l' => [14, 14, 10, 10, 8],
                'r' => [14, 14, 10, 10, 8],
            ];
            // Set explicit font sizes for tablet breakpoints instead of #a
            $cur_fs = $layers[$discount_key]['font']['size'];
            if (($cur_fs[2] ?? '#a') === '#a') $cur_fs[2] = '12px';
            if (($cur_fs[3] ?? '#a') === '#a') $cur_fs[3] = '11px';
            $layers[$discount_key]['font']['size'] = $cur_fs;
        }

        // 5. Trust line
        if ($trust_key !== null) {
            $layers[$trust_key]['content']['text'] = $event['trust_text'] ?? '';
            if (!empty($event['trust_color'])) {
                $c = $event['trust_color'];
                $layers[$trust_key]['color'] = [$c, $c, $c, $c, $c];
            }
            if (!empty($event['trust_font_family'])) {
                $layers[$trust_key]['font']['family'] = $event['trust_font_family'];
            }
            if (!empty($event['trust_font_weight'])) {
                $layers[$trust_key]['font']['weight'] = $event['trust_font_weight'];
            }
            $apply_font_size(
                $layers[$trust_key],
                $event['trust_font_size'] ?? null,
                $event['trust_font_size_tablet'] ?? null,
                $event['trust_font_size_mobile'] ?? null
            );
        }

        // 6. Button
        if ($button_layer_key !== null) {
            $layers[$button_layer_key]['content']['text'] = $event['button_text'] ?? 'SHOP NOW';
            // Button uses _text_color instead of _color
            if (!empty($event['button_text_color'])) {
                $c = $event['button_text_color'];
                $layers[$button_layer_key]['color'] = [$c, $c, $c, $c, $c];
            }
            if (!empty($event['button_font_family'])) {
                $layers[$button_layer_key]['font']['family'] = $event['button_font_family'];
            }
            if (!empty($event['button_font_weight'])) {
                $layers[$button_layer_key]['font']['weight'] = $event['button_font_weight'];
            }
            $apply_font_size(
                $layers[$button_layer_key],
                $event['button_font_size'] ?? null,
                $event['button_font_size_tablet'] ?? null,
                $event['button_font_size_mobile'] ?? null
            );
            if (!empty($event['button_bg_color'])) {
                $layers[$button_layer_key]['bg']['color'] = [
                    'orig' => $event['button_bg_color'],
                    'type' => 'solid',
                    'string' => $event['button_bg_color'],
                ];
            }
            // Fix responsive: template has tablet widths of 481/367px (too wide).
            // Use auto width so button fits its content on all devices.
            $layers[$button_layer_key]['size']['w'] = ['auto', 'auto', 'auto', 'auto', 'auto'];
            $layers[$button_layer_key]['size']['h'] = ['auto', 'auto', 'auto', 'auto', 'auto'];
            // Fix line-height (template has 40px lh with 14px font)
            $layers[$button_layer_key]['lh'] = ['22px', '22px', '20px', '18px', '16px'];
            // Set explicit font sizes for tablet breakpoints instead of #a
            $cur_fs = $layers[$button_layer_key]['font']['size'];
            if (($cur_fs[2] ?? '#a') === '#a') $cur_fs[2] = '12px';
            if (($cur_fs[3] ?? '#a') === '#a') $cur_fs[3] = '11px';
            $layers[$button_layer_key]['font']['size'] = $cur_fs;
            // Fix padding for tablet (template has 10/20 padding on tablet - too big)
            $layers[$button_layer_key]['p'] = [
                't' => [5, 5, 4, 4, 3],
                'b' => [5, 5, 4, 4, 3],
                'l' => [14, 14, 10, 10, 8],
                'r' => [14, 14, 10, 10, 8],
            ];
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
            // Preserve template's size/pos settings; only set defaults if missing
            if (!isset($layers[$bg_layer_key]['bg']['image']['size'])) {
                $layers[$bg_layer_key]['bg']['image']['size'] = 'cover';
            }
            if (!isset($layers[$bg_layer_key]['bg']['image']['pos'])) {
                $layers[$bg_layer_key]['bg']['image']['pos'] = ['x' => '50%', 'y' => '50%'];
            }
        }

        // 6. Text position per device
        // RS7 uses pixel-based positioning (not "left"/"right" keywords).
        // Values based on actual template slides: left-side (#84,86,87) and right-side (#85).
        // Slider widths: [1200, 1200, 1024, 778, 480]
        $pos_desktop = $event['text_position'] ?? 'left';
        $pos_tablet = $event['text_position_tablet'] ?? $pos_desktop;
        $pos_mobile = $event['text_position_mobile'] ?? $pos_desktop;

        // Pixel positions per breakpoint: [desktop, laptop, tablet, tablet-phone, mobile]
        $pos_px = [
            'left'   => ['text' => null, 'button' => null], // keep template values
            'center' => [
                'text'   => ['center', 'center', 'center', 'center', 'center'],
                'button' => ['center', 'center', 'center', 'center', 'center'],
            ],
            'right'  => [
                'text'   => ['680px', '680px', '540px', '380px', '15px'],
                'button' => ['846px', '846px', '540px', '380px', '15px'],
            ],
        ];

        $all_pos_keys = array_filter(
            [$eyebrow_key, $headline_key, $subheadline_key, $discount_key, $trust_key, $button_layer_key],
            function($k) { return $k !== null; }
        );

        if ($pos_desktop !== 'left' || $pos_tablet !== 'left' || $pos_mobile !== 'left') {
            foreach ($all_pos_keys as $lk) {
                if (!isset($layers[$lk])) continue;

                $is_button = ($lk === $button_layer_key);
                $cur_x = $layers[$lk]['pos']['x'] ?? ['0px', '0px', '#a', '#a', '0px'];

                if ($pos_desktop !== 'left') {
                    $px = $pos_px[$pos_desktop] ?? $pos_px['left'];
                    $vals = $is_button ? $px['button'] : $px['text'];
                    if ($vals) { $cur_x[0] = $vals[0]; $cur_x[1] = $vals[1]; }
                }
                if ($pos_tablet !== 'left') {
                    $px = $pos_px[$pos_tablet] ?? $pos_px['left'];
                    $vals = $is_button ? $px['button'] : $px['text'];
                    if ($vals) { $cur_x[2] = $vals[2]; $cur_x[3] = $vals[3]; }
                }
                if ($pos_mobile !== 'left') {
                    $px = $pos_px[$pos_mobile] ?? $pos_px['left'];
                    $vals = $is_button ? $px['button'] : $px['text'];
                    if ($vals) { $cur_x[4] = $vals[4]; }
                }

                $layers[$lk]['pos']['x'] = $cur_x;
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
        global $wpdb;
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
        $event_id = (int) ($event['id'] ?? 0);

        // Find existing event slide (by slug or event ID)
        $existing_slide_id = self::find_event_slide_id($slider_id, $event_slug, $event_id);

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
            // Place after all template slides so it uses the same fly-over transition
            $max_order = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(params, '$.order')) AS UNSIGNED)) FROM {$wpdb->prefix}revslider_slides7 WHERE slider_id = %d AND (static = '' OR static IS NULL)",
                $slider_id
            ));
            $event_params['order'] = $max_order + 1;

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
    public static function remove_event_slide(string $event_slug, int $event_id = 0): array {
        if (!self::rs_available()) {
            return ['success' => false, 'message' => 'Rev Slider classes not available.'];
        }

        $alias = self::get_master_alias();
        $slider = self::get_slider($alias);
        if (!$slider) {
            return ['success' => false, 'message' => 'Slider not found.'];
        }

        $slider_id = $slider->get_id();
        $slide_id = self::find_event_slide_id($slider_id, $event_slug, $event_id);

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
    public static function clear_rs_cache(int $slider_id): void {
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

        // Purge SiteGround page cache
        if (function_exists('sg_cachepress_purge_everything')) {
            sg_cachepress_purge_everything();
        }

        // Purge WP Rocket page cache
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
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
