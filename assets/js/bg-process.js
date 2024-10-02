import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    function dlAllProductGalleryImages() {
        var dlAllProductGalleryImagesForm = $('#awca-dl-all-product-gallery-images');
        var spinnerLoading = dlAllProductGalleryImagesForm.find('.spinner-loading');
        var resultEl = dlAllProductGalleryImagesForm.find('.awca_ajax-result');
        var progressEl = dlAllProductGalleryImagesForm.find('.awca_ajax-result-progress');
        var formButton = dlAllProductGalleryImagesForm.find('#dl_product_g_imgs_btn');
        var processControllers = dlAllProductGalleryImagesForm.find('.process-controllers')

        if (dlAllProductGalleryImagesForm.length !== 0) {
            dlAllProductGalleryImagesForm.on('submit', function (e) {
                e.preventDefault();


                var msgType = 'error';
                var currentPage = 1;
                var totalQueuedImages = 0;
                var QueuedProducts = 0;

                function updateProgressBar(totalProducts, totalImages) {
                    // Example: Update your progress bar here based on totalProducts and totalImages
                    console.log('Processed products: ' + totalProducts + ', Processed images: ' + totalImages);
                }

                function processNextPage() {
                    $.ajax({
                        url: awca_ajax_object.ajax_url,
                        type: "POST",
                        dataType: "json",
                        data: {
                            action: 'awca_run_dl_product_gallery_images_bg_process_ajax',
                            paged: currentPage
                        },
                        beforeSend: function () {
                            spinnerLoading.show();
                            formButton.attr("disabled", "disabled");
                        },
                        success: function (response) {
                            // console.log('page: ' + currentPage)
                            // console.log(response)
                            if (response.success) {
                                msgType = 'success';
                                QueuedProducts += response.data.queued_products;

                                if(QueuedProducts > 0){
                                    var message = QueuedProducts +' محصول به صف دانلود تصاویر گالری اضافه شد.'
                                    resultEl.addClass('success').text(message);
                                }

                                if (!response.data.finished) {
                                    currentPage = response.data.next_paged;
                                    processNextPage(); // Continue to the next page
                                } else {
                                    spinnerLoading.hide();
                                    // formButton.removeAttr("disabled");
                                    awca_toast(response.data.message, msgType);
                                    setInterval(function (){
                                        location.reload()
                                    }, 1000)
                                }
                            } else {
                                spinnerLoading.hide();
                                formButton.removeAttr("disabled");
                                awca_toast(response.data.message, msgType);
                            }
                        },
                        error: function (xhr, status, err) {
                            spinnerLoading.hide();
                            formButton.removeAttr("disabled");
                            awca_toast(xhr.responseText);
                        },
                        complete: function () {

                        }
                    });
                }

                // Start the first request
                processNextPage();
            }); // end on click



            // when Process running we need to show reports and have some controls
            function progressHandler() {
                $.ajax({
                    url: awca_ajax_object.ajax_url,
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: 'awca_dl_product_gallery_images_bg_process_data_ajax',
                    },
                    beforeSend: function () {
                    },
                    success: function (response) {
                        if (response.success) {

                            if(response.data.process_status === 'processing' || response.data.process_status === 'queued'){

                                var processedProducts = response.data.processed_products;
                                var queuedProducts = response.data.queued_products;
                                var totalProducts = response.data.total_products;
                                console.log(processedProducts +' of total:'+ totalProducts + 'queued:' + queuedProducts)
                                var percent = Math.ceil((processedProducts * 100) / totalProducts);

                                progressEl.show();
                                if(percent <= 100){
                                    progressEl.find('.bar').animate({"width": percent + "%"}, 500);
                                    var dataMessage = percent + '%'
                                    dlAllProductGalleryImagesForm.find('.process_data_message').text(dataMessage)
                                }

                            }else{
                                awca_toast('دریافت تصاویر با موفقیت به اتمام رسید.', 'success')
                                setInterval(function (){
                                    location.reload()
                                }, 2000)

                            }

                        }else{
                            console.log(response)
                        }
                    },
                    error: function (xhr, status, err) {
                        awca_toast(xhr.responseText);
                    },
                    complete: function () {

                    }
                });
            }

            function processController(){
                processControllers.find('a').on('click', function(e) {
                    e.preventDefault();

                    var action = $(this).data('action');

                    $.ajax({
                        url: awca_ajax_object.ajax_url, // WordPress AJAX URL
                        type: 'POST',
                        data: {
                            action: 'awca_handle_dl_product_gallery_images_process_actions',
                            process_action: action // This is the action to perform (resume, pause, cancel)
                        },
                        success: function(response) {
                            $(this).hide()
                            if($(this).hasClass('pause')){
                                processControllers.find('.resume').show()
                            }else if($(this).hasClass('resume')){
                                processControllers.find('.pause').show()
                            }else if($(this).hasClass('cancel')){
                                location.reload()
                            }
                            awca_toast('عملیات با موفقیت انجام شد.', 'success')
                        },
                        error: function(xhr, status, error) {
                            console.log('xhr:', xhr);
                            console.log('status:', status);
                            awca_toast('مشکلی در انجام درخواست شما بوجود آمد. خطا: ' + error, 'error')
                        }
                    });
                });
            }


            if (processControllers.length !== 0){
                setInterval(progressHandler, 2000)
                processController()
            }


        }
    }

    dlAllProductGalleryImages();
});