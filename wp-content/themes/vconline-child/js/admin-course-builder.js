jQuery(document).ready(function($) {
    if (typeof vcoBuilderObj === 'undefined') {
        return;
    }

    const courseId = vcoBuilderObj.course_id;
    const ajaxurl = vcoBuilderObj.ajaxurl;
    const nonce = vcoBuilderObj.nonce;

    // Polling interval to inject fields when the options/General section renders
    setInterval(function() {
        if ($('#vco-badge-builder-settings').length > 0) {
            return; // Already injected
        }

        // Target the Q&A field wrapper
        const $qnaWrapper = $('input[name="enable_qna"]').closest('[data-cy="form-field-wrapper"]');
        let $parent = null;
        let classes = 'css-1bhd30j'; // Fallback class if not found dynamically

        if ($qnaWrapper.length > 0) {
            $parent = $qnaWrapper.parent();
            classes = $qnaWrapper.attr('class') || classes;
        } else {
            // Fallback to Public Course wrapper
            const $publicCourseWrapper = $('input[name="is_public_course"]').closest('[data-cy="form-field-wrapper"]');
            if ($publicCourseWrapper.length > 0) {
                $parent = $publicCourseWrapper.parent();
                classes = $publicCourseWrapper.attr('class') || classes;
            }
        }

        if ($parent && $parent.length > 0) {
            // Construct the 3rd form-field-wrapper div dynamically using the exact classes
            const html = `
                <div data-cy="form-field-wrapper" class="${classes}" id="vco-badge-builder-settings" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; width: 100%;">
                    <div class="css-1itza76" style="display: flex; flex-direction: column; gap: 15px; width: 100%;">
                        <h4 style="font-size: 15px; font-weight: bold; margin: 0 0 5px 0; color: #1e293b; clear: both;">Course Badge Settings</h4>
                        
                        <div style="display: flex; align-items: center; width: 100%;">
                            <label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 500; font-size: 14px; color: #334155; margin: 0;">
                                <input type="checkbox" id="vco_builder_enable_badge" style="margin-right: 8px; width: 16px; height: 16px; cursor: pointer;" />
                                Enable Badge
                            </label>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 5px; width: 100%;">
                            <label style="display: block; font-weight: 500; font-size: 14px; color: #334155; margin: 0;">Badge Text</label>
                            <input type="text" id="vco_builder_badge_text" style="width: 100%; max-width: 300px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; outline: none; background: #fff;" placeholder="e.g. New, Free, Hot" />
                        </div>

                        <div style="display: flex; gap: 20px; width: 100%;">
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <label style="display: block; font-weight: 500; font-size: 14px; color: #334155; margin: 0;">Text Color</label>
                                <input type="color" id="vco_builder_badge_text_color" value="#ffffff" style="width: 50px; height: 30px; padding: 0; border: none; cursor: pointer; background: transparent;" />
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <label style="display: block; font-weight: 500; font-size: 14px; color: #334155; margin: 0;">Background Color</label>
                                <input type="color" id="vco_builder_badge_bg_color" value="#E03E3E" style="width: 50px; height: 30px; padding: 0; border: none; cursor: pointer; background: transparent;" />
                            </div>
                        </div>
                        
                        <div id="vco_builder_badge_status" style="font-size: 12px; color: #64748b; height: 15px; margin: 0;"></div>
                    </div>
                </div>
            `;

            // Append as the last child of the parent container holding the field wrappers
            $parent.append(html);

            // Fetch current badge settings
            $.post(ajaxurl, {
                action: 'vco_get_course_badge',
                course_id: courseId,
                nonce: nonce
            }, function(response) {
                if (response.success && response.data) {
                    $('#vco_builder_enable_badge').prop('checked', response.data.enable);
                    $('#vco_builder_badge_text').val(response.data.text);
                    $('#vco_builder_badge_text_color').val(response.data.textcolor);
                    $('#vco_builder_badge_bg_color').val(response.data.bgcolor);
                }
            });

            // Save on changes
            function saveBadgeSettings() {
                const enable = $('#vco_builder_enable_badge').is(':checked');
                const text = $('#vco_builder_badge_text').val();
                const textcolor = $('#vco_builder_badge_text_color').val();
                const bgcolor = $('#vco_builder_badge_bg_color').val();
                
                $('#vco_builder_badge_status').text('Saving badge...').css('color', '#64748b');

                $.post(ajaxurl, {
                    action: 'vco_save_course_badge',
                    course_id: courseId,
                    enable: enable,
                    text: text,
                    textcolor: textcolor,
                    bgcolor: bgcolor,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        $('#vco_builder_badge_status').text('Badge saved successfully.').css('color', '#10b981');
                        setTimeout(function() {
                            $('#vco_builder_badge_status').text('');
                        }, 2000);
                    } else {
                        $('#vco_builder_badge_status').text('Error saving badge.').css('color', '#ef4444');
                    }
                });
            }

            $('#vco_builder_enable_badge, #vco_builder_badge_text_color, #vco_builder_badge_bg_color').on('change', saveBadgeSettings);
            
            // Debounce typing to save badge text
            let typingTimer;
            $('#vco_builder_badge_text').on('keyup input', function() {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(saveBadgeSettings, 600);
            });
        }
    }, 1000);
});
