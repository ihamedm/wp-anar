import Toastify from 'toastify-js'

export function awca_toast (message, type = "error") {
    if(message != null){
        Toastify({
            text: message,
            duration: 5000,
            newWindow: true,
            close: true,
            style: {
                background:
                    type === "error" ? "#cc3e3e" : type === "success" ? "#25ae25" : "#373636", // Custom background colors for different types
            },
            gravity: "bottom", // `top` or `bottom`
            position: "left", // `left`, `center` or `right`
            stopOnFocus: true, // Prevents dismissing of toast on hover
        }).showToast();
    }
}

export function paginateLinks(args) {
    var current = args.current || 1;
    var total = args.total || 1;
    var base = args.base || '#page-%#%';
    var format = args.format || '?page=%#%';
    var prevText = args.prev_text || '&laquo;';
    var nextText = args.next_text || '&raquo;';
    var paginationHtml = '';

    // Previous button
    if (current > 1) {
        paginationHtml += '<a href="' + base.replace('%#%', current - 1) + '" data-page="' + (current - 1) + '">' + prevText + '</a>';
    }

    // Page numbers
    for (var i = 1; i <= total; i++) {
        if (i === current) {
            paginationHtml += '<span class="current">' + i + '</span>'; // Current page
        } else {
            paginationHtml += '<a href="' + base.replace('%#%', i) + '" data-page="' + i + '">' + i + '</a>';
        }
    }

    // Next button
    if (current < total) {
        paginationHtml += '<a href="' + base.replace('%#%', current + 1) + '" data-page="' + (current + 1) + '">' + nextText + '</a>';
    }

    return paginationHtml;
}