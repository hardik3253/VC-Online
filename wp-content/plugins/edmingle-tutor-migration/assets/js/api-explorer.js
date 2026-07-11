/**
 * API Explorer JavaScript (Phase 0)
 */
jQuery(document).ready(function($) {

	// Tabs Logic
	$('.etm-tab').on('click', function(e) {
		e.preventDefault();
		$('.etm-tab').removeClass('active');
		$(this).addClass('active');
		
		$('.etm-tab-content').removeClass('active');
		$('#' + $(this).data('target')).addClass('active');
	});

	$('.etm-res-tab').on('click', function(e) {
		e.preventDefault();
		$('.etm-res-tab').removeClass('active');
		$(this).addClass('active');
		
		$('.etm-res-tab-content').removeClass('active');
		$('#' + $(this).data('target')).addClass('active');
	});

	// API Form Submission
	$('#etm-api-form').on('submit', function(e) {
		e.preventDefault();
		
		var $btn = $('#etm-api-submit');
		var $spinner = $('#etm-api-spinner');
		
		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$('#etm-response-viewer').hide();
		
		var formData = {
			action: 'etm_execute_api',
			nonce: etm_ajax.nonce,
			api_method: $('#etm-api-method').val(),
			endpoint: $('#etm-api-endpoint').val(),
			headers: $('#etm-api-headers').val(),
			body: $('#etm-api-body').val(),
			query: $('#etm-api-query').val()
		};

		$.ajax({
			url: etm_ajax.ajax_url,
			method: 'POST',
			data: formData,
			success: function(response) {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
				
				var data = response.data;
				if (!response.success && typeof data === 'string') {
					alert('Error: ' + data);
					return;
				}

				// Populate Response Viewer
				$('#etm-response-viewer').show();
				
				var statusClass = data.status_code >= 400 ? 'status-error' : 'status-success';
				$('#etm-res-status').text(data.status_code).removeClass('status-success status-error').addClass(statusClass);
				$('#etm-res-time').text(data.execution_time);
				$('#etm-res-size').text(data.response_size);

				// Format JSON nicely
				var prettyJson = (typeof data.data === 'object') ? JSON.stringify(data.data, null, 2) : data.raw;
				$('#etm-pretty-code').text(prettyJson);
				$('#etm-raw-code').val(data.raw);
				$('#etm-headers-code').text(JSON.stringify(data.headers, null, 2));

				// Reload page to update history table (simple approach)
				setTimeout(function() {
					location.reload();
				}, 1500);
			},
			error: function() {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
				alert('AJAX Error occurred');
			}
		});
	});

	// Run Again Logic
	$('.etm-run-again').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		
		$('#etm-api-method').val($btn.data('method'));
		$('#etm-api-endpoint').val($btn.data('endpoint'));
		
		var headers = $btn.data('headers');
		if(headers && $.isEmptyObject(headers) === false) {
			$('#etm-api-headers').val(JSON.stringify(headers, null, 2));
		} else {
			$('#etm-api-headers').val('');
		}

		var body = $btn.data('body');
		if(body && $.isEmptyObject(body) === false) {
			$('#etm-api-body').val(JSON.stringify(body, null, 2));
		} else {
			$('#etm-api-body').val('');
		}

		var query = $btn.data('query');
		if(query && $.isEmptyObject(query) === false) {
			$('#etm-api-query').val(JSON.stringify(query, null, 2));
		} else {
			$('#etm-api-query').val('');
		}
		
		// Scroll to top and submit
		$('html, body').animate({ scrollTop: 0 }, 'fast');
		$('#etm-api-submit').trigger('click');
	});

	// Delete History Item
	$('.etm-delete-history').on('click', function(e) {
		e.preventDefault();
		if (!confirm('Delete this request from history?')) return;
		
		var index = $(this).closest('tr').data('index');
		
		$.post(etm_ajax.ajax_url, {
			action: 'etm_delete_history',
			nonce: etm_ajax.nonce,
			index: index
		}, function() {
			location.reload();
		});
	});

	// Clear All History
	$('#etm-clear-history').on('click', function(e) {
		e.preventDefault();
		if (!confirm('Clear ALL request history?')) return;
		
		$.post(etm_ajax.ajax_url, {
			action: 'etm_delete_history',
			nonce: etm_ajax.nonce,
			index: -1
		}, function() {
			location.reload();
		});
	});

	// Export Response JSON
	$('#etm-export-response').on('click', function(e) {
		e.preventDefault();
		var content = $('#etm-raw-code').val();
		if (!content) return;
		
		var blob = new Blob([content], { type: "application/json" });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = 'api-response.json';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	});

	// Dashboard: Test Connection
	$('#etm-test-connection-btn').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var $spinner = $('#etm-test-spinner');
		var $results = $('#etm-test-results');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$results.hide();

		$.post(etm_ajax.ajax_url, {
			action: 'etm_test_connection',
			nonce: etm_ajax.nonce
		}, function(response) {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
			$results.show();

			var data = response.data;

			if (response.success) {
				$('#etm-test-status-heading').html('<span style="color:#46b450;">✔ Connected</span>');
				$('#etm-test-http').text('200 OK');
				$('#etm-test-time').text(data.time + ' ms');
				$('#etm-test-method').text(data.method);
			} else {
				$('#etm-test-status-heading').html('<span style="color:#d63638;">✖ Authentication Failed</span>');
				$('#etm-test-http').text('Error');
				$('#etm-test-time').text((data.time || 0) + ' ms');
				$('#etm-test-method').text(data.message || data);
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
			alert('AJAX request failed.');
		});
	});

});
