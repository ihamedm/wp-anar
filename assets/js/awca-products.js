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
                            rows += '<td style="cursor: pointer;" >' + makeShortDescription(product.description) + '...</td>';
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
        return text.replace(/<\/?[^>]+>/gi, '').split(' ').slice(0, 20).join(' ')
    }


    var getAndSaveProductsBtn = $('#get-save-products-btn')
    if(getAndSaveProductsBtn.length !== 0){
        getAndSaveProductsBtn.on('click', function(e){
            e.preventDefault()

            var spinnerLoading = $(this).find(".spinner-loading")
            var resultEl = $(this).next('.awca_step-ajax-result')

            $.ajax({
                url: awca_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'awca_get_products_save_on_db_ajax'
                },
                beforeSend: function () {
                    spinnerLoading.show();
                    $(this).attr("disabled", "disabled");
                },
                success: function(response) {
                    console.log('awca_get_products_save_on_db_ajax', response)
                    if (response.success) {
                        resultEl.addClass('success').text(response.message)
                        awca_show_toast(response.message, "success");
                    } else {
                        resultEl.addClass('error').text(response.message)
                        awca_show_toast(response.message, "error");
                    }
                },
                error: function (xhr, status, err) {
                    spinnerLoading.hide();
                    $(this).removeAttr("disabled");

                    awca_show_toast(xhr.responseText)

                },
                complete: function () {
                    spinnerLoading.hide();
                    $(this).removeAttr("disabled");
                },
            });
        })
    }

});