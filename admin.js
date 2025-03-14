/* stylelint-disable */
jQuery(document).ready(function ($) {


    // ----------- All Pages ----------- //

    

    // ----------- Manage Team Members Page ----------- //
    $(".tmoc-move-member").click(function () {
        let postId = $(this).data("id");
        let direction = $(this).data("direction");

        $.ajax({
            type: "POST",
            url: tmoc_ajax.ajax_url,
            data: {
                action: "tmoc_reorder_members",
                post_id: postId,
                direction: direction,
            },
            success: function (response) {
                if (response.success) {
                    console.log("✅ Order updated!");
                    location.reload(); // Reload to show new order
                } else {
                    console.log("❌ Error updating order:", response);
                }
            },
            error: function () {
                console.log("❌ AJAX request failed.");
            },
        });
    });

    $(".tmoc-sort").click(function () {
        let sortOrder = $(this).data("sort");

        $.ajax({
            type: "POST",
            url: tmoc_ajax.ajax_url,
            data: {
                action: "tmoc_sort_members",
                sort: sortOrder
            },
            success: function (response) {
                if (response.success) {
                    $("#tmoc-team-members-list").html(response.data.html);
                    console.log("✅ Sorted order:", sortOrder);
                } else {
                    console.error("❌ Error sorting members:", response);
                }
            },
            error: function () {
                console.error("❌ AJAX request failed.");
            },
        });
    });

    // ------------------------------------------------- //

    // Update image preview on upload
    function updatePreview() {
        let imageUrl = $('#tmoc_image').val();
        let fit = $('#tmoc_image_fit').val();
        let posX = $('#tmoc_image_x').val() || '0';
        let posY = $('#tmoc_image_y').val() || '0';
        let scale = $('#tmoc_image_scale').val() || '1';

        $('#tmoc_image_preview').css({
            'background-image': 'url(' + imageUrl + ')',
            'background-size': fit,
            'background-position': posX + 'px ' + posY + 'px'
        });

        $('#tmoc_preview_img').css({
            'object-fit': fit,
            'transform': 'scale(' + scale + ')',
            'object-position': posX + 'px ' + posY + 'px'
        });
    }

    // Step button click event
    $('.tmoc-step-btn').click(function () {
        let target = $(this).data('target');
        let step = parseFloat($(this).data('step'));
        let inputField = $('#' + target);
        let currentValue = parseFloat(inputField.val()) || 0;
        inputField.val(currentValue + step).trigger('change');
    });

    // Update preview when input fields change
    $('#tmoc_image, #tmoc_image_fit, #tmoc_image_x, #tmoc_image_y, #tmoc_image_scale').on('input change', function () {
        updatePreview();
    });

    // Open media uploader when selecting an image
    $('.tmoc_upload_image_button').click(function (e) {
        e.preventDefault();
        let frame = wp.media({
            title: 'Select Profile Image',
            button: { text: 'Use this image' },
            multiple: false
        });

        frame.on('select', function () {
            let attachment = frame.state().get('selection').first().toJSON();
            $('#tmoc_image').val(attachment.url);
            updatePreview();
        });

        frame.open();
    });

    // Initialize preview on page load
    updatePreview();
});
/* stylelint-enable */