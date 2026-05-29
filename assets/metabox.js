jQuery(document).ready(function($) {

    // Helper to get content from Block Editor or Classic Editor.
    function getEditorContent() {
        // Check if block editor is active
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            return wp.data.select('core/editor').getEditedPostContent();
        }
        // Fallback to Classic Editor (TinyMCE)
        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            return tinymce.get('content').getContent();
        }
        // Fallback to plain textarea
        return $('#content').val() || '';
    }

    // Helper to set/overwrite content in Block Editor, WPBakery, or Classic Editor.
    function setEditorContent(content) {
        var applied = false;

        // 1. WPBakery Page Builder support
        if (typeof window.vc !== 'undefined' && window.vc.storage) {
            window.vc.storage.setContent(content);
            if (window.vc.shortcodes) {
                window.vc.shortcodes.fetch({reset: true});
            }
            applied = true;
        }

        // 2. Gutenberg Block Editor
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor') && wp.blocks) {
            // Re-parse the Blocks from translated HTML
            var blocks = wp.blocks.parse(content);
            wp.data.dispatch('core/editor').resetBlocks(blocks);
            applied = true;
        }

        // 3. Classic Editor (TinyMCE)
        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            tinymce.get('content').setContent(content);
            applied = true;
        }

        // 4. Fallback to plain textarea
        if ($('#content').length) {
            $('#content').val(content).trigger('change');
            applied = true;
        }

        return applied;
    }

    // Translate Button click event
    $(document).on('click', '#sat_translate_btn', function(e) {
        e.preventDefault();

        var isImportMode = $('#sat_target_language').attr('type') === 'hidden';
        var content = getEditorContent();
        var targetLanguage = $('#sat_target_language').val();

        if (!isImportMode && !content.trim()) {
            alert(sat_ajax_obj.alerts.empty_content);
            return;
        }

        // Hide result, show spinner
        $('#sat_result_wrapper').hide();
        $('#sat_loader_wrapper').show();
        $('#sat_translate_btn').prop('disabled', true);

        // Make AJAX request
        $.ajax({
            url: sat_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'sat_translate_content',
                nonce: sat_ajax_obj.nonce,
                post_id: sat_ajax_obj.post_id,
                content: content,
                target_language: targetLanguage
            },
            success: function(response) {
                if (response.success) {
                    $('#sat_translated_content').val(response.data.translation);
                    $('#sat_result_wrapper').fadeIn();
                } else {
                    alert(response.data.message || 'Translation failed.');
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred during translation: ' + error);
            },
            complete: function() {
                $('#sat_loader_wrapper').hide();
                $('#sat_translate_btn').prop('disabled', false);
            }
        });
    });

    // Copy Button click event
    $(document).on('click', '#sat_copy_btn', function(e) {
        e.preventDefault();
        var text = $('#sat_translated_content').val();
        if (text) {
            navigator.clipboard.writeText(text).then(function() {
                alert(sat_ajax_obj.alerts.success_copy);
            }, function(err) {
                alert('Could not copy text: ', err);
            });
        }
    });

    // Overwrite/Apply Button click event
    $(document).on('click', '#sat_overwrite_btn', function(e) {
        e.preventDefault();
        var text = $('#sat_translated_content').val();
        if (text) {
            if (confirm('Are you sure you want to overwrite the current editor content with the translated version?')) {
                if (setEditorContent(text)) {
                    alert(sat_ajax_obj.alerts.applied);
                } else {
                    alert('Could not apply content to the editor.');
                }
            }
        }
    });

});
