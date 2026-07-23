(function ($) {
  'use strict';

  function applyMasonryRatios(root) {
    var isElement = typeof Element !== 'undefined' && root instanceof Element;
    var context = isElement ? root : document;
    var images = context.querySelectorAll('.zlfms-masonry-gallery .zlfms-gallery-img');

    if (!images.length) {
      return;
    }

    Array.prototype.forEach.call(images, function (el) {
      var ratio = parseFloat(el.getAttribute('data-ratio'));
      if (!isNaN(ratio) && ratio > 0) {
        el.style.setProperty('--zlfms-masonry-item-ratio', ratio + '%');
        if (!el.style.paddingBottom) {
          el.style.paddingBottom = ratio + '%';
        }
      }
    });
  }

  function initMasonry($scope) {
    if ($scope && $scope.length && $scope[0]) {
      applyMasonryRatios($scope[0]);
    } else {
      applyMasonryRatios(document);
    }
  }

  $(document).ready(function () {
    initMasonry();
  });

  var resizeTimer = null;
  $(window).on('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      initMasonry();
    }, 150);
  });

  $(window).on('elementor/frontend/init', function () {
    elementorFrontend.hooks.addAction('frontend/element_ready/advanced_image_gallery.default', initMasonry);
  });
})(jQuery);
