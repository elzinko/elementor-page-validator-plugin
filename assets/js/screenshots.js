
jQuery(document).ready(function($) {
    $("input[name=\'valider[]\']").change(function() {
        var parentChecked = $(this).prop("checked");
        var parentId = $(this).val();
        $("tr[data-id^=\'" + parentId + "-\']").find("input[type=\'checkbox\']").prop("checked", parentChecked);
    });
});
jQuery(document).ready(function($) {
    $("img[id^=\'screenshot-\']").each(function() {
        var screenshotUrl = $(this).data("url");
        var imgElement = $(this);
        $.ajax({
            url: screenshotUrl,
            type: "GET",
            success: function(data) {
                imgElement.attr("src", data.url);
            },
            error: function(error) {
                console.log("Erreur lors de la récupération de l\'image : ", error);
            }
        });
    });
});