jQuery(document).ready(function($) {

	// Handle migrate/resume button clicks
	$('.etm-btn-migrate, .etm-btn-resume').on('click', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var action = $btn.data('action');
		var isResume = $btn.data('resume') || false;
		var $card = $btn.closest('.etm-card');
		var $spinner = $card.find('.spinner');
		var $notice = $card.find('.etm-card-notice');
		var $progress = $card.find('.etm-sync-progress');
		
		$card.find('.etm-btn-migrate, .etm-btn-resume').prop('disabled', true);
		$spinner.addClass('is-active');
		$notice.hide().removeClass('notice-success notice-error');
		$progress.show();

		// Start migration loop
		migrateBatch(action, isResume, $card, 0);
	});

	function migrateBatch(action, isResume, $card, retries) {
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
				$card.find('.etm-progress-text').text('Migrating... (' + state.records_migrated + ' / ' + state.total_records + ')');
				
				var imp = parseInt($card.find('.etm-stat-imported').text()) || 0;
				var skp = parseInt($card.find('.etm-stat-skipped').text()) || 0;
				var err = parseInt($card.find('.etm-stat-errors').text()) || 0;
				
				if (!isResume && state.records_migrated <= 50) { // Reset on fresh start
					imp = 0; skp = 0; err = 0;
				}
				
				$card.find('.etm-stat-imported').text(imp + stats.imported);
				$card.find('.etm-stat-skipped').text(skp + stats.skipped + stats.updated);
				$card.find('.etm-stat-errors').text(err + stats.errors);

				if (state.status === 'running') {
					// Continue to next batch
					setTimeout(function() {
						migrateBatch(action, true, $card, 0);
					}, 1000); // 1s delay
				} else if (state.status === 'completed') {
					// Finished
					$card.find('.etm-progress-text').text('Finished (' + state.records_migrated + ' records processed)');
					
					$card.find('.etm-btn-migrate').prop('disabled', false).show();
					$card.find('.etm-btn-resume').hide();
					$card.find('.spinner').removeClass('is-active');
					$card.find('.etm-card-notice').addClass('notice-success').html('Migration Complete!').show();
				}
			} else {
				handleMigrationError(action, $card, retries, response.data.message || response.data || 'Unknown error');
			}
		}).fail(function(jqXHR, textStatus, errorThrown) {
			handleMigrationError(action, $card, retries, 'Server request failed: ' + textStatus);
		});
	}

	function handleMigrationError(action, $card, retries, errorMsg) {
		if (retries < 3) {
			var $notice = $card.find('.etm-card-notice');
			$notice.addClass('notice-error').html('Error: ' + errorMsg + '. Retrying in 2 seconds... (Attempt ' + (retries + 1) + '/3)').show();
			setTimeout(function() {
				$notice.hide().removeClass('notice-error');
				migrateBatch(action, true, $card, retries + 1);
			}, 2000);
		} else {
			// Stop and allow resume
			$card.find('.etm-card-notice').addClass('notice-error').html('Error: ' + errorMsg + '. Migration paused.').show();
			$card.find('.spinner').removeClass('is-active');
			$card.find('.etm-btn-migrate').hide();
			$card.find('.etm-btn-resume').prop('disabled', false).show();
		}
	}

});
