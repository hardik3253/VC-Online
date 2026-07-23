(function ($) {
  'use strict';

  function parseSize(value, fallback) {
    var parsed = parseFloat(value);
    return isNaN(parsed) ? fallback : parsed;
  }

  function layoutJustifiedContainer(container) {
    var items = container.querySelectorAll('.zlfms-gallery-item');
    if (!items.length) {
      return;
    }

    var styles = window.getComputedStyle(container);
    var rowHeight = parseSize(styles.getPropertyValue('--zlfms-justified-row-height'), 220);
    var gap = parseSize(styles.getPropertyValue('--zlfms-justified-gutter'), parseSize(styles.getPropertyValue('gap'), 15));
    var containerWidth = container.clientWidth;

    if (!containerWidth) {
      return;
    }

    Array.prototype.forEach.call(items, function (item) {
      item.style.flex = '';
      item.style.width = '';
      item.style.height = rowHeight + 'px';
      var media = item.querySelector('.zlfms-gallery-img');
      if (media) {
        media.style.minHeight = rowHeight + 'px';
      }
    });

    var currentRow = [];
    var aspectSum = 0;

    Array.prototype.forEach.call(items, function (item, index) {
      var width = parseSize(item.getAttribute('data-width'), rowHeight);
      var height = parseSize(item.getAttribute('data-height'), rowHeight);
      var aspect = width / height;

      currentRow.push({ item: item, aspect: aspect });
      aspectSum += aspect;

      var isLast = index === items.length - 1;
      var rowWidth = rowHeight * aspectSum + gap * Math.max(currentRow.length - 1, 0);

      if (rowWidth >= containerWidth || isLast) {
        var availableWidth = containerWidth - gap * Math.max(currentRow.length - 1, 0);
        var targetHeight = aspectSum > 0 ? availableWidth / aspectSum : rowHeight;
        targetHeight = Math.max(targetHeight, 40);

        currentRow.forEach(function (entry) {
          var targetWidth = entry.aspect * targetHeight;
          entry.item.style.flex = '0 0 ' + targetWidth + 'px';
          entry.item.style.width = targetWidth + 'px';
          entry.item.style.height = targetHeight + 'px';
          var media = entry.item.querySelector('.zlfms-gallery-img');
          if (media) {
            media.style.minHeight = targetHeight + 'px';
            media.style.height = '100%';
          }
        });

        currentRow = [];
        aspectSum = 0;
      }
    });
  }

  function layoutAllJustified(root) {
    var isElement = typeof Element !== 'undefined' && root instanceof Element;
    var context = isElement ? root : document;
    var containers = context.querySelectorAll('.zlfms-justified-gallery .zlfms_gallery');
    Array.prototype.forEach.call(containers, function (container) {
      layoutJustifiedContainer(container);
    });
  }

  var resizeTimer = null;
  function handleResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      layoutAllJustified(document);
    }, 150);
  }

  $(document).ready(function () {
    layoutAllJustified(document);
  });

  $(window).on('resize', handleResize);

  $(window).on('elementor/frontend/init', function () {
    elementorFrontend.hooks.addAction('frontend/element_ready/advanced_image_gallery.default', function ($scope) {
      if ($scope && $scope.length && $scope[0]) {
        layoutAllJustified($scope[0]);
      } else {
        layoutAllJustified(document);
      }
    });
  });
})(jQuery);
