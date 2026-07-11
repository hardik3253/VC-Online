(function () {
	'use strict';

	function setSrcFromData(img) {
		if (!img || img.tagName !== 'IMG') return;
		var hasSrc = img.getAttribute('src');
		var dataSrc = img.getAttribute('data-src') || img.getAttribute('data-lazy-src');
		if (!hasSrc && dataSrc) {
			img.setAttribute('src', dataSrc);
			var dataSrcset = img.getAttribute('data-srcset') || img.getAttribute('data-lazy-srcset');
			if (dataSrcset) {
				img.setAttribute('srcset', dataSrcset);
			}
		}
	}

	function fixLightboxImages(root) {
		var context = root && root.querySelectorAll ? root : document;
		var candidates = context.querySelectorAll('.elementor-lightbox img, .dialog-lightbox img, .elementor-lightbox-content img');
		if (!candidates.length) return;
		Array.prototype.forEach.call(candidates, function (img) {
			setSrcFromData(img);
		});
	}

	function scheduleFixes() {
		// Run several times shortly after open to catch late DOM injections
		var attempts = 8;
		var interval = 100;
		var timer = setInterval(function () {
			fixLightboxImages(document);
			attempts--;
			if (attempts <= 0) clearInterval(timer);
		}, interval);
	}

	// Hook: clicking any elementor lightbox trigger
	document.addEventListener('click', function (e) {
		var target = e.target;
		if (!target) return;
		// Walk up to link
		var el = target.closest && target.closest('a[data-elementor-open-lightbox="yes"]');
		if (el) {
			scheduleFixes();
		}
	}, true);

	// Observe lightbox containers for added images
	var observer = new MutationObserver(function (mutations) {
		for (var i = 0; i < mutations.length; i++) {
			var m = mutations[i];
			if (m.type === 'childList') {
				Array.prototype.forEach.call(m.addedNodes || [], function (node) {
					if (!node || !(node.nodeType === 1)) return;
					if (node.matches && (node.matches('.elementor-lightbox, .dialog-lightbox, .elementor-lightbox-content') || node.querySelector && (node.querySelector('.elementor-lightbox, .dialog-lightbox, .elementor-lightbox-content')))) {
						fixLightboxImages(node);
					}
				});
			}
		}
	});

	try {
		observer.observe(document.documentElement || document.body, { childList: true, subtree: true });
	} catch (e) {}

	// Elementor frontend hook (defensive)
	if (window.jQuery && window.elementorFrontend) {
		window.jQuery(window).on('elementor/lightbox/show elementor/frontend/init', function () {
			scheduleFixes();
		});
	}
})();


