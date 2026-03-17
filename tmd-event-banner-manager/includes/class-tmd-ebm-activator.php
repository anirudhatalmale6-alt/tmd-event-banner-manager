<?php
if (!defined('ABSPATH')) exit;

class TMD_EBM_Activator {
    public static function activate() {
        global $wpdb;
        $table = TMD_EBM_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_name VARCHAR(255) NOT NULL,
            event_slug VARCHAR(120) NOT NULL,
            banner_type VARCHAR(80) NOT NULL DEFAULT 'event',
            phase VARCHAR(40) NOT NULL DEFAULT 'main',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            priority INT NOT NULL DEFAULT 10,
            headline TEXT NULL,
            subheadline TEXT NULL,
            description TEXT NULL,
            discount_text VARCHAR(255) NULL,
            button_text VARCHAR(255) NULL,
            button_link TEXT NULL,
            background_image_id BIGINT UNSIGNED NULL,
            background_image_url TEXT NULL,
            countdown_date DATETIME NULL,
            canvas_width INT NOT NULL DEFAULT 1200,
            canvas_height INT NOT NULL DEFAULT 400,
            overlay_color VARCHAR(20) NULL,
            overlay_opacity DECIMAL(4,2) NULL,
            eyebrow_text VARCHAR(255) NULL,
            eyebrow_font_family VARCHAR(120) NULL,
            eyebrow_font_size_desktop INT NULL,
            eyebrow_font_size_tablet INT NULL,
            eyebrow_font_size_mobile INT NULL,
            eyebrow_font_weight VARCHAR(40) NULL,
            eyebrow_color VARCHAR(20) NULL,
            eyebrow_x INT NULL,
            eyebrow_y INT NULL,
            headline_font_family VARCHAR(120) NULL,
            headline_font_size_desktop INT NULL,
            headline_font_size_tablet INT NULL,
            headline_font_size_mobile INT NULL,
            headline_font_weight VARCHAR(40) NULL,
            headline_color VARCHAR(20) NULL,
            headline_x INT NULL,
            headline_y INT NULL,
            subheadline_font_family VARCHAR(120) NULL,
            subheadline_font_size_desktop INT NULL,
            subheadline_font_size_tablet INT NULL,
            subheadline_font_size_mobile INT NULL,
            subheadline_font_weight VARCHAR(40) NULL,
            subheadline_color VARCHAR(20) NULL,
            subheadline_x INT NULL,
            subheadline_y INT NULL,
            discount_font_family VARCHAR(120) NULL,
            discount_font_size INT NULL,
            discount_font_size_tablet INT NULL,
            discount_font_size_mobile INT NULL,
            discount_font_weight VARCHAR(40) NULL,
            discount_text_color VARCHAR(20) NULL,
            discount_bg_color VARCHAR(20) NULL,
            discount_border_radius INT NULL,
            discount_x INT NULL,
            discount_y INT NULL,
            button_font_family VARCHAR(120) NULL,
            button_font_size INT NULL,
            button_font_size_tablet INT NULL,
            button_font_size_mobile INT NULL,
            button_font_weight VARCHAR(40) NULL,
            button_text_color VARCHAR(20) NULL,
            button_bg_color VARCHAR(20) NULL,
            button_hover_bg_color VARCHAR(20) NULL,
            button_border_radius INT NULL,
            button_x INT NULL,
            button_y INT NULL,
            trust_text VARCHAR(255) NULL,
            trust_font_family VARCHAR(120) NULL,
            trust_font_size INT NULL,
            trust_font_size_tablet INT NULL,
            trust_font_size_mobile INT NULL,
            trust_font_weight VARCHAR(40) NULL,
            trust_color VARCHAR(20) NULL,
            trust_x INT NULL,
            trust_y INT NULL,
            style_preset VARCHAR(80) NULL,
            headline_animation_in VARCHAR(80) NULL,
            headline_animation_duration INT NULL,
            subheadline_animation_in VARCHAR(80) NULL,
            subheadline_animation_duration INT NULL,
            discount_animation_in VARCHAR(80) NULL,
            button_animation_in VARCHAR(80) NULL,
            trust_animation_in VARCHAR(80) NULL,
            image_animation_in VARCHAR(80) NULL,
            show_discount TINYINT(1) NOT NULL DEFAULT 1,
            show_countdown TINYINT(1) NOT NULL DEFAULT 0,
            show_trust TINYINT(1) NOT NULL DEFAULT 0,
            coupon_code VARCHAR(120) NULL,
            text_position VARCHAR(20) NOT NULL DEFAULT 'left',
            text_position_tablet VARCHAR(20) NOT NULL DEFAULT 'left',
            text_position_mobile VARCHAR(20) NOT NULL DEFAULT 'left',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY event_slug_phase (event_slug, phase, start_date),
            KEY idx_date (start_date, end_date),
            KEY idx_priority (priority)
        ) {$charset_collate};";

        dbDelta($sql);

        if (!wp_next_scheduled('tmd_ebm_refresh_event')) {
            wp_schedule_event(time(), 'hourly', 'tmd_ebm_refresh_event');
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled('tmd_ebm_refresh_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'tmd_ebm_refresh_event');
        }
    }
}
