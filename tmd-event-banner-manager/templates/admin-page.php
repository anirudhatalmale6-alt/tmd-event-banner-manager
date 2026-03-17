<?php if (!defined('ABSPATH')) exit;
$e = $edit_event ?: [];
$is_edit = !empty($e);
$v = function($key, $default = '') use ($e) { return esc_attr($e[$key] ?? $default); };
?>
<div class="wrap tmd-ebm-wrap">
    <h1>Event Banner Manager</h1>

    <?php if (!empty($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible"><p>Event saved successfully.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p>Slider update executed.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
        <div class="notice notice-warning is-dismissible"><p>Event deleted.</p></div>
    <?php endif; ?>

    <!-- EVENT LIST -->
    <div class="tmd-ebm-panel">
        <h2>All Events</h2>
        <table class="widefat striped tmd-ebm-events-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Event Name</th>
                    <th>Slug</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:20px;">No events yet. Create your first event below.</td></tr>
                <?php else: ?>
                    <?php foreach ($events as $evt):
                        $today = current_time('Y-m-d');
                        $is_current = (!empty($evt['is_active']) && $evt['start_date'] <= $today && $evt['end_date'] >= $today);
                        $status = '';
                        if (!$evt['is_active']) {
                            $status = '<span style="color:#999;">Disabled</span>';
                        } elseif ($is_current) {
                            $status = '<span style="color:#0a0;font-weight:bold;">ACTIVE NOW</span>';
                        } elseif ($evt['start_date'] > $today) {
                            $status = '<span style="color:#06c;">Scheduled</span>';
                        } else {
                            $status = '<span style="color:#999;">Expired</span>';
                        }
                    ?>
                        <tr<?php echo $is_current ? ' style="background:#eeffee;"' : ''; ?>>
                            <td><?php echo (int)$evt['id']; ?></td>
                            <td><strong><?php echo esc_html($evt['event_name']); ?></strong></td>
                            <td><code><?php echo esc_html($evt['event_slug']); ?></code></td>
                            <td><?php echo esc_html($evt['start_date']); ?></td>
                            <td><?php echo esc_html($evt['end_date']); ?></td>
                            <td><?php echo (int)$evt['priority']; ?></td>
                            <td><?php echo $status; ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tmd-ebm&edit=' . $evt['id'])); ?>" class="button button-small">Edit</a>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('Delete this event?');">
                                    <?php wp_nonce_field('tmd_ebm_delete_event'); ?>
                                    <input type="hidden" name="action" value="tmd_ebm_delete_event">
                                    <input type="hidden" name="event_id" value="<?php echo (int)$evt['id']; ?>">
                                    <button class="button button-small" style="color:#a00;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tmd-ebm-actions" style="margin-top:12px;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                <?php wp_nonce_field('tmd_ebm_run_update'); ?>
                <input type="hidden" name="action" value="tmd_ebm_run_update">
                <button class="button button-secondary">Refresh Active Event Now</button>
            </form>
            <?php if ($active_slug): ?>
                <span style="margin-left:16px;">Current active slug: <strong><?php echo esc_html($active_slug); ?></strong></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- BANNER SLIDES OVERVIEW -->
    <?php if (!empty($banner_slides)): ?>
    <div class="tmd-ebm-panel" style="margin-top:24px;">
        <h2>Banner Slides Overview</h2>
        <p class="description">All slides currently in the Banner slider. Event slides are managed here; manual slides can be edited in Slider Revolution.</p>
        <table class="widefat striped tmd-ebm-events-table">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th style="width:40px;">Order</th>
                    <th>Title</th>
                    <th style="width:120px;">Type</th>
                    <th style="width:50px;">Layers</th>
                    <th style="width:180px;">Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($banner_slides as $bs): ?>
                    <tr>
                        <td><?php echo $bs['id']; ?></td>
                        <td><?php echo $bs['order']; ?></td>
                        <td><?php echo esc_html($bs['title']); ?></td>
                        <td>
                            <?php if ($bs['is_global']): ?>
                                <span style="color:#999;">Global Layers</span>
                            <?php elseif ($bs['event_slug']): ?>
                                <span style="color:#0a0;font-weight:bold;">Event: <?php echo esc_html($bs['event_slug']); ?></span>
                            <?php else: ?>
                                <span style="color:#06c;">Manual</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?php echo $bs['layer_count']; ?></td>
                        <td>
                            <?php if ($bs['bg_url']): ?>
                                <img src="<?php echo esc_url($bs['bg_url']); ?>" style="max-width:160px;height:auto;border:1px solid #ddd;border-radius:3px;">
                            <?php else: ?>
                                <span style="color:#999;">No preview</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- EVENT FORM -->
    <div class="tmd-ebm-panel" style="margin-top:24px;">
        <h2><?php echo $is_edit ? 'Edit Event #' . (int)$e['id'] : 'Create New Event'; ?></h2>
        <?php if ($is_edit): ?>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=tmd-ebm')); ?>">&larr; Cancel editing, create new instead</a></p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tmd-ebm-form">
            <?php wp_nonce_field('tmd_ebm_save_event'); ?>
            <input type="hidden" name="action" value="tmd_ebm_save_event">
            <input type="hidden" name="event_id" value="<?php echo $v('id', '0'); ?>">

            <!-- TAB NAVIGATION -->
            <nav class="nav-tab-wrapper tmd-ebm-tabs">
                <a href="#" class="nav-tab nav-tab-active" data-tab="content">Content</a>
                <a href="#" class="nav-tab" data-tab="desktop">Desktop</a>
                <a href="#" class="nav-tab" data-tab="tablet">Tablet</a>
                <a href="#" class="nav-tab" data-tab="mobile">Mobile</a>
            </nav>

            <!-- ==================== CONTENT TAB ==================== -->
            <div class="tmd-ebm-tab-pane active" data-tab="content">
                <table class="form-table">
                    <tr>
                        <th>Event Name</th>
                        <td><input type="text" name="event_name" value="<?php echo $v('event_name'); ?>" required placeholder="Easter Sale"></td>
                    </tr>
                    <tr>
                        <th>Event Slug</th>
                        <td><input type="text" name="event_slug" value="<?php echo $v('event_slug'); ?>" required placeholder="easter">
                        <p class="description">Used to match product categories and identify the event</p></td>
                    </tr>
                    <tr>
                        <th>Banner Type</th>
                        <td><select name="banner_type">
                            <?php foreach (['event'=>'Shopping Event / Promotion','fashion'=>'Fashion & Accessories','gadget'=>'Tech & Smart Gadgets','other'=>'Other'] as $bk=>$bl): ?>
                                <option value="<?php echo $bk; ?>" <?php selected($v('banner_type','event'), $bk); ?>><?php echo $bl; ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th>Phase</th>
                        <td><select name="phase">
                            <?php foreach (['pre'=>'Pre-Event','main'=>'Main','last-chance'=>'Last Chance'] as $pk=>$pl): ?>
                                <option value="<?php echo $pk; ?>" <?php selected($v('phase','main'), $pk); ?>><?php echo $pl; ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th>Start Date</th>
                        <td><input type="date" name="start_date" value="<?php echo $v('start_date'); ?>" required></td>
                    </tr>
                    <tr>
                        <th>End Date</th>
                        <td><input type="date" name="end_date" value="<?php echo $v('end_date'); ?>" required></td>
                    </tr>
                    <tr>
                        <th>Priority</th>
                        <td><input type="number" name="priority" value="<?php echo $v('priority','10'); ?>" min="1" max="99">
                        <p class="description">Lower number = higher priority when dates overlap</p></td>
                    </tr>
                    <tr>
                        <th>Eyebrow Text</th>
                        <td><input type="text" name="eyebrow_text" value="<?php echo $v('eyebrow_text'); ?>" placeholder="SPRING SAVINGS EVENT"></td>
                    </tr>
                    <tr>
                        <th>Headline</th>
                        <td><input type="text" name="headline" value="<?php echo $v('headline'); ?>" required placeholder="EASTER SALE"></td>
                    </tr>
                    <tr>
                        <th>Subheadline</th>
                        <td><input type="text" name="subheadline" value="<?php echo $v('subheadline'); ?>" placeholder="Celebrate with limited time savings"></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><textarea name="description" rows="3" placeholder="Optional longer copy"><?php echo esc_textarea($e['description'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th>Discount Text</th>
                        <td><input type="text" name="discount_text" value="<?php echo $v('discount_text'); ?>" placeholder="UP TO 50% OFF"></td>
                    </tr>
                    <tr>
                        <th>Coupon Code</th>
                        <td><input type="text" name="coupon_code" value="<?php echo $v('coupon_code'); ?>" placeholder="EASTER2026">
                        <p class="description">Optional WooCommerce coupon code for this event</p></td>
                    </tr>
                    <tr>
                        <th>Button Text</th>
                        <td><input type="text" name="button_text" value="<?php echo $v('button_text','SHOP NOW'); ?>" placeholder="SHOP NOW"></td>
                    </tr>
                    <tr>
                        <th>Button Link</th>
                        <td><input type="url" name="button_link" value="<?php echo $v('button_link'); ?>" placeholder="https://trendymalldeals.com/shop/"></td>
                    </tr>
                    <tr>
                        <th>Trust Line</th>
                        <td><input type="text" name="trust_text" value="<?php echo $v('trust_text','Free Shipping • Easy Returns • Secure Checkout'); ?>"></td>
                    </tr>
                    <tr>
                        <th>Background Image</th>
                        <td>
                            <input type="hidden" name="background_image_id" id="background_image_id" value="<?php echo $v('background_image_id'); ?>">
                            <input type="text" name="background_image_url" id="background_image_url" value="<?php echo $v('background_image_url'); ?>" class="regular-text" placeholder="Image URL">
                            <button type="button" class="button tmd-ebm-media-button" data-target-id="background_image_id" data-target-url="background_image_url">Browse</button>
                            <?php if (!empty($e['background_image_url'])): ?>
                                <br><img src="<?php echo esc_url($e['background_image_url']); ?>" style="max-width:400px;height:auto;margin-top:8px;border:1px solid #ddd;">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Countdown Date</th>
                        <td><input type="datetime-local" name="countdown_date" value="<?php echo $v('countdown_date'); ?>"></td>
                    </tr>
                    <tr>
                        <th>Active</th>
                        <td><label><input type="checkbox" name="is_active" <?php checked(!empty($e['is_active']) || !$is_edit); ?>> Enabled</label></td>
                    </tr>
                </table>
            </div>

            <!-- ==================== DESKTOP TAB ==================== -->
            <div class="tmd-ebm-tab-pane" data-tab="desktop">
                <table class="form-table">
                    <tr>
                        <th>Text Position</th>
                        <td>
                            <select name="text_position">
                                <option value="left" <?php selected($v('text_position','left'), 'left'); ?>>Left</option>
                                <option value="center" <?php selected($v('text_position','left'), 'center'); ?>>Center</option>
                                <option value="right" <?php selected($v('text_position','left'), 'right'); ?>>Right</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Canvas Size</th>
                        <td>
                            <input type="number" name="canvas_width" value="<?php echo $v('canvas_width','1200'); ?>" style="width:100px;"> x
                            <input type="number" name="canvas_height" value="<?php echo $v('canvas_height','400'); ?>" style="width:100px;"> px
                        </td>
                    </tr>
                    <tr>
                        <th>Overlay Color / Opacity</th>
                        <td>
                            <input type="text" name="overlay_color" value="<?php echo $v('overlay_color','#000000'); ?>" style="width:100px;">
                            <input type="number" step="0.01" min="0" max="1" name="overlay_opacity" value="<?php echo $v('overlay_opacity','0.35'); ?>" style="width:80px;">
                        </td>
                    </tr>
                    <tr>
                        <th>Style Preset</th>
                        <td><select name="style_preset">
                            <?php foreach (['default_sale'=>'Default Sale','fashion_banner'=>'Fashion Banner','holiday_banner'=>'Holiday Banner','gadget_banner'=>'Gadget Banner'] as $sk=>$sl): ?>
                                <option value="<?php echo $sk; ?>" <?php selected($v('style_preset','default_sale'), $sk); ?>><?php echo $sl; ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                </table>

                <!-- Eyebrow -->
                <h3 class="tmd-ebm-layer-title">Eyebrow</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Family</th>
                        <td><input type="text" name="eyebrow_font_family" value="<?php echo $v('eyebrow_font_family','Montserrat SemiBold'); ?>" style="width:250px;"></td>
                    </tr>
                    <tr>
                        <th>Font Weight</th>
                        <td><input type="text" name="eyebrow_font_weight" value="<?php echo $v('eyebrow_font_weight','600'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="eyebrow_font_size_desktop" value="<?php echo $v('eyebrow_font_size_desktop','16'); ?>" style="width:80px;"> px</td>
                    </tr>
                    <tr>
                        <th>Color</th>
                        <td><input type="text" name="eyebrow_color" value="<?php echo $v('eyebrow_color','#FFD400'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Position X / Y</th>
                        <td>
                            <input type="number" name="eyebrow_x" value="<?php echo $v('eyebrow_x','80'); ?>" style="width:80px;">
                            <input type="number" name="eyebrow_y" value="<?php echo $v('eyebrow_y','70'); ?>" style="width:80px;">
                        </td>
                    </tr>
                </table>

                <!-- Headline -->
                <h3 class="tmd-ebm-layer-title">Headline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Family</th>
                        <td><input type="text" name="headline_font_family" value="<?php echo $v('headline_font_family','Montserrat ExtraBold'); ?>" style="width:250px;"></td>
                    </tr>
                    <tr>
                        <th>Font Weight</th>
                        <td><input type="text" name="headline_font_weight" value="<?php echo $v('headline_font_weight','800'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="headline_font_size_desktop" value="<?php echo $v('headline_font_size_desktop','58'); ?>" style="width:80px;"> px</td>
                    </tr>
                    <tr>
                        <th>Color</th>
                        <td><input type="text" name="headline_color" value="<?php echo $v('headline_color','#FFFFFF'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Position X / Y</th>
                        <td>
                            <input type="number" name="headline_x" value="<?php echo $v('headline_x','80'); ?>" style="width:80px;">
                            <input type="number" name="headline_y" value="<?php echo $v('headline_y','110'); ?>" style="width:80px;">
                        </td>
                    </tr>
                    <tr>
                        <th>Max Width</th>
                        <td><input type="number" name="headline_max_width" value="<?php echo $v('headline_max_width','500'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <!-- Subheadline -->
                <h3 class="tmd-ebm-layer-title">Subheadline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Family</th>
                        <td><input type="text" name="subheadline_font_family" value="<?php echo $v('subheadline_font_family','Open Sans SemiBold'); ?>" style="width:250px;"></td>
                    </tr>
                    <tr>
                        <th>Font Weight</th>
                        <td><input type="text" name="subheadline_font_weight" value="<?php echo $v('subheadline_font_weight','600'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="subheadline_font_size_desktop" value="<?php echo $v('subheadline_font_size_desktop','22'); ?>" style="width:80px;"> px</td>
                    </tr>
                    <tr>
                        <th>Color</th>
                        <td><input type="text" name="subheadline_color" value="<?php echo $v('subheadline_color','#FFFFFF'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Position X / Y</th>
                        <td>
                            <input type="number" name="subheadline_x" value="<?php echo $v('subheadline_x','80'); ?>" style="width:80px;">
                            <input type="number" name="subheadline_y" value="<?php echo $v('subheadline_y','190'); ?>" style="width:80px;">
                        </td>
                    </tr>
                </table>

                <!-- Discount Badge -->
                <h3 class="tmd-ebm-layer-title">Discount Badge</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Family</th>
                        <td><input type="text" name="discount_font_family" value="<?php echo $v('discount_font_family','Montserrat'); ?>" style="width:250px;"></td>
                    </tr>
                    <tr>
                        <th>Font Weight</th>
                        <td><input type="text" name="discount_font_weight" value="<?php echo $v('discount_font_weight','700'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="discount_font_size" value="<?php echo $v('discount_font_size','16'); ?>" style="width:80px;"> px</td>
                    </tr>
                    <tr>
                        <th>Text Color</th>
                        <td><input type="text" name="discount_text_color" value="<?php echo $v('discount_text_color','#FFFFFF'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Background Color</th>
                        <td><input type="text" name="discount_bg_color" value="<?php echo $v('discount_bg_color','#FF5A36'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Border Radius</th>
                        <td><input type="number" name="discount_border_radius" value="<?php echo $v('discount_border_radius','20'); ?>" style="width:80px;"> px</td>
                    </tr>
                    <tr>
                        <th>Position X / Y</th>
                        <td>
                            <input type="number" name="discount_x" value="<?php echo $v('discount_x','80'); ?>" style="width:80px;">
                            <input type="number" name="discount_y" value="<?php echo $v('discount_y','245'); ?>" style="width:80px;">
                        </td>
                    </tr>
                </table>

                <!-- Button -->
                <h3 class="tmd-ebm-layer-title">Button</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Family</th>
                        <td><input type="text" name="button_font_family" value="<?php echo $v('button_font_family','Montserrat'); ?>" style="width:250px;"></td>
                    </tr>
                    <tr>
                        <th>Font Weight</th>
                        <td><input type="text" name="button_font_weight" value="<?php echo $v('button_font_weight','700'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="button_font_size" value="<?php echo $v('button_font_size','17'); ?>" style="width:80px;"> px</td>
                    </tr>
                    <tr>
                        <th>Text Color</th>
                        <td><input type="text" name="button_text_color" value="<?php echo $v('button_text_color','#FFFFFF'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Background Color</th>
                        <td><input type="text" name="button_bg_color" value="<?php echo $v('button_bg_color','#0B2C48'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Hover BG Color</th>
                        <td><input type="text" name="button_hover_bg_color" value="<?php echo $v('button_hover_bg_color','#154A75'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Border Radius</th>
                        <td><input type="number" name="button_border_radius" value="<?php echo $v('button_border_radius','6'); ?>" style="width:80px;"> px</td>
                    </tr>
                    <tr>
                        <th>Position X / Y</th>
                        <td>
                            <input type="number" name="button_x" value="<?php echo $v('button_x','80'); ?>" style="width:80px;">
                            <input type="number" name="button_y" value="<?php echo $v('button_y','295'); ?>" style="width:80px;">
                        </td>
                    </tr>
                </table>

                <!-- Trust Line -->
                <h3 class="tmd-ebm-layer-title">Trust Line</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Family</th>
                        <td><input type="text" name="trust_font_family" value="<?php echo $v('trust_font_family','Open Sans'); ?>" style="width:250px;"></td>
                    </tr>
                    <tr>
                        <th>Font Weight</th>
                        <td><input type="text" name="trust_font_weight" value="<?php echo $v('trust_font_weight','400'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="trust_font_size" value="<?php echo $v('trust_font_size','14'); ?>" style="width:80px;"> px</td>
                    </tr>
                    <tr>
                        <th>Color</th>
                        <td><input type="text" name="trust_color" value="<?php echo $v('trust_color','#D9D9D9'); ?>" style="width:100px;"></td>
                    </tr>
                    <tr>
                        <th>Position X / Y</th>
                        <td>
                            <input type="number" name="trust_x" value="<?php echo $v('trust_x','80'); ?>" style="width:80px;">
                            <input type="number" name="trust_y" value="<?php echo $v('trust_y','345'); ?>" style="width:80px;">
                        </td>
                    </tr>
                </table>

                <!-- Animations -->
                <h3 class="tmd-ebm-layer-title">Animations</h3>
                <table class="form-table">
                    <tr>
                        <th>Headline</th>
                        <td><input type="text" name="headline_animation_in" value="<?php echo $v('headline_animation_in','fadeInUp'); ?>" style="width:140px;"> Duration: <input type="number" name="headline_animation_duration" value="<?php echo $v('headline_animation_duration','600'); ?>" style="width:80px;"> ms</td>
                    </tr>
                    <tr>
                        <th>Subheadline</th>
                        <td><input type="text" name="subheadline_animation_in" value="<?php echo $v('subheadline_animation_in','fadeIn'); ?>" style="width:140px;"> Duration: <input type="number" name="subheadline_animation_duration" value="<?php echo $v('subheadline_animation_duration','800'); ?>" style="width:80px;"> ms</td>
                    </tr>
                    <tr>
                        <th>Discount</th>
                        <td><input type="text" name="discount_animation_in" value="<?php echo $v('discount_animation_in','zoomIn'); ?>" style="width:140px;"></td>
                    </tr>
                    <tr>
                        <th>Button</th>
                        <td><input type="text" name="button_animation_in" value="<?php echo $v('button_animation_in','slideInUp'); ?>" style="width:140px;"></td>
                    </tr>
                    <tr>
                        <th>Trust</th>
                        <td><input type="text" name="trust_animation_in" value="<?php echo $v('trust_animation_in','fadeIn'); ?>" style="width:140px;"></td>
                    </tr>
                    <tr>
                        <th>Image</th>
                        <td><input type="text" name="image_animation_in" value="<?php echo $v('image_animation_in','zoomIn'); ?>" style="width:140px;"></td>
                    </tr>
                </table>

                <!-- Visibility -->
                <h3 class="tmd-ebm-layer-title">Visibility</h3>
                <table class="form-table">
                    <tr>
                        <th>Show / Hide</th>
                        <td>
                            <label><input type="checkbox" name="show_discount" <?php checked(!empty($e['show_discount']) || !$is_edit); ?>> Show Discount Badge</label><br>
                            <label><input type="checkbox" name="show_countdown" <?php checked(!empty($e['show_countdown'])); ?>> Show Countdown</label><br>
                            <label><input type="checkbox" name="show_trust" <?php checked(!empty($e['show_trust'])); ?>> Show Trust Line</label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ==================== TABLET TAB ==================== -->
            <div class="tmd-ebm-tab-pane" data-tab="tablet">
                <p class="tmd-ebm-inherit-note">Font families, colors, weights, and positions inherit from Desktop. Only font sizes and text alignment can be overridden here.</p>

                <table class="form-table">
                    <tr>
                        <th>Text Position</th>
                        <td>
                            <select name="text_position_tablet">
                                <option value="left" <?php selected($v('text_position_tablet','left'), 'left'); ?>>Left</option>
                                <option value="center" <?php selected($v('text_position_tablet','left'), 'center'); ?>>Center</option>
                                <option value="right" <?php selected($v('text_position_tablet','left'), 'right'); ?>>Right</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Eyebrow</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="eyebrow_font_size_tablet" value="<?php echo $v('eyebrow_font_size_tablet','14'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Headline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="headline_font_size_tablet" value="<?php echo $v('headline_font_size_tablet','42'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Subheadline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="subheadline_font_size_tablet" value="<?php echo $v('subheadline_font_size_tablet','18'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Discount Badge</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="discount_font_size_tablet" value="<?php echo $v('discount_font_size_tablet','14'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Button</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="button_font_size_tablet" value="<?php echo $v('button_font_size_tablet','15'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Trust Line</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="trust_font_size_tablet" value="<?php echo $v('trust_font_size_tablet','12'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>
            </div>

            <!-- ==================== MOBILE TAB ==================== -->
            <div class="tmd-ebm-tab-pane" data-tab="mobile">
                <p class="tmd-ebm-inherit-note">Font families, colors, weights, and positions inherit from Desktop. Only font sizes and text alignment can be overridden here.</p>

                <table class="form-table">
                    <tr>
                        <th>Text Position</th>
                        <td>
                            <select name="text_position_mobile">
                                <option value="left" <?php selected($v('text_position_mobile','left'), 'left'); ?>>Left</option>
                                <option value="center" <?php selected($v('text_position_mobile','left'), 'center'); ?>>Center</option>
                                <option value="right" <?php selected($v('text_position_mobile','left'), 'right'); ?>>Right</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Eyebrow</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="eyebrow_font_size_mobile" value="<?php echo $v('eyebrow_font_size_mobile','12'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Headline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="headline_font_size_mobile" value="<?php echo $v('headline_font_size_mobile','32'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Subheadline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="subheadline_font_size_mobile" value="<?php echo $v('subheadline_font_size_mobile','16'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Discount Badge</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="discount_font_size_mobile" value="<?php echo $v('discount_font_size_mobile','12'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Button</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="button_font_size_mobile" value="<?php echo $v('button_font_size_mobile','14'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Trust Line</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="trust_font_size_mobile" value="<?php echo $v('trust_font_size_mobile','11'); ?>" style="width:80px;"> px</td>
                    </tr>
                </table>
            </div>

            <p class="tmd-ebm-submit">
                <button class="button button-primary button-large"><?php echo $is_edit ? 'Update Event' : 'Create Event'; ?></button>
                <?php if ($is_edit): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=tmd-ebm')); ?>" class="button button-large" style="margin-left:8px;">Cancel</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>
