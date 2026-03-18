<?php if (!defined('ABSPATH')) exit;
$e = $edit_event ?: [];
$is_edit = !empty($e);
$v = function($key, $default = '') use ($e) { return esc_attr($e[$key] ?? $default); };
// Helper: render inline style controls (color, font-size, font-weight) for a text layer
$style_row = function($prefix, $opts = []) use ($v) {
    $color_field = $opts['color_field'] ?? ($prefix . '_color');
    $size_field = $opts['size_field'] ?? ($prefix . '_font_size_desktop');
    if (in_array($prefix, ['discount', 'button', 'trust'])) {
        $size_field = $prefix . '_font_size';
    }
    if ($prefix === 'discount') $color_field = 'discount_text_color';
    if ($prefix === 'button') $color_field = 'button_text_color';
    $weight_field = $prefix . '_font_weight';
    $family_field = $prefix . '_font_family';
    $has_bg = !empty($opts['has_bg']);
    $bg_field = $prefix . '_bg_color';
    ?>
    <div class="tmd-ebm-inline-style" style="margin-top:6px;padding:8px 12px;background:#f9f9f9;border:1px solid #e2e2e2;border-radius:4px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#666;">
            Color
            <input type="color" name="<?php echo $color_field; ?>" value="<?php echo esc_attr($v($color_field) ?: '#ffffff'); ?>" style="width:32px;height:28px;padding:0;border:1px solid #ccc;cursor:pointer;"<?php echo empty($v($color_field)) ? ' data-empty="1"' : ''; ?>>
        </label>
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#666;">
            Size
            <input type="number" name="<?php echo $size_field; ?>" value="<?php echo $v($size_field); ?>" style="width:55px;height:28px;" placeholder="Auto" min="6" max="120">
            <span style="font-size:11px;">px</span>
        </label>
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#666;">
            Weight
            <select name="<?php echo $weight_field; ?>" style="height:28px;font-size:12px;">
                <option value="" <?php selected($v($weight_field), ''); ?>>Auto</option>
                <option value="300" <?php selected($v($weight_field), '300'); ?>>Light</option>
                <option value="400" <?php selected($v($weight_field), '400'); ?>>Regular</option>
                <option value="600" <?php selected($v($weight_field), '600'); ?>>Semi-Bold</option>
                <option value="700" <?php selected($v($weight_field), '700'); ?>>Bold</option>
                <option value="800" <?php selected($v($weight_field), '800'); ?>>Extra Bold</option>
                <option value="900" <?php selected($v($weight_field), '900'); ?>>Black</option>
            </select>
        </label>
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#666;">
            Font
            <select name="<?php echo $family_field; ?>" style="height:28px;font-size:12px;max-width:140px;">
                <option value="" <?php selected($v($family_field), ''); ?>>Template Default</option>
                <option value="Poppins" <?php selected($v($family_field), 'Poppins'); ?>>Poppins</option>
                <option value="Montserrat" <?php selected($v($family_field), 'Montserrat'); ?>>Montserrat</option>
                <option value="Oswald" <?php selected($v($family_field), 'Oswald'); ?>>Oswald</option>
                <option value="Playfair Display" <?php selected($v($family_field), 'Playfair Display'); ?>>Playfair Display</option>
                <option value="Roboto" <?php selected($v($family_field), 'Roboto'); ?>>Roboto</option>
                <option value="Open Sans" <?php selected($v($family_field), 'Open Sans'); ?>>Open Sans</option>
                <option value="Lato" <?php selected($v($family_field), 'Lato'); ?>>Lato</option>
                <option value="Raleway" <?php selected($v($family_field), 'Raleway'); ?>>Raleway</option>
                <option value="Georgia" <?php selected($v($family_field), 'Georgia'); ?>>Georgia</option>
                <option value="Impact" <?php selected($v($family_field), 'Impact'); ?>>Impact</option>
            </select>
        </label>
        <?php if ($has_bg): ?>
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#666;">
            BG
            <input type="color" name="<?php echo $bg_field; ?>" value="<?php echo esc_attr($v($bg_field) ?: '#e63946'); ?>" style="width:32px;height:28px;padding:0;border:1px solid #ccc;cursor:pointer;"<?php echo empty($v($bg_field)) ? ' data-empty="1"' : ''; ?>>
        </label>
        <?php endif; ?>
    </div>
    <?php
};
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
    <?php if (!empty($_GET['published'])): ?>
        <div class="notice notice-success is-dismissible"><p>Event published and banner slide created.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['unpublished'])): ?>
        <div class="notice notice-info is-dismissible"><p>Event unpublished and banner slide removed.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['slide_on'])): ?>
        <div class="notice notice-success is-dismissible"><p>Slide turned ON (published).</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['slide_off'])): ?>
        <div class="notice notice-info is-dismissible"><p>Slide turned OFF (unpublished).</p></div>
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
                                <?php if (!empty($evt['is_active'])): ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('tmd_ebm_toggle_publish'); ?>
                                    <input type="hidden" name="action" value="tmd_ebm_toggle_publish">
                                    <input type="hidden" name="event_id" value="<?php echo (int)$evt['id']; ?>">
                                    <input type="hidden" name="new_state" value="0">
                                    <button class="button button-small" style="color:#d63638;">Unpublish</button>
                                </form>
                                <?php else: ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('tmd_ebm_toggle_publish'); ?>
                                    <input type="hidden" name="action" value="tmd_ebm_toggle_publish">
                                    <input type="hidden" name="event_id" value="<?php echo (int)$evt['id']; ?>">
                                    <input type="hidden" name="new_state" value="1">
                                    <button class="button button-small" style="color:#00a32a;">Publish</button>
                                </form>
                                <?php endif; ?>
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
        <h2>Homepage Slider Overview</h2>
        <p class="description">All slides in the homepage slider (<?php echo esc_html($target_alias); ?>). Use the On/Off toggle to show or hide any slide.</p>
        <table class="widefat striped tmd-ebm-events-table">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th style="width:40px;">Order</th>
                    <th>Title</th>
                    <th style="width:120px;">Type</th>
                    <th style="width:80px;">Status</th>
                    <th style="width:180px;">Preview</th>
                    <th style="width:80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($banner_slides as $bs):
                    $is_on = ($bs['publish_state'] ?? 'published') === 'published';
                ?>
                    <tr<?php echo !$is_on ? ' style="opacity:0.5;"' : ''; ?>>
                        <td><?php echo $bs['id']; ?></td>
                        <td><?php echo $bs['order']; ?></td>
                        <td><?php echo esc_html($bs['title']); ?></td>
                        <td>
                            <?php if ($bs['event_slug']): ?>
                                <span style="color:#0a0;font-weight:bold;">Event: <?php echo esc_html($bs['event_slug']); ?></span>
                            <?php else: ?>
                                <span style="color:#06c;">Manual</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($is_on): ?>
                                <span style="color:#00a32a;font-weight:bold;">ON</span>
                            <?php else: ?>
                                <span style="color:#d63638;font-weight:bold;">OFF</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($bs['bg_url']): ?>
                                <img src="<?php echo esc_url($bs['bg_url']); ?>" style="max-width:160px;height:auto;border:1px solid #ddd;border-radius:3px;">
                            <?php else: ?>
                                <span style="color:#999;">No preview</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                <?php wp_nonce_field('tmd_ebm_toggle_slide'); ?>
                                <input type="hidden" name="action" value="tmd_ebm_toggle_slide">
                                <input type="hidden" name="slide_id" value="<?php echo $bs['id']; ?>">
                                <?php if ($is_on): ?>
                                    <input type="hidden" name="new_state" value="unpublished">
                                    <button class="button button-small" style="color:#d63638;">Turn Off</button>
                                <?php else: ?>
                                    <input type="hidden" name="new_state" value="published">
                                    <button class="button button-small" style="color:#00a32a;">Turn On</button>
                                <?php endif; ?>
                            </form>
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
                        <th>Text Position</th>
                        <td>
                            <select name="text_position">
                                <option value="left" <?php selected($v('text_position','left'), 'left'); ?>>Left</option>
                                <option value="center" <?php selected($v('text_position','left'), 'center'); ?>>Center</option>
                                <option value="right" <?php selected($v('text_position','left'), 'right'); ?>>Right</option>
                            </select>
                            <p class="description">Position of text layers on the banner</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Eyebrow Text</th>
                        <td>
                            <select class="tmd-preset-select" data-target="eyebrow_text">
                                <option value="">-- Pick a preset or type below --</option>
                                <option value="SPRING SAVINGS EVENT">SPRING SAVINGS EVENT</option>
                                <option value="LIMITED TIME OFFER">LIMITED TIME OFFER</option>
                                <option value="HOLIDAY SPECIAL">HOLIDAY SPECIAL</option>
                                <option value="NEW COLLECTION">NEW COLLECTION</option>
                                <option value="FLASH SALE">FLASH SALE</option>
                                <option value="CLEARANCE EVENT">CLEARANCE EVENT</option>
                                <option value="DEALS YOU'LL LOVE">DEALS YOU'LL LOVE</option>
                                <option value="SMART LIVING">SMART LIVING</option>
                                <option value="STYLE ESSENTIALS">STYLE ESSENTIALS</option>
                            </select>
                            <input type="text" name="eyebrow_text" id="eyebrow_text" value="<?php echo $v('eyebrow_text'); ?>" placeholder="Type or select above" style="margin-top:4px;">
                            <?php $style_row('eyebrow'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Headline</th>
                        <td>
                            <select class="tmd-preset-select" data-target="headline">
                                <option value="">-- Pick a preset or type below --</option>
                                <option value="EASTER SALE">EASTER SALE</option>
                                <option value="EASTER<br>WEEKEND DEALS">EASTER WEEKEND DEALS</option>
                                <option value="SUMMER SALE">SUMMER SALE</option>
                                <option value="BLACK FRIDAY">BLACK FRIDAY</option>
                                <option value="CHRISTMAS DEALS">CHRISTMAS DEALS</option>
                                <option value="FLASH SALE">FLASH SALE</option>
                                <option value="NEW ARRIVALS">NEW ARRIVALS</option>
                                <option value="LIMITED TIME<br>DEALS">LIMITED TIME DEALS</option>
                                <option value="FASHION &amp;<br>ACCESSORIES">FASHION & ACCESSORIES</option>
                                <option value="TECH &amp;<br>SMART GADGETS">TECH & SMART GADGETS</option>
                            </select>
                            <textarea name="headline" id="headline" rows="2" required placeholder="Type or select above (Enter = new line)" style="margin-top:4px;width:100%;"><?php echo esc_textarea(str_replace(["<br>","<br/>","<br />"], "\n", $e["headline"] ?? "")); ?></textarea>
                            <?php $style_row('headline'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Subheadline</th>
                        <td>
                            <select class="tmd-preset-select" data-target="subheadline">
                                <option value="">-- Pick a preset or type below --</option>
                                <option value="Celebrate with limited time savings">Celebrate with limited time savings</option>
                                <option value="Top trending products at unmatched prices">Top trending products at unmatched prices</option>
                                <option value="Upgrade your everyday with smart deals">Upgrade your everyday with smart deals</option>
                                <option value="New season styles and trending looks">New season styles and trending looks</option>
                                <option value="Don't miss these incredible deals">Don't miss these incredible deals</option>
                                <option value="Shop the season's best picks">Shop the season's best picks</option>
                            </select>
                            <textarea name="subheadline" id="subheadline" rows="2" placeholder="Type or select above (Enter = new line)" style="margin-top:4px;width:100%;"><?php echo esc_textarea(str_replace(["<br>","<br/>","<br />"], "\n", $e["subheadline"] ?? "")); ?></textarea>
                            <?php $style_row('subheadline'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><textarea name="description" rows="3" placeholder="Optional longer copy"><?php echo esc_textarea($e['description'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th>Discount Text</th>
                        <td>
                            <select class="tmd-preset-select" data-target="discount_text">
                                <option value="">-- Pick a preset or type below --</option>
                                <option value="UP TO 30% OFF">UP TO 30% OFF</option>
                                <option value="UP TO 50% OFF">UP TO 50% OFF</option>
                                <option value="UP TO 60% OFF">UP TO 60% OFF</option>
                                <option value="UP TO 70% OFF">UP TO 70% OFF</option>
                                <option value="BUY 1 GET 1 FREE">BUY 1 GET 1 FREE</option>
                                <option value="FREE SHIPPING">FREE SHIPPING</option>
                                <option value="NEW ARRIVALS">NEW ARRIVALS</option>
                                <option value="TOP SELLERS">TOP SELLERS</option>
                            </select>
                            <input type="text" name="discount_text" id="discount_text" value="<?php echo $v('discount_text'); ?>" placeholder="Type or select above" style="margin-top:4px;">
                            <?php $style_row('discount', ['has_bg' => true]); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Coupon Code</th>
                        <td><input type="text" name="coupon_code" value="<?php echo $v('coupon_code'); ?>" placeholder="EASTER2026">
                        <p class="description">Optional WooCommerce coupon code for this event</p></td>
                    </tr>
                    <tr>
                        <th>Button Text</th>
                        <td>
                            <select class="tmd-preset-select" data-target="button_text">
                                <option value="">-- Pick a preset or type below --</option>
                                <option value="SHOP NOW">SHOP NOW</option>
                                <option value="SHOP DEALS">SHOP DEALS</option>
                                <option value="SHOP EASTER DEALS">SHOP EASTER DEALS</option>
                                <option value="SHOP FASHION">SHOP FASHION</option>
                                <option value="SHOP TECH DEALS">SHOP TECH DEALS</option>
                                <option value="BROWSE COLLECTION">BROWSE COLLECTION</option>
                            </select>
                            <input type="text" name="button_text" id="button_text" value="<?php echo $v('button_text','SHOP NOW'); ?>" placeholder="Type or select above" style="margin-top:4px;">
                            <?php $style_row('button', ['has_bg' => true]); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Button Link</th>
                        <td><input type="url" name="button_link" value="<?php echo $v('button_link'); ?>" placeholder="https://trendymalldeals.com/shop/"></td>
                    </tr>
                    <tr>
                        <th>Trust Line</th>
                        <td>
                            <select class="tmd-preset-select" data-target="trust_text">
                                <option value="">-- Pick a preset or type below --</option>
                                <option value="Free Shipping &bull; Easy Returns &bull; Secure Checkout">Free Shipping . Easy Returns . Secure Checkout</option>
                                <option value="Free Shipping &bull; Secure Checkout &bull; Easy Returns">Free Shipping . Secure Checkout . Easy Returns</option>
                                <option value="Fast Delivery &bull; Secure Payment &bull; 30-Day Returns">Fast Delivery . Secure Payment . 30-Day Returns</option>
                            </select>
                            <input type="text" name="trust_text" id="trust_text" value="<?php echo $v('trust_text','Free Shipping • Easy Returns • Secure Checkout'); ?>" placeholder="Type or select above" style="margin-top:4px;">
                            <?php $style_row('trust'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Background Image</th>
                        <td>
                            <input type="hidden" name="background_image_id" id="background_image_id" value="<?php echo $v('background_image_id'); ?>">
                            <input type="text" name="background_image_url" id="background_image_url" value="<?php echo $v('background_image_url'); ?>" style="width:calc(100% - 110px);" placeholder="Image URL">
                            <button type="button" class="button tmd-ebm-media-button" data-target-id="background_image_id" data-target-url="background_image_url">Browse</button>
                            <br><img class="tmd-ebm-img-preview" src="<?php echo esc_url($e['background_image_url'] ?? ''); ?>" style="max-width:400px;height:auto;margin-top:8px;border:1px solid #ddd;<?php echo empty($e['background_image_url']) ? 'display:none;' : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>Countdown Date</th>
                        <td><input type="datetime-local" name="countdown_date" value="<?php echo $v('countdown_date'); ?>"></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <?php if ($is_edit): ?>
                                <?php if (!empty($e['is_active'])): ?>
                                    <span style="color:#00a32a;font-weight:bold;">Published (live on site)</span>
                                <?php else: ?>
                                    <span style="color:#999;">Draft (not live)</span>
                                <?php endif; ?>
                                <input type="hidden" name="is_active" value="<?php echo !empty($e['is_active']) ? '1' : '0'; ?>">
                            <?php else: ?>
                                <span style="color:#888;">Status will be set by Publish or Save as Draft button below</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ==================== DESKTOP TAB ==================== -->
            <div class="tmd-ebm-tab-pane" data-tab="desktop">
                <table class="form-table">
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

                <p class="description" style="margin:10px 0;padding:8px;background:#f0f6fc;border-left:3px solid #2271b1;">Font, color, weight and family are set in the Content tab (inline style controls). Use this tab for positioning, sizing and animations.</p>

                <!-- Eyebrow -->
                <h3 class="tmd-ebm-layer-title">Eyebrow</h3>
                <table class="form-table">
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
                        <th>Border Radius</th>
                        <td><input type="number" name="discount_border_radius" value="<?php echo $v('discount_border_radius'); ?>" style="width:80px;" placeholder="Auto"> px</td>
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
                        <th>Hover BG Color</th>
                        <td><input type="text" name="button_hover_bg_color" value="<?php echo $v('button_hover_bg_color'); ?>" style="width:100px;" placeholder="Inherited"></td>
                    </tr>
                    <tr>
                        <th>Border Radius</th>
                        <td><input type="number" name="button_border_radius" value="<?php echo $v('button_border_radius'); ?>" style="width:80px;" placeholder="Auto"> px</td>
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
                        <td><input type="number" name="eyebrow_font_size_tablet" value="<?php echo $v('eyebrow_font_size_tablet'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Headline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="headline_font_size_tablet" value="<?php echo $v('headline_font_size_tablet'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Subheadline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="subheadline_font_size_tablet" value="<?php echo $v('subheadline_font_size_tablet'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Discount Badge</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="discount_font_size_tablet" value="<?php echo $v('discount_font_size_tablet'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Button</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="button_font_size_tablet" value="<?php echo $v('button_font_size_tablet'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Trust Line</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="trust_font_size_tablet" value="<?php echo $v('trust_font_size_tablet'); ?>" style="width:80px;" placeholder="Auto"> px</td>
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
                        <td><input type="number" name="eyebrow_font_size_mobile" value="<?php echo $v('eyebrow_font_size_mobile'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Headline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="headline_font_size_mobile" value="<?php echo $v('headline_font_size_mobile'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Subheadline</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="subheadline_font_size_mobile" value="<?php echo $v('subheadline_font_size_mobile'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Discount Badge</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="discount_font_size_mobile" value="<?php echo $v('discount_font_size_mobile'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Button</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="button_font_size_mobile" value="<?php echo $v('button_font_size_mobile'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>

                <h3 class="tmd-ebm-layer-title">Trust Line</h3>
                <table class="form-table">
                    <tr>
                        <th>Font Size</th>
                        <td><input type="number" name="trust_font_size_mobile" value="<?php echo $v('trust_font_size_mobile'); ?>" style="width:80px;" placeholder="Auto"> px</td>
                    </tr>
                </table>
            </div>

            <p class="tmd-ebm-submit">
                <?php if ($is_edit): ?>
                    <?php if (!empty($e['is_active'])): ?>
                        <button name="save_as" value="publish" class="button button-primary button-large">Update Event</button>
                        <button name="save_as" value="draft" class="button button-large" style="margin-left:8px;color:#d63638;">Unpublish</button>
                    <?php else: ?>
                        <button name="save_as" value="publish" class="button button-primary button-large" style="background:#00a32a;border-color:#00a32a;color:#fff;">Publish Event</button>
                        <button name="save_as" value="draft" class="button button-large" style="margin-left:8px;">Save as Draft</button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=tmd-ebm')); ?>" class="button button-large" style="margin-left:8px;">Cancel</a>
                <?php else: ?>
                    <button name="save_as" value="publish" class="button button-primary button-large">Publish Event</button>
                    <button name="save_as" value="draft" class="button button-large" style="margin-left:8px;">Save as Draft</button>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>
