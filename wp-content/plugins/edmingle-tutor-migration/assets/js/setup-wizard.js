jQuery(document).ready(function($) {

	$('#etm-btn-initialize').on('click', function(e) {
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
		}).done(function(response) {
			if (response.success) {
				successStep('#step-authenticate');
				successStep('#step-api-key');
				$('#ro-user-id').text(response.data.user_id || '');
				
				// Proceed to Step 2: Fetch Institution
				fetchInstitution();
			} else {
				failWizard('#step-authenticate', 'Authentication Failed: ' + (response.data || 'Unknown error'));
			}
		}).fail(function(jqXHR, textStatus, errorThrown) {
			failWizard('#step-authenticate', 'Request failed: ' + textStatus);
		});

		// Step 2: Institution
		function fetchInstitution() {
			runStep('#step-institution');
			$.ajax({
				url: etm_setup.ajax_url,
				method: 'POST',
				data: { action: 'etm_setup_fetch_institution', nonce: etm_setup.nonce }
			}).done(function(response) {
				if (response.success) {
					successStep('#step-institution');
					$('#ro-inst-id').text(response.data.institution_id || '');
					
					// Proceed to Step 3: Fetch Organization
					fetchOrganization();
				} else {
					failWizard('#step-institution', 'Institution Failed: ' + (response.data || 'Unknown error'));
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
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
			}).done(function(response) {
				if (response.success) {
					successStep('#step-organization');
					$('#ro-org-id').text(response.data.organization_id || '');
					
					// Proceed to Step 4: Verify APIs
					verifyApis();
				} else {
					failWizard('#step-organization', 'Organization Failed: ' + (response.data || 'Unknown error'));
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
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
			}).done(function(response) {
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
			}).fail(function(jqXHR, textStatus, errorThrown) {
				failWizard('#step-students', 'Request failed: ' + textStatus);
			});
		}

	});

	// Retry button
	$('.etm-btn-retry').on('click', function() {
		$('#etm-btn-initialize').trigger('click');
	});

	// Toggle password visibility
	$('#etm-toggle-password').on('click', function() {
		var $pwd = $('#etm_admin_password');
		if ($pwd.attr('type') === 'password') {
			$pwd.attr('type', 'text');
			$(this).removeClass('dashicons-visibility').addClass('dashicons-hidden');
		} else {
			$pwd.attr('type', 'password');
			$(this).removeClass('dashicons-hidden').addClass('dashicons-visibility');
		}
	});
});
