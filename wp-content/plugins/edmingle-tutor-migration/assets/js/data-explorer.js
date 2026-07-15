jQuery(document).ready(function($) {

	// Handle fetch button clicks
	$('.etm-btn-fetch').on('click', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var action = $btn.data('action');
		var $card = $btn.closest('.etm-card');
		var $spinner = $card.find('.spinner');
		var $notice = $card.find('.etm-card-notice');
		
		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$notice.hide().removeClass('notice-success notice-error');

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: action,
				nonce: etm_admin.nonce
			}
		}).done(function(response) {
			if (response.success) {
				var data = response.data;
				$notice.addClass('notice-success').html('Fetched ' + data.total_imported + ' records in ' + data.execution_time + 'ms.').show();
				
				// Update stats
				var currentTotal = parseInt($card.find('.etm-stat-total').text()) || 0;
				$card.find('.etm-stat-total').text(currentTotal + data.total_imported);
				
				var now = new Date();
				$card.find('.etm-stat-sync').text(now.toISOString().replace('T', ' ').substring(0, 19));
				
				// Enable View JSON button
				$card.find('.etm-btn-view').prop('disabled', false);
			} else {
				$notice.addClass('notice-error').html('Error: ' + (response.data || 'Unknown error')).show();
			}
		}).fail(function() {
			$notice.addClass('notice-error').html('Error: Server request failed.').show();
		}).always(function() {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
		});
	});

	// Handle View JSON button
	$('.etm-btn-view').on('click', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var type = $btn.data('type');
		var $modal = $('#etm-json-modal');
		var $viewer = $('#etm-json-viewer');
		
		$btn.prop('disabled', true);
		$viewer.text('Loading...');
		$('#etm-modal-type-title').text('(' + type + ')');
		$modal.show();

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'edmingle_view_json',
				type: type,
				nonce: etm_admin.nonce
			}
		}).done(function(response) {
			if (response.success) {
				$viewer.text(JSON.stringify(response.data, null, 4));
			} else {
				$viewer.text('Error: ' + (response.data || 'Failed to load JSON'));
			}
		}).fail(function() {
			$viewer.text('Error: Request failed.');
		}).always(function() {
			$btn.prop('disabled', false);
		});
	});

	// Close modal
	$('.etm-close-modal').on('click', function() {
		$('#etm-json-modal').hide();
	});

	// Close modal when clicking outside
	$(window).on('click', function(e) {
		if ($(e.target).is('#etm-json-modal')) {
			$('#etm-json-modal').hide();
		}
	});

});
