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
      // Immediately swap name to hidden empty input so form submits '' not '#ffffff'
      var name = $input.attr('name');
      $input.removeAttr('name');
      var $hidden = $('<input type="hidden">').attr('name', name).val('');
      $input.after($hidden);
      $input.data('orig-name', name);
      $input.css('opacity', '0.4');
      // Create a clear button next to color input
      var $clear = $('<span class="tmd-color-clear" title="Clear (inherit from template)" style="cursor:pointer;font-size:14px;margin-left:2px;color:#999;">&#x2715;</span>');
      $input.after($clear);
      $clear.on('click', function(){
        var n = $input.data('orig-name');
        $input.val('#ffffff').attr('data-empty', '1').css('opacity', '0.4');
        $input.removeAttr('name');
        $input.siblings('input[type="hidden"][name="' + n + '"]').remove();
        var $h = $('<input type="hidden">').attr('name', n).val('');
        $input.after($h);
      });
    }
  });
  // When color is changed from empty state, restore name so the picked color submits
  $('.tmd-ebm-inline-style input[type="color"]').on('input', function(){
    var $input = $(this);
    if ($input.attr('data-empty') === '1') {
      var name = $input.data('orig-name');
      $input.removeAttr('data-empty').css('opacity', '1');
      // Remove hidden placeholder and restore name on the color input
      $input.siblings('input[type="hidden"][name="' + name + '"]').remove();
      $input.attr('name', name);
    }
  });
});
