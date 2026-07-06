jQuery(function ($) {
  function refreshSinglePreview($field, attachment) {
    var $preview = $field.find('.justin-media-preview');
    $preview.html('');

    if (attachment && attachment.url) {
      $preview.append($('<img />', {
        src: attachment.url,
        alt: ''
      }));
    }
  }

  function refreshGalleryPreview($field, attachments) {
    var $preview = $field.find('.justin-media-preview');
    $preview.html('');

    attachments.forEach(function (attachment) {
      if (attachment && attachment.url) {
        $preview.append($('<img />', {
          src: attachment.url,
          alt: ''
        }));
      }
    });
  }

  $('body').on('click', '.justin-media-select', function (event) {
    event.preventDefault();

    var $button = $(this);
    var $field = $button.closest('.justin-media-field');
    var isMultiple = $field.data('multiple') === 1 || $field.data('multiple') === '1';
    var frame = wp.media({
      title: isMultiple ? 'Select images' : 'Select image',
      button: {
        text: isMultiple ? 'Use images' : 'Use image'
      },
      multiple: isMultiple
    });

    frame.on('select', function () {
      var selection = frame.state().get('selection');
      var attachments = selection.toJSON();
      var ids = attachments.map(function (item) {
        return item.id;
      });

      $field.find('.justin-media-value').val(isMultiple ? ids.join(',') : ids[0] || '');

      if (isMultiple) {
        refreshGalleryPreview($field, attachments);
      } else {
        refreshSinglePreview($field, attachments[0]);
      }
    });

    frame.open();
  });

  $('body').on('click', '.justin-media-clear', function (event) {
    event.preventDefault();

    var $field = $(this).closest('.justin-media-field');
    $field.find('.justin-media-value').val('');
    $field.find('.justin-media-preview').html('');
  });
});
