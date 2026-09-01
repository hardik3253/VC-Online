jQuery(document).ready(function ($) {

	$('#etm-btn-initialize').on('click', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var $form = $('#etm-setup-form');
		var $spinner = $btn.next('.spinner');
		var $errorNotice = $('#etm-setup-error');
		var $errorMsg = $errorNotice.find('.error-message');

		// Reset UI
		$errorNotice.hide();
		$('.etm-step').removeClass('success failed running').addClass('pending');
		$('#etm-read-only-panel').hide();

		// Basic validation
		var baseUrl = $('#etm_api_base_url').val().trim();
		var email = $('#etm_admin_email').val().trim();
		var password = $('#etm_admin_password').val().trim();

		if (!baseUrl || !email || !password) {
			alert('Please fill in all configuration fields.');
			return;
		}

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');

		function failWizard(stepId, error) {
			$(stepId).removeClass('running pending').addClass('failed');
			$errorMsg.text(error);
			$errorNotice.show();
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
		}

		function successStep(stepId) {
			$(stepId).removeClass('running pending').addClass('success');
		}

		function runStep(stepId) {
			$(stepId).removeClass('pending').addClass('running');
		}

		// Step 1: Authenticate
		runStep('#step-authenticate');
		runStep('#step-api-key'); // Done in same request

		$.ajax({
			url: etm_setup.ajax_url,
			method: 'POST',
			data: {
				action: 'etm_setup_authenticate',
				nonce: etm_setup.nonce,
				base_url: baseUrl,
				email: email,
				password: password
			}
		}).done(function (response) {
			if (response.success) {
				successStep('#step-authenticate');
				successStep('#step-api-key');
				$('#ro-user-id').text(response.data.user_id || '');

				// Proceed to Step 2: Fetch Institution
				fetchInstitution();
			} else {
				failWizard('#step-authenticate', 'Authentication Failed: ' + (response.data || 'Unknown error'));
			}
		}).fail(function (jqXHR, textStatus, errorThrown) {
			failWizard('#step-authenticate', 'Request failed: ' + textStatus);
		});

		// Step 2: Institution
		function fetchInstitution() {
			runStep('#step-institution');
			$.ajax({
				url: etm_setup.ajax_url,
				method: 'POST',
				data: { action: 'etm_setup_fetch_institution', nonce: etm_setup.nonce }
			}).done(function (response) {
				if (response.success) {
					successStep('#step-institution');
					$('#ro-inst-id').text(response.data.institution_id || '');

					// Proceed to Step 3: Fetch Organization
					fetchOrganization();
				} else {
					failWizard('#step-institution', 'Institution Failed: ' + (response.data || 'Unknown error'));
				}
			}).fail(function (jqXHR, textStatus, errorThrown) {
				failWizard('#step-institution', 'Request failed: ' + textStatus);
			});
		}

		// Step 3: Organization
		function fetchOrganization() {
			runStep('#step-organization');
			$.ajax({
				url: etm_setup.ajax_url,
				method: 'POST',
				data: { action: 'etm_setup_fetch_organization', nonce: etm_setup.nonce }
			}).done(function (response) {
				if (response.success) {
					successStep('#step-organization');
					$('#ro-org-id').text(response.data.organization_id || '');

					// Proceed to Step 4: Verify APIs
					verifyApis();
				} else {
					failWizard('#step-organization', 'Organization Failed: ' + (response.data || 'Unknown error'));
				}
			}).fail(function (jqXHR, textStatus, errorThrown) {
				failWizard('#step-organization', 'Request failed: ' + textStatus);
			});
		}

		// Step 4: Verify Access
		function verifyApis() {
			runStep('#step-students');
			runStep('#step-enrollments');
			runStep('#step-curriculum');
			runStep('#step-progress');

			$.ajax({
				url: etm_setup.ajax_url,
				method: 'POST',
				data: { action: 'etm_setup_verify_access', nonce: etm_setup.nonce }
			}).done(function (response) {
				// We expect results to be an object with true/false for each api
				var res = response.data || {};
				if (response.success) {
					successStep('#step-students');
					successStep('#step-enrollments');
					successStep('#step-curriculum');
					successStep('#step-progress');

					// Show success UI
					$('#etm-read-only-panel').fadeIn();
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
				} else {
					// Some failed
					if (res.results) {
						res.results.students ? successStep('#step-students') : failWizard('#step-students', res.message);
						res.results.enrollments ? successStep('#step-enrollments') : failWizard('#step-enrollments', res.message);
						res.results.curriculum ? successStep('#step-curriculum') : failWizard('#step-curriculum', res.message);
						res.results.progress ? successStep('#step-progress') : failWizard('#step-progress', res.message);
					}
					failWizard('#step-students', 'API Verification Failed: ' + (res.message || response.data || 'Unknown error'));
				}
			}).fail(function (jqXHR, textStatus, errorThrown) {
				failWizard('#step-students', 'Request failed: ' + textStatus);
			});
		}

	});

	// Retry button
	$('.etm-btn-retry').on('click', function () {
		$('#etm-btn-initialize').trigger('click');
	});

	// Toggle password visibility
	$('#etm-toggle-password').on('click', function () {
		var $pwd = $('#etm_admin_password');
		if ($pwd.attr('type') === 'password') {
			$pwd.attr('type', 'text');
			$(this).removeClass('dashicons-visibility').addClass('dashicons-hidden');
		} else {
			$pwd.attr('type', 'password');
			$(this).removeClass('dashicons-hidden').addClass('dashicons-visibility');
		}
	});

	// Google Sheets Batch Sync logic
	if ($('#etm-unsynced-count').length) {
		// Fetch count on load
		fetchUnsyncedCount();

		var totalSynced = 0;
		var totalFailed = 0;

		$('#etm-btn-sync-existing').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $spinner = $('#etm-bulk-sync-spinner');
			var $progressDiv = $('#etm-gsheet-bulk-progress');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$progressDiv.show();

			totalSynced = 0;
			totalFailed = 0;

			syncBatch();

			function syncBatch() {
				$.ajax({
					url: etm_setup.ajax_url,
					method: 'POST',
					data: {
						action: 'etm_sync_existing_users_batch',
						nonce: etm_setup.nonce,
						limit: 161
					}
				}).done(function (response) {
					if (response.success) {
						var data = response.data;
						totalSynced += data.synced;
						totalFailed += data.failed;

						$('#etm-bulk-synced-count').text(totalSynced);
						$('#etm-bulk-failed-count').text(totalFailed);
						$('#etm-bulk-remaining-count').text(data.remaining);
						$('#etm-unsynced-count').text(data.remaining);

						if (data.remaining > 0 && data.synced > 0) {
							// Continue loop
							syncBatch();
						} else {
							// Done
							$spinner.removeClass('is-active');
							$btn.prop('disabled', false);
							alert('Synchronization completed! Synced: ' + totalSynced + ', Failed: ' + totalFailed);
							fetchUnsyncedCount();
						}
					} else {
						alert('Error syncing batch: ' + (response.data || 'Unknown error'));
						$spinner.removeClass('is-active');
						$btn.prop('disabled', false);
					}
				}).fail(function () {
					alert('Request failed. Please try again.');
					$spinner.removeClass('is-active');
					$btn.prop('disabled', false);
				});
			}
		});

		$('#etm-btn-reset-sync').on('click', function (e) {
			e.preventDefault();
			if (!confirm('This will reset all user sync flags and allow you to re-sync all existing users to Google Sheets. The sheet script will update existing records and avoid duplicates. Continue?')) {
				return;
			}
			var $btn = $(this);
			$btn.prop('disabled', true);
			$.ajax({
				url: etm_setup.ajax_url,
				method: 'POST',
				data: {
					action: 'etm_reset_gsheet_sync',
					nonce: etm_setup.nonce
				}
			}).done(function (response) {
				$btn.prop('disabled', false);
				if (response.success) {
					fetchUnsyncedCount();
					alert(response.data.message);
				} else {
					alert('Error resetting sync: ' + (response.data || 'Unknown error'));
				}
			}).fail(function () {
				$btn.prop('disabled', false);
				alert('Request failed. Please try again.');
			});
		});

		function fetchUnsyncedCount() {
			$.ajax({
				url: etm_setup.ajax_url,
				method: 'POST',
				data: {
					action: 'etm_get_unsynced_users_count',
					nonce: etm_setup.nonce
				}
			}).done(function (response) {
				if (response.success) {
					$('#etm-unsynced-count').text(response.data.count);
					if (response.data.count > 0 && $('#etm_gsheet_webhook_url').length && $('#etm_gsheet_webhook_url').val().trim() !== '') {
						$('#etm-btn-sync-existing').prop('disabled', false);
					} else {
						$('#etm-btn-sync-existing').prop('disabled', true);
					}
				}
			});
		}
	}
});
