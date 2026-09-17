(function ($) {
  'use strict';

  function initMetro($scope) {
    var context = ($scope && $scope.length && $scope[0]) ? $scope[0] : document;
    var isElement = typeof Element !== 'undefined' && context instanceof Element;
    context = isElement ? context : document;
    var galleries = context.querySelectorAll('.zlfms-metro-gallery');

    Array.prototype.forEach.call(galleries, function (gallery) {
      if (!gallery.classList.contains('zlfms-metro-initialized')) {
        gallery.classList.add('zlfms-metro-initialized');
      }
      var grid = gallery.querySelector('.zlfms_gallery');
      if (grid) {
        var baseRow = grid.style.getPropertyValue('--zlfms-metro-row-height');
        if (baseRow) {
          grid.style.setProperty('grid-auto-rows', baseRow);
        }
      }
    });
  }

  $(document).ready(function () {
    initMetro();
  });

  $(window).on('elementor/frontend/init', function () {
    elementorFrontend.hooks.addAction('frontend/element_ready/advanced_image_gallery.default', initMetro);
  });
})(jQuery);
