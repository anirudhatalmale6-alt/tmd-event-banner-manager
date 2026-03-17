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
    });

    frame.open();
  });
});
