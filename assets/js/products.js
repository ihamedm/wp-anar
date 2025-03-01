import {awca_toast} from "./functions";

jQuery(document).ready(function($) {

    var productListTable = $('.awca_product_list_table')

    if(productListTable.length !== 0){
        var currentPage = 1;
        var totalPages = 1;
        var perPage = 10;

        function loadProducts(page) {

            // Show placeholders while loading
            productListTable.find('tbody').html(`
            ${'<tr class="placeholder"><td>—</td><td><div class="placeholder-image"></div></td><td><div class="placeholder-text"></div></td><td><div class="placeholder-text"></div></td><td><div class="placeholder-text"></div></td><td><div class="placeholder-text"></div></td><td><div class="placeholder-text"></div></td><td><div class="placeholder-text"></div></td></tr>'.repeat(10)}
        `);

            currentPage = page;
            $.ajax({
                url: awca_ajax_object.ajax_url,
                type: 'GET',
                data: {
                    action: 'awca_fetch_products_paginate_ajax',
                    page: page
                },
                success: function(response) {
                    if (response.success) {
                        var products = response.data.items;
                        var tbody = productListTable.find('.list');
                        var rows = '';

                        $.each(products, function(index, product) {
                            rows += '<tr class="item">';
                            rows += '<td>' + (((currentPage - 1) * perPage) + (index+1) ) + '</td>';
                            rows += '<td><img class="awca_product_images" src="' + (product.mainImage ? product.mainImage : '') + '" alt="' + product.title + '"></td>';
                            rows += '<td class="awca_product_title">' + product.title + '</td>';
                            rows += '<td style="cursor: pointer;" >' + makeShortDescription(product.description ?? '') + '...</td>';
                            rows += '<td style="cursor: pointer;" >مشاهده</td>';
                            rows += '<td class="awca_product_price">' + product.variants[0].priceForResell + ' تومان</td>';
                            rows += '<td class="awca_product_price">' + product.variants[0].price + ' تومان</td>';
                            rows += '<td style="cursor: pointer;" onclick="awca_complete_desc(\'' + JSON.stringify(product.shipments) + '\', \'روش‌های ارسال محصول\')">مشاهده</td>' ;
                            rows += '</tr>';
                        });

                        tbody.html(rows);

                        totalPages = Math.ceil(response.data.total / perPage); // Calculate total pages

                        $('#current-page').text(page);
                        $('#prev-page').prop('disabled', page === 1);
                        $('#next-page').prop('disabled', products.length === 0);

                        generatePaginationNumbers(page, totalPages);
                    } else {
                        console.log(response.data);
                    }
                },
                error: function(error) {
                    console.log(error);
                }
            });
        }

        function generatePaginationNumbers(currentPage, totalPages) {
            var paginationNumbers = $('#pagination-numbers');
            paginationNumbers.empty(); // Clear existing page numbers

            // Add Prev button
            if (currentPage > 1) {
                var prevPageNumber = $('<li class="prev">قبلی</li>');
                prevPageNumber.click(function() {
                    loadProducts(currentPage - 1);
                });
                paginationNumbers.append(prevPageNumber);
            }

            if (totalPages <= 6) {
                // If total pages are less than or equal to 6, show all page numbers
                for (var i = 1; i <= totalPages; i++) {
                    var pageNumber = $('<li>' + i + '</li>');
                    if (i === currentPage) {
                        pageNumber.addClass('active');
                    }
                    pageNumber.click((function(page) {
                        return function() {
                            loadProducts(page);
                        };
                    })(i));
                    paginationNumbers.append(pageNumber);
                }
            } else {
                // Show first 2 pages
                for (var i = 1; i <= 2; i++) {
                    var pageNumber = $('<li>' + i + '</li>');
                    if (i === currentPage) {
                        pageNumber.addClass('active');
                    }
                    pageNumber.click((function(page) {
                        return function() {
                            loadProducts(page);
                        };
                    })(i));
                    paginationNumbers.append(pageNumber);
                }

                // Show dots if needed before the current page
                if (currentPage > 4) {
                    paginationNumbers.append('<li class="dots">...</li>');
                }

                // Show up to 3 pages around the current page
                for (var i = Math.max(3, currentPage - 1); i <= Math.min(totalPages - 2, currentPage + 1); i++) {
                    if (i > 2 && i < totalPages - 1) {
                        var pageNumber = $('<li>' + i + '</li>');
                        if (i === currentPage) {
                            pageNumber.addClass('active');
                        }
                        pageNumber.click((function(page) {
                            return function() {
                                loadProducts(page);
                            };
                        })(i));
                        paginationNumbers.append(pageNumber);
                    }
                }

                // Show dots if needed after the current page
                if (currentPage < totalPages - 3) {
                    paginationNumbers.append('<li class="dots">...</li>');
                }

                // Show last 2 pages
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    var pageNumber = $('<li>' + i + '</li>');
                    if (i === currentPage) {
                        pageNumber.addClass('active');
                    }
                    pageNumber.click((function(page) {
                        return function() {
                            loadProducts(page);
                        };
                    })(i));
                    paginationNumbers.append(pageNumber);
                }
            }

            // Add Next button
            if (currentPage < totalPages) {
                var nextPageNumber = $('<li class="next">بعدی</li>');
                nextPageNumber.click(function() {
                    loadProducts(currentPage + 1);
                });
                paginationNumbers.append(nextPageNumber);
            }
        }



        $('#prev-page').click(function() {
            if (currentPage > 1) {
                currentPage--;
                loadProducts(currentPage);
            }
        });

        $('#next-page').click(function() {
            currentPage++;
            loadProducts(currentPage);
        });

        // Initial load
        loadProducts(currentPage);

    }

    function makeShortDescription(text){
        const WORD_LIMIT = 20;

        // Check if text is provided
        if (!text) return text;

        // Remove HTML tags and split into words
        const cleanText = text.replace(/<\/?[^>]+>/gi, '').trim();
        const words = cleanText.split(/\s+/); // Split by whitespace

        // Return the first WORD_LIMIT words or the original text if empty
        return words.length > 0
            ? words.slice(0, WORD_LIMIT).join(' ')
            : text;
    }


});

jQuery(document).ready(function($) {
    var getAndSaveProductsBtn = $('#get-save-products-btn');
    var isTaskRunning = false; // Track if the task is running

    if (getAndSaveProductsBtn.length !== 0) {
        getAndSaveProductsBtn.on('click', function(e) {
            e.preventDefault();

            var spinnerLoading = $(this).find(".spinner-loading");
            var resultEl = $(this).next('.awca_step-ajax-result');
            var wrapper = $(this).parent('.awca-save-products-wrapper');
            var progressEl = wrapper.find('.awca_step-ajax-result-progress');
            var page = 1;
            var retries = 0;
            var maxRetries = 10;
            var totalAdded = 0;
            var totalProducts = 0;
            var hasMorePages = true; // Add a variable to track if there are more pages

            // Set task running flag
            isTaskRunning = true;

            function fetchProducts(page) {
                $.ajax({
                    url: awca_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'awca_get_products_save_on_db_ajax',
                        page: page
                    },
                    beforeSend: function() {
                        spinnerLoading.show();
                        getAndSaveProductsBtn.attr("disabled", "disabled");
                    },
                    success: function(response) {
                        if (response.success) {
                            totalAdded += response.total_added;
                            totalProducts = response.total_products; // Set the total products only once

                            var percent = (totalAdded * 100) / totalProducts + "%";

                            progressEl.show();
                            progressEl.find('.bar').animate({"width": percent}, 500);

                            var message = totalAdded + ' محصول از کل ' + totalProducts + ' دریافت شد';
                            resultEl.addClass('success').text(message);

                            hasMorePages = response.has_more; // Update the hasMorePages variable
                            if (hasMorePages) {
                                fetchProducts(page + 1); // Fetch the next page
                            } else {
                                spinnerLoading.hide();
                                // getAndSaveProductsBtn.removeAttr("disabled");
                                resultEl.append('<p style="font-weight:bold;">همه محصولات انار دریافت شد. ساخت محصولات در پس زمینه انجام می شود. می توانید این صفحه را ببندید.</p>');
                                awca_toast('کل محصولات انار دریافت شد.', "success");
                                isTaskRunning = false; // Task completed

                                // Run the background process after fetching all pages
                                //runProductCreationBackgroundProcess();
                            }
                        } else {
                            resultEl.addClass('error').text(response.message);
                            awca_toast(response.message, "error");
                            spinnerLoading.hide();
                            getAndSaveProductsBtn.removeAttr("disabled");
                            isTaskRunning = false; // Task failed
                        }
                    },
                    error: function(xhr, status, err) {
                        if (retries < maxRetries) {
                            retries++;
                            awca_toast('ارتباط قطع شد. تلاش مجدد ... (' + retries + '/' + maxRetries + ')');
                            fetchProducts(page); // Retry the same page
                        } else {
                            spinnerLoading.hide();
                            getAndSaveProductsBtn.removeAttr("disabled");
                            resultEl.addClass('error').text('ارتباط پس از ' + maxRetries + ' تلاش مجدد قطع شد!');
                            isTaskRunning = false; // Task failed
                        }
                    },
                    complete: function() {
                        if (!hasMorePages) {
                            spinnerLoading.hide();
                            // getAndSaveProductsBtn.removeAttr("disabled");
                        }
                    }
                });
            }

            // Function to run the background process


            fetchProducts(page);
        });

        // Add beforeunload event listener to alert user if the task is running
        window.addEventListener('beforeunload', function(e) {
            if (isTaskRunning) {
                var confirmationMessage = 'در صورت بستن یا باگزاری مجدد دریافت اطلاعات از انار قطع می شود. مطمئنید؟';
                (e || window.event).returnValue = confirmationMessage; // For older browsers
                return confirmationMessage; // For modern browsers
            }
        });


    }

    function runProductCreationBackgroundProcess() {
        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'awca_run_product_creation_background_process_ajax',
                nonce: awca_ajax_object.nonce
            },
            success: function(response) {
                awca_toast('ساخت محصولات در پس زمینه شروع شد.', "success");
            },
            error: function(xhr, status, err) {
                awca_toast('مشکلی در شروع فرآیند پس زمینه ایجاد شد.', "error");
            }
        });
    }

    $('#start_bg_process_products').on('click', function(e) {
        e.preventDefault();
        runProductCreationBackgroundProcess()
    })
});


