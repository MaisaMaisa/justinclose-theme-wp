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

jQuery(function ($) {
  var $tagsWrap = $('#justin-gallery-tag-assign');
  if (!$tagsWrap.length) {
    return;
  }

  var allTags = [];
  try {
    allTags = JSON.parse($tagsWrap.attr('data-tags') || '[]');
  } catch (e) {
    allTags = [];
  }

  var $tagsInput = $('#gallery_image_tags');
  var $tagList = $tagsWrap.find('.justin-gallery-tag-list');
  var $galleryValueInput = $('input.justin-media-value[name="gallery"]');
  var lastGalleryValue = null;

  function getTagsMap() {
    try {
      var parsed = JSON.parse($tagsInput.val() || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (e) {
      return {};
    }
  }

  function setTagsMap(map) {
    $tagsInput.val(JSON.stringify(map));
  }

  function getGalleryIds() {
    var raw = $galleryValueInput.val() || '';
    return raw.split(',').map(function (value) {
      return value.trim();
    }).filter(function (value) {
      return value.length > 0;
    });
  }

  function renderTagList() {
    var ids = getGalleryIds();
    var tagsMap = getTagsMap();
    var cleanedMap = {};
    $tagList.empty();

    if (!ids.length) {
      $tagList.append($('<p />', { text: 'No gallery images selected yet.' }));
      setTagsMap(cleanedMap);
      return;
    }

    var $thumbs = $tagsWrap.closest('.inside').find('.justin-media-preview img');

    ids.forEach(function (id, index) {
      var existingTags = Array.isArray(tagsMap[id]) ? tagsMap[id] : [];
      cleanedMap[id] = existingTags;

      var $row = $('<div />', {
        class: 'justin-gallery-tag-row',
        style: 'display:flex; gap:12px; align-items:flex-start; padding:8px 0; border-bottom:1px solid #ddd;'
      });

      var $thumbWrap = $('<div />', { style: 'flex-shrink:0; width:60px;' });
      var $thumbImg = $thumbs.eq(index).clone();
      if ($thumbImg.length) {
        $thumbImg.css({ width: '60px', height: '60px', objectFit: 'cover', display: 'block' });
        $thumbWrap.append($thumbImg);
      }

      var $checks = $('<div />', { style: 'display:flex; flex-wrap:wrap; gap:6px 14px;' });

      allTags.forEach(function (tag) {
        var checkboxId = 'gallery-tag-' + id + '-' + tag.replace(/\s+/g, '-');
        var $label = $('<label />', { style: 'font-size:12px; white-space:nowrap;', for: checkboxId });
        var $checkbox = $('<input />', {
          type: 'checkbox',
          id: checkboxId,
          value: tag,
          checked: existingTags.indexOf(tag) !== -1
        });

        $checkbox.on('change', function () {
          var map = getTagsMap();
          var current = Array.isArray(map[id]) ? map[id] : [];
          if ($checkbox.is(':checked')) {
            if (current.indexOf(tag) === -1) {
              current.push(tag);
            }
          } else {
            current = current.filter(function (value) {
              return value !== tag;
            });
          }
          map[id] = current;
          setTagsMap(map);
        });

        $label.append($checkbox).append(' ' + tag);
        $checks.append($label);
      });

      $row.append($thumbWrap).append($checks);
      $tagList.append($row);
    });

    setTagsMap(cleanedMap);
  }

  renderTagList();

  // Polling instead of hooking into the existing wp.media select/clear
  // handlers, so this never touches that shared code path.
  setInterval(function () {
    var currentValue = $galleryValueInput.val() || '';
    if (currentValue !== lastGalleryValue) {
      lastGalleryValue = currentValue;
      renderTagList();
    }
  }, 700);
});
