/**
 * JS for Course Mapping
 */
jQuery(document).ready(function($) {

	var tutorOptions = [];
	var savedMapping = {};

	function getSelectHTML( edmingleId, selectedTutorId ) {
		var html = '<select name="mappings[' + edmingleId + ']" class="etm-course-select">';
		html += '<option value="">-- Do Not Map --</option>';
		
		$.each(tutorOptions, function(index, course) {
			var selected = (course.id == selectedTutorId) ? ' selected' : '';
			html += '<option value="' + course.id + '"' + selected + '>' + course.title + ' (ID: ' + course.id + ')</option>';
		});
		
		html += '</select>';
		return html;
	}

	$('#etm-fetch-courses').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var $spinner = $('#etm-mapping-spinner');
		var $notice = $('#etm-mapping-notice');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$notice.hide().removeClass('notice-success notice-error');

		$.ajax({
			url: etm_ajax.ajax_url,
			method: 'POST',
			data: {
				action: 'etm_fetch_courses',
				nonce: etm_ajax.nonce
			},
			success: function(response) {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');

				if (response.success) {
					var data = response.data;
					tutorOptions = data.tutor_options;
					savedMapping = data.saved_mapping || {};

					var $matchedBody = $('#etm-matched-table tbody');
					var $missingBody = $('#etm-missing-table tbody');
					
					$matchedBody.empty();
					$missingBody.empty();

					// Populate Matched
					if (data.matched.length === 0) {
						$matchedBody.append('<tr><td colspan="2">No auto-matched courses found.</td></tr>');
					} else {
						$.each(data.matched, function(i, match) {
							// Determine selected ID: user saved override OR auto-matched
							var selectedId = savedMapping[match.edmingle.id] !== undefined ? savedMapping[match.edmingle.id] : match.tutor.id;
							
							var tr = '<tr>';
							tr += '<td><strong>' + match.edmingle.title + '</strong><br><small>ID: ' + match.edmingle.id + '</small></td>';
							tr += '<td>' + getSelectHTML(match.edmingle.id, selectedId) + '</td>';
							tr += '</tr>';
							$matchedBody.append(tr);
						});
					}

					// Populate Missing
					if (data.missing.length === 0) {
						$missingBody.append('<tr><td colspan="2">No missing courses found.</td></tr>');
					} else {
						$.each(data.missing, function(i, missing) {
							// Check if there's a saved manual mapping for this missing course
							var selectedId = savedMapping[missing.id] !== undefined ? savedMapping[missing.id] : '';
							
							var tr = '<tr>';
							tr += '<td><strong>' + missing.title + '</strong><br><small>ID: ' + missing.id + '</small></td>';
							tr += '<td>' + getSelectHTML(missing.id, selectedId) + '</td>';
							tr += '</tr>';
							$missingBody.append(tr);
						});
					}

					$('#etm-mapping-container').show();

				} else {
					$notice.addClass('notice-error').show().find('p').text(response.data);
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
				$notice.addClass('notice-error').show().find('p').text('AJAX Error occurred.');
			}
		});
	});

	// Save Mapping Form Submit
	$('#etm-mapping-form').on('submit', function(e) {
		e.preventDefault();
		
		var $btn = $('#etm-save-mapping');
		var $notice = $('#etm-mapping-notice');
		
		$btn.prop('disabled', true).text('Saving...');
		$notice.hide().removeClass('notice-success notice-error');

		var formData = $(this).serialize() + '&action=etm_save_mapping&nonce=' + etm_ajax.nonce;

		$.ajax({
			url: etm_ajax.ajax_url,
			method: 'POST',
			data: formData,
			success: function(response) {
				$btn.prop('disabled', false).text('Save Mapping');
				
				if (response.success) {
					$notice.addClass('notice-success').show().find('p').text(response.data);
					$('html, body').animate({ scrollTop: 0 }, 'fast');
				} else {
					$notice.addClass('notice-error').show().find('p').text(response.data);
					$('html, body').animate({ scrollTop: 0 }, 'fast');
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Save Mapping');
				$notice.addClass('notice-error').show().find('p').text('AJAX Error occurred.');
				$('html, body').animate({ scrollTop: 0 }, 'fast');
			}
		});
	});

});
