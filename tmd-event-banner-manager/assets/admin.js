jQuery(function($){
  // Tab switching
  $('.tmd-ebm-tabs .nav-tab').on('click', function(e){
    e.preventDefault();
    var tab = $(this).data('tab');
    // Update tab nav
    $('.tmd-ebm-tabs .nav-tab').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');
    // Show/hide panes
    $('.tmd-ebm-tab-pane').removeClass('active');
    $('.tmd-ebm-tab-pane[data-tab="' + tab + '"]').addClass('active');
  });

  // Preset dropdown → fills the text input below
  $('.tmd-preset-select').on('change', function(){
    var val = $(this).val();
    var targetId = $(this).data('target');
    if (val) {
      $('#' + targetId).val(val);
    }
    // Reset dropdown to placeholder after filling
    $(this).val('');
  });

  // Media uploader
  $('.tmd-ebm-media-button').on('click', function(e){
    e.preventDefault();
    var targetId = $(this).data('target-id');
    var targetUrl = $(this).data('target-url');

    var frame = wp.media({
      title: 'Select Banner Image',
      button: { text: 'Use Image' },
      multiple: false
    });

    frame.on('select', function(){
      var attachment = frame.state().get('selection').first().toJSON();
      $('#' + targetId).val(attachment.id || '');
      $('#' + targetUrl).val(attachment.url || '');
      // Update preview image
      var $preview = $('#' + targetUrl).siblings('.tmd-ebm-img-preview');
      if ($preview.length) {
        $preview.attr('src', attachment.url || '').show();
      }
    });

    frame.open();
  });

  // Color pickers: handle empty state
  // When data-empty="1", color input shows default color but should submit empty
  $('.tmd-ebm-inline-style input[type="color"]').each(function(){
    var $input = $(this);
    if ($input.attr('data-empty') === '1') {
      // Create a clear button next to color input
      var $clear = $('<span class="tmd-color-clear" title="Clear (inherit from template)" style="cursor:pointer;font-size:14px;margin-left:2px;color:#999;">&#x2715;</span>');
      $input.after($clear);
      $input.css('opacity', '0.4');
      $clear.on('click', function(){
        $input.val('').attr('data-empty', '1').css('opacity', '0.4');
        // Add a hidden input to override the color value
        $input.siblings('input[type="hidden"][name="' + $input.attr('name') + '"]').remove();
        var $hidden = $('<input type="hidden">').attr('name', $input.attr('name')).val('');
        $input.after($hidden);
        $input.removeAttr('name'); // prevent color input from submitting
      });
    }
  });
  // When color is changed from empty state, restore it
  $('.tmd-ebm-inline-style input[type="color"]').on('input', function(){
    var $input = $(this);
    if ($input.attr('data-empty') === '1') {
      $input.removeAttr('data-empty').css('opacity', '1');
      // Restore name if it was removed
      var $hidden = $input.siblings('input[type="hidden"]');
      if ($hidden.length) {
        $input.attr('name', $hidden.attr('name'));
        $hidden.remove();
      }
    }
  });
});
