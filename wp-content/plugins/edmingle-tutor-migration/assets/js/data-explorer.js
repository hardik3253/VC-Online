jQuery(document).ready(function($) {

	// Handle fetch/resume button clicks
	$('.etm-btn-fetch, .etm-btn-resume').on('click', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var action = $btn.data('action');
		var isResume = $btn.data('resume') || false;
		var $card = $btn.closest('.etm-card');
		var $spinner = $card.find('.spinner');
		var $notice = $card.find('.etm-card-notice');
		var $progress = $card.find('.etm-sync-progress');
		
		$card.find('.etm-btn-fetch, .etm-btn-resume').prop('disabled', true);
		$spinner.addClass('is-active');
		$notice.hide().removeClass('notice-success notice-error');
		$progress.show();

		// Start syncing loop
		syncPage(action, isResume, $card, 0);
	});

	function syncPage(action, isResume, $card, retries) {
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: action,
				resume: isResume,
				nonce: etm_admin.nonce
			}
		}).done(function(response) {
			if (response.success) {
				var data = response.data;
				var state = data.state;
				var stats = data.stats;
				
				// Update progress UI
				$card.find('.etm-progress-text').text('Page ' + (state.current_page - 1) + ' / ' + (state.total_pages > 0 ? state.total_pages : '?') + ' (' + state.records_fetched + ' / ' + (state.total_records > 0 ? state.total_records : '?') + ')');
				
				var imp = parseInt($card.find('.etm-stat-imported').text()) || 0;
				var upd = parseInt($card.find('.etm-stat-updated').text()) || 0;
				var skp = parseInt($card.find('.etm-stat-skipped').text()) || 0;
				
				if (!isResume || state.current_page === 2) { // Reset on fresh start (page 1 done, now 2)
					if (!isResume) {
						imp = 0; upd = 0; skp = 0;
					}
				}
				
				$card.find('.etm-stat-imported').text(imp + stats.imported);
				$card.find('.etm-stat-updated').text(upd + stats.updated);
				$card.find('.etm-stat-skipped').text(skp + stats.skipped);
				$card.find('.etm-stat-time').text(data.execution_time + 'ms');

				if (state.status === 'running') {
					// Continue to next page after a delay to prevent API rate limiting
					setTimeout(function() {
						syncPage(action, true, $card, 0);
					}, 1500); // 1.5 seconds delay between requests
				} else if (state.status === 'completed') {
					// Finished
					$card.find('.etm-progress-text').text('Finished (' + state.records_fetched + ' records)');
					var now = new Date();
					$card.find('.etm-stat-sync').text(now.toISOString().replace('T', ' ').substring(0, 19));
					$card.find('.etm-stat-total').text(state.records_fetched);
					
					$card.find('.etm-btn-fetch').prop('disabled', false).show();
					$card.find('.etm-btn-resume').hide();
					$card.find('.etm-btn-view').prop('disabled', false);
					$card.find('.spinner').removeClass('is-active');
					$card.find('.etm-card-notice').addClass('notice-success').html('Sync Complete!').show();
				}
			} else {
				handleSyncError(action, $card, retries, response.data.message || response.data || 'Unknown error');
			}
		}).fail(function(jqXHR, textStatus, errorThrown) {
			handleSyncError(action, $card, retries, 'Server request failed: ' + textStatus);
		});
	}

	function handleSyncError(action, $card, retries, errorMsg) {
		// If it's a rate limit error, don't even bother retrying
		var isRateLimited = errorMsg.toLowerCase().indexOf('limit') !== -1 || errorMsg.toLowerCase().indexOf('blocked') !== -1;
		
		if (retries < 3 && !isRateLimited) {
			var $notice = $card.find('.etm-card-notice');
			$notice.addClass('notice-error').html('Error: ' + errorMsg + '. Retrying in 2 seconds... (Attempt ' + (retries + 1) + '/3)').show();
			setTimeout(function() {
				$notice.hide().removeClass('notice-error');
				syncPage(action, true, $card, retries + 1);
			}, 2000);
		} else {
			// Stop and allow resume
			var msg = isRateLimited ? 'Error: ' + errorMsg + '. Sync paused.' : 'Error: ' + errorMsg + '. Max retries reached.';
			$card.find('.etm-card-notice').addClass('notice-error').html(msg).show();
			$card.find('.spinner').removeClass('is-active');
			$card.find('.etm-btn-fetch').hide();
			$card.find('.etm-btn-resume').prop('disabled', false).show();
		}
	}

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
