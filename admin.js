jQuery(document).ready(function ($) {
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
});
