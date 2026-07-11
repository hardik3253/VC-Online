/**
 * JS for Migration Batch Processing
 */
jQuery(document).ready(function($) {

	var migrationQueue = ['students', 'enrollments', 'progress', 'completions', 'expiry'];
	var currentQueueIndex = 0;
	var currentPage = 1;
	var isRunning = false;

	var $runBtn = $('#etm-run-migration');
	var $resumeBtn = $('#etm-resume-migration');
	var $batchInput = $('#etm-batch-size');
	var $progressContainer = $('#etm-migration-progress-container');
	var $progressBar = $('#etm-migration-progress-bar');
	var $statusText = $('#etm-migration-status-text');
	var $errorNotice = $('#etm-migration-error');

	function updateProgressUI() {
		// Calculate a rough percentage based on queue steps
		var totalSteps = migrationQueue.length;
		var percentage = Math.round((currentQueueIndex / totalSteps) * 100);
		
		if (currentQueueIndex >= totalSteps) {
			percentage = 100;
			$statusText.text('Migration Complete!');
		} else {
			var currentStep = migrationQueue[currentQueueIndex];
			$statusText.text('Processing ' + currentStep.toUpperCase() + ' (Page ' + currentPage + ')...');
		}

		$progressBar.val(percentage);
	}

	function processNextBatch() {
		if (!isRunning) return;

		if (currentQueueIndex >= migrationQueue.length) {
			// Done
			isRunning = false;
			$runBtn.prop('disabled', false).text('Run Complete Migration');
			$resumeBtn.hide();
			updateProgressUI();
			return;
		}

		var currentStep = migrationQueue[currentQueueIndex];
		var batchSize = parseInt($batchInput.val(), 10) || 50;

		updateProgressUI();

		$.ajax({
			url: etm_ajax.ajax_url,
			method: 'POST',
			data: {
				action: 'etm_run_batch',
				nonce: etm_ajax.nonce,
				step: currentStep,
				page: currentPage,
				batch_size: batchSize
			},
			success: function(response) {
				if (response.success) {
					var results = response.data;
					var totalProcessed = (results.created || 0) + (results.updated || 0) + (results.completed || 0) + (results.skipped || 0);
					
					// If we processed items and didn't hit a 0 count, try the next page
					if (totalProcessed > 0 && totalProcessed >= batchSize) {
						currentPage++;
					} else {
						// This step is fully processed, move to next step
						currentQueueIndex++;
						currentPage = 1;
					}

					// Recurse
					processNextBatch();
				} else {
					handleError(response.data);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				handleError('AJAX Error: ' + textStatus + ' - ' + errorThrown);
			}
		});
	}

	function handleError(errorMessage) {
		isRunning = false;
		$runBtn.prop('disabled', false).text('Run Complete Migration');
		$resumeBtn.show();
		$errorNotice.show().find('p').text(errorMessage);
		$statusText.text('Migration Failed / Paused.');
	}

	$runBtn.on('click', function(e) {
		e.preventDefault();
		
		if (isRunning) return;

		// Reset states
		currentQueueIndex = 0;
		currentPage = 1;
		isRunning = true;
		
		$runBtn.prop('disabled', true).text('Running...');
		$resumeBtn.hide();
		$errorNotice.hide();
		$progressContainer.show();
		
		processNextBatch();
	});

	$resumeBtn.on('click', function(e) {
		e.preventDefault();
		
		if (isRunning) return;

		isRunning = true;
		$runBtn.prop('disabled', true).text('Running...');
		$resumeBtn.hide();
		$errorNotice.hide();
		
		processNextBatch();
	});

	// Test Connection Logic
	$('#etm-test-connection').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var $statusText = $('#etm-connection-status');
		var $spinner = $('#etm-connection-spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$statusText.text('Status: Testing...').css('color', '');

		$.ajax({
			url: etm_ajax.ajax_url,
			method: 'POST',
			data: {
				action: 'etm_test_connection',
				nonce: etm_ajax.nonce
			},
			success: function(response) {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');

				if (response.success) {
					$statusText.text('Status: Connected').css('color', '#46b450');
				} else {
					$statusText.text('Status: Failed - ' + response.data).css('color', '#d63638');
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
				$statusText.text('Status: AJAX Error').css('color', '#d63638');
			}
		});
	});

});
