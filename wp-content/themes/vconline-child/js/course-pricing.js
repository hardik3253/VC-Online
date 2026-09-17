/**
 * Custom Tutor LMS Pro Pricing Section JS
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Find the price block on the page
        var $priceBlock = $('.tutor-course-sidebar-card-pricing');
        if (!$priceBlock.length) {
            return; // No pricing block found on this page
        }

        // Get localized variables with safe fallbacks
        var courseId = typeof vcaCoursePricing !== 'undefined' ? vcaCoursePricing.courseId : null;
        if (!courseId) {
            // Fallback: extract course ID from body class or post class if possible
            var bodyClass = $('body').attr('class') || '';
            var match = bodyClass.match(/postid-(\d+)/) || bodyClass.match(/course-id-(\d+)/);
            if (match) {
                courseId = match[1];
            } else {
                courseId = 'default';
            }
        }

        var regularPrice = typeof vcaCoursePricing !== 'undefined' ? parseFloat(vcaCoursePricing.regularPrice) : 0;
        var salePrice = typeof vcaCoursePricing !== 'undefined' ? parseFloat(vcaCoursePricing.salePrice) : 0;
        var isOnSale = typeof vcaCoursePricing !== 'undefined' ? vcaCoursePricing.isOnSale : false;

        // Fallback: Parse prices from the DOM if PHP localization is absent or inactive
        var $delTag = $priceBlock.find('del');
        var hasDel = $delTag.length > 0;
        
        if (hasDel && (!regularPrice || !salePrice)) {
            var regText = $delTag.text();
            // Clone block, remove del tag to extract the clean sale price text
            var $clone = $priceBlock.clone();
            $clone.find('del').remove();
            var saleText = $clone.text();

            var parsedReg = parseFloat(regText.replace(/[^\d\.]/g, ''));
            var parsedSale = parseFloat(saleText.replace(/[^\d\.]/g, ''));

            if (!isNaN(parsedReg) && !isNaN(parsedSale) && parsedSale < parsedReg) {
                regularPrice = parsedReg;
                salePrice = parsedSale;
                isOnSale = true;
            }
        }

        // 1. Render Dynamic Discount Badge
        if (isOnSale && regularPrice > 0) {
            var discountPercent = Math.round(((regularPrice - salePrice) / regularPrice) * 100);
            if (discountPercent > 0 && !$priceBlock.find('.vco-discount-badge').length) {
                var badgeHtml = '<span class="vco-discount-badge">' + discountPercent + '% OFF</span>';
                
                // Append badge next to the prices (del / regular price wrapper)
                var $priceContainer = $priceBlock.find('div').first();
                if ($priceContainer.length) {
                    $priceContainer.append(badgeHtml);
                } else {
                    $priceBlock.append(badgeHtml);
                }
            }
        }

        // 2. Render & Initialize Countdown Timer & Coupon Notice
        if (isOnSale) {
            // Inject containers if they don't exist (e.g. when Elementor widgets bypass standard hooks)
            var $existingExtras = $('.vco-pricing-extras');
            if (!$existingExtras.length) {
                var pricingExtrasHtml = 
                    '<div class="vco-pricing-extras" data-course-id="' + courseId + '">' +
                        '<div class="vco-countdown-wrapper tutor-mt-16">' +
                            '<span class="vco-countdown-label">' +
                                '<span class="tutor-icon-clock tutor-mr-8"></span>24 hours left at this price' +
                            '</span>' +
                            '<div class="vco-countdown-timer" id="vco-countdown-' + courseId + '">24:00:00</div>' +
                        '</div>' +
                        '<div class="vco-coupon-notice tutor-mt-12">' +
                            '<div class="vco-coupon-left">' +
                                '<span class="vco-coupon-icon">✓</span>' +
                                '<span class="vco-coupon-code">VCWELCOME</span>' +
                            '</div>' +
                            '<span class="vco-coupon-applied-text">Applied!</span>' +
                        '</div>' +
                    '</div>';
                
                // Insert right below the price block
                $priceBlock.after(pricingExtrasHtml);
            }

            // Start countdown timer logic
            var storageKey = 'vco_timer_course_' + courseId;
            var now = Date.now();
            var targetTime = localStorage.getItem(storageKey);

            if (targetTime) {
                targetTime = parseInt(targetTime, 10);
                if (isNaN(targetTime) || targetTime <= now) {
                    targetTime = now + (24 * 60 * 60 * 1000);
                    localStorage.setItem(storageKey, targetTime);
                }
            } else {
                targetTime = now + (24 * 60 * 60 * 1000);
                localStorage.setItem(storageKey, targetTime);
            }

            // Set eligibility cookie with remaining time (max-age in seconds)
            var cookieRemaining = Math.max(0, Math.floor((targetTime - Date.now()) / 1000));
            if (cookieRemaining > 0) {
                document.cookie = "vco_eligible_welcome_offer=1; path=/; max-age=" + cookieRemaining + "; SameSite=Lax";
            }

            var $timerElement = $('#vco-countdown-' + courseId);
            if ($timerElement.length) {
                var countdownInterval = setInterval(updateCountdown, 1000);
                updateCountdown(); // run immediately
            }
        }

        function updateCountdown() {
            var currentNow = Date.now();
            var diff = targetTime - currentNow;

            if (diff <= 0) {
                clearInterval(countdownInterval);
                $timerElement.text('00:00:00');
                
                var $label = $timerElement.siblings('.vco-countdown-label');
                if ($label.length) {
                    $label.html('<span class="tutor-icon-clock tutor-mr-8"></span>Offer expiring soon... refresh for latest offer');
                }
                return;
            }

            var totalSeconds = Math.floor(diff / 1000);
            var hours = Math.floor(totalSeconds / 3600);
            var minutes = Math.floor((totalSeconds % 3600) / 60);
            var seconds = totalSeconds % 60;

            var hStr = hours < 10 ? '0' + hours : hours;
            var mStr = minutes < 10 ? '0' + minutes : minutes;
            var sStr = seconds < 10 ? '0' + seconds : seconds;

            $timerElement.text(hStr + ':' + mStr + ':' + sStr);
        }
    });
})(jQuery);
