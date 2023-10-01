
jQuery(document).ready(function($) {
    $("input[name=\'valider[]\']").change(function() {
        var parentChecked = $(this).prop("checked");
        var parentId = $(this).val();
        $("tr[data-id^=\'" + parentId + "-\']").find("input[type=\'checkbox\']").prop("checked", parentChecked);
    });
});

document.addEventListener("DOMContentLoaded", function() {
    const images = document.querySelectorAll("img[data-url]");
    images.forEach(img => {
        const dataUrl = img.getAttribute("data-url");
        fetch('/wp-json/epvp/v1/request-image/', {
            method: 'POST',
            body: JSON.stringify({ data_url: dataUrl }),
        })
        .then(response => response.json())
        .then(data => {
            const taskId = data.task_id;
            checkImageStatus(taskId, img);
        });
    });
});

function checkImageStatus(taskId, imgElement) {
    fetch(`/wp-json/epvp/v1/check-image/${taskId}`)
    .then(response => response.json())
    .then(data => {
        if (data.status === 'completed') {
            imgElement.src = data.image_url;
        } else {
            setTimeout(() => checkImageStatus(taskId, imgElement), 5000);
        }
    });
}

