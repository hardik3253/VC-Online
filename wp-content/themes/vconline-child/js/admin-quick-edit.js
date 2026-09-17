jQuery(document).ready(function($) {
    if (typeof inlineEditPost === 'undefined') {
        return;
    }

    // Hook into inline edit post function
    const $wp_inline_edit = inlineEditPost.edit;
    inlineEditPost.edit = function(id) {
        $wp_inline_edit.apply(this, arguments);

        // Get post ID
        const post_id = inlineEditPost.getId(id);
        if (post_id) {
            const $row = $('#post-' + post_id);
            const $badge_preview = $row.find('.column-course_badge .badge-preview');
            const enable = $badge_preview.data('enable') === 'yes';
            const text = $badge_preview.data('text') || '';
            const textcolor = $badge_preview.data('textcolor') || '#ffffff';
            const bgcolor = $badge_preview.data('bgcolor') || '#E03E3E';

            const $edit_row = $('#edit-' + post_id);
            $edit_row.find('.quick-edit-enable-badge').prop('checked', enable);
            $edit_row.find('.quick-edit-badge-text').val(text);
            $edit_row.find('.quick-edit-badge-text-color').val(textcolor);
            $edit_row.find('.quick-edit-badge-bg-color').val(bgcolor);
        }
    };
});
