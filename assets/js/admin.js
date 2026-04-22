(function($) {
    'use strict';

    $(function() {
        // View Details in Modal (Thickbox + AJAX)
        $(document).on('click', '.mgc-view-details', function(e) {
            e.preventDefault();

            var $button = $(this);
            var logId   = $button.data('id');
            var type    = $button.data('type') || 'audit';
            var title   = $button.attr('title') || mgc_admin.loading;

            // Show loading state if possible
            $button.addClass('updating-message');

            $.ajax({
                url: mgc_admin.ajaxurl,
                type: 'GET',
                data: {
                    action: 'mgc_view_policy',
                    log_id: logId,
                    type: type,
                    nonce: mgc_admin.nonce
                },
                success: function(response) {
                    $button.removeClass('updating-message');
                    
                    if (response.success) {
                        // Create a temporary hidden container for the content
                        var $temp = $('<div id="mgc-modal-content" style="display:none;"></div>').html(response.data);
                        $('body').append($temp);

                        // Show Thickbox
                        tb_show(title, '#TB_inline?width=600&height=450&inlineId=mgc-modal-content');

                        // Clean up when Thickbox closes
                        $(document).on('tb_unload', '#TB_window', function() {
                            $('#mgc-modal-content').remove();
                        });
                    } else {
                        alert(response.data || mgc_admin.error);
                    }
                },
                error: function() {
                    $button.removeClass('updating-message');
                    alert(mgc_admin.error);
                }
            });
        });
    });

})(jQuery);
