// jQuery(function ($) {
//   function refreshSinglePreview($field, attachment) {
//     var $preview = $field.find('.justin-media-preview');
//     $preview.html('');

//     if (attachment && attachment.url) {
//       $preview.append($('<img />', {
//         src: attachment.url,
//         alt: ''
//       }));
//     }
//   }

//   function refreshGalleryPreview($field, attachments) {
//     var $preview = $field.find('.justin-media-preview');
//     $preview.html('');

//     attachments.forEach(function (attachment) {
//       if (attachment && attachment.url) {
//         $preview.append($('<img />', {
//           src: attachment.url,
//           alt: ''
//         }));
//       }
//     });
//   }

//   $('body').on('click', '.justin-media-select', function (event) {
//     event.preventDefault();

//     var $button = $(this);
//     var $field = $button.closest('.justin-media-field');
//     var isMultiple = $field.data('multiple') === 1 || $field.data('multiple') === '1';
//     var frame = wp.media({
//       title: isMultiple ? 'Select images' : 'Select image',
//       button: {
//         text: isMultiple ? 'Use images' : 'Use image'
//       },
//       multiple: isMultiple
//     });

//     frame.on('select', function () {
//       var selection = frame.state().get('selection');
//       var attachments = selection.toJSON();
//       var ids = attachments.map(function (item) {
//         return item.id;
//       });

//       $field.find('.justin-media-value').val(isMultiple ? ids.join(',') : ids[0] || '');

//       if (isMultiple) {
//         refreshGalleryPreview($field, attachments);
//       } else {
//         refreshSinglePreview($field, attachments[0]);
//       }
//     });

//     frame.open();
//   });

//   $('body').on('click', '.justin-media-clear', function (event) {
//     event.preventDefault();

//     var $field = $(this).closest('.justin-media-field');
//     $field.find('.justin-media-value').val('');
//     $field.find('.justin-media-preview').html('');
//   });
// });


jQuery(function ($) {
  function buildThumbMarkup(attachment) {
    var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail)
      ? attachment.sizes.thumbnail.url
      : attachment.url;

    var $thumb = $('<div />', { class: 'justin-media-thumb', 'data-id': attachment.id });
    $thumb.append($('<img />', { src: thumbUrl, alt: '' }));
    $thumb.append($('<button />', {
      type: 'button',
      class: 'justin-media-thumb-remove',
      'aria-label': 'Remove image',
      html: '&times;'
    }));
    return $thumb;
  }

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
        $preview.append(buildThumbMarkup(attachment));
      }
    });

    initSortable($field);
  }

  function syncOrderFromDOM($field) {
    var ids = $field.find('.justin-media-thumb').map(function () {
      return $(this).data('id');
    }).get();

    $field.find('.justin-media-value').val(ids.join(','));
  }

  function initSortable($field) {
    var $preview = $field.find('.justin-media-preview');
    if (!$preview.length) {
      return;
    }

    // Already initialized — just tell Sortable the DOM changed
    // (new/removed thumbs) rather than re-binding.
    if ($preview.data('justin-sortable-init')) {
      $preview.sortable('refresh');
      return;
    }

    $preview.sortable({
      items: '.justin-media-thumb',
      tolerance: 'pointer',
      placeholder: 'ui-sortable-placeholder',
      update: function () {
        syncOrderFromDOM($field);
      }
    });

    $preview.data('justin-sortable-init', true);
  }

  // Enable drag-reorder on every multi-image field already rendered
  // by PHP on page load (Gallery images, Book Template images, etc).
  $('.justin-media-field').each(function () {
    var $field = $(this);
    if ($field.data('multiple') === 1 || $field.data('multiple') === '1') {
      initSortable($field);
    }
  });

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

  // Remove a single image from a multi-image field without clearing
  // the rest.
  $('body').on('click', '.justin-media-thumb-remove', function (event) {
    event.preventDefault();

    var $thumb = $(this).closest('.justin-media-thumb');
    var $field = $thumb.closest('.justin-media-field');
    $thumb.remove();
    syncOrderFromDOM($field);
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

  var customTags = [];
  try {
    customTags = JSON.parse($tagsWrap.attr('data-custom-tags') || '[]');
  } catch (e) {
    customTags = [];
  }

  var $customTagManager = $('#justin-custom-tag-manager');

  function renderCustomTagManager() {
    $customTagManager.empty();

    if (!customTags.length) {
      return;
    }

    $customTagManager.append($('<p />', { style: 'margin-bottom:6px; color:#555;', text: 'Your custom tags (click × to delete):' }));

    var $chips = $('<div />', { style: 'display:flex; flex-wrap:wrap; gap:6px;' });

    customTags.forEach(function (tag) {
      var $chip = $('<span />', {
        style: 'display:inline-flex; align-items:center; gap:6px; padding:4px 8px; background:#f0f0f1; border-radius:3px; font-size:12px;'
      }).text(tag);

      var $delBtn = $('<button />', {
        type: 'button',
        text: '×',
        style: 'border:none; background:none; cursor:pointer; font-weight:bold; color:#c00; line-height:1;'
      });

      $delBtn.on('click', function () {
        deleteTag(tag);
      });

      $chip.append($delBtn);
      $chips.append($chip);
    });

    $customTagManager.append($chips);
  }

  function deleteTag(tagName) {
    if (!window.confirm('Delete "' + tagName + '"? This removes it from every image it\'s currently assigned to.')) {
      return;
    }

    $.post(JUSTIN_ADMIN.ajaxUrl, {
      action: 'justin_delete_photo_grid_tag',
      nonce: JUSTIN_ADMIN.nonce,
      tag: tagName
    }).done(function (response) {
      if (response && response.success) {
        allTags = response.data.tags;
        customTags = customTags.filter(function (tag) {
          return tag.toLowerCase() !== tagName.toLowerCase();
        });
        $tagsWrap.attr('data-tags', JSON.stringify(allTags));
        $tagsWrap.attr('data-custom-tags', JSON.stringify(customTags));
        renderCustomTagManager();
        renderTagList();
      } else {
        window.alert((response && response.data && response.data.message) || 'Could not delete tag.');
      }
    }).fail(function () {
      window.alert('Network error — please try again.');
    });
  }

  renderCustomTagManager();

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

  var $newTagInput = $('#justin-new-tag-input');
  var $addTagBtn = $('#justin-add-tag-btn');
  var $addTagError = $('#justin-add-tag-error');

  function addNewTag() {
    var tagName = ($newTagInput.val() || '').trim();
    $addTagError.text('');

    if (!tagName) {
      return;
    }

    var alreadyExists = allTags.some(function (tag) {
      return tag.toLowerCase() === tagName.toLowerCase();
    });

    if (alreadyExists) {
      $addTagError.text('That tag already exists.');
      return;
    }

    $addTagBtn.prop('disabled', true);

    $.post(JUSTIN_ADMIN.ajaxUrl, {
      action: 'justin_add_photo_grid_tag',
      nonce: JUSTIN_ADMIN.nonce,
      tag: tagName
    }).done(function (response) {
    if (response && response.success) {
      allTags = response.data.tags;
      customTags.push(response.data.added);
      $tagsWrap.attr('data-tags', JSON.stringify(allTags));
      $tagsWrap.attr('data-custom-tags', JSON.stringify(customTags));
      $newTagInput.val('');
      renderCustomTagManager();
      renderTagList();
    } else {
        $addTagError.text((response && response.data && response.data.message) || 'Could not add tag.');
      }
    }).fail(function () {
      $addTagError.text('Network error — please try again.');
    }).always(function () {
      $addTagBtn.prop('disabled', false);
    });
  }

  $addTagBtn.on('click', addNewTag);
  $newTagInput.on('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      addNewTag();
    }
  });

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
