function awca_move_to_step(stepNumber) {
  let steps = document.querySelectorAll(".step");
  let stepsTitle = document.querySelectorAll(".step-title");
  steps.forEach(function (step, index) {
    if (index + 1 <= stepNumber) {
      step.classList.add("active");
    } else {
      step.classList.remove("active");
    }
  });
  stepsTitle.forEach(function (step, index) {
    if (index + 1 == stepNumber) {
      step.classList.add("activeTitle");
    } else {
      step.classList.remove("activeTitle");
    }
  });
  let stepContents = document.querySelectorAll(".stepContent");
  stepContents.forEach(function (content, index) {
    if (index + 1 === stepNumber) {
      content.classList.add("active");
    } else {
      content.classList.remove("active");
    }
  });
  let persent = stepNumber == 2 ? 85 : 86;
  let progressWidth = ((stepNumber - 1) / (steps.length - 1)) * persent + "%";

  document.querySelector(".stepper-progress").style.width = progressWidth;

  // Update the URL query parameter
  let url = new URL(window.location);
  let params = new URLSearchParams(url.search);
  params.set('step', stepNumber);
  url.search = params.toString();
  window.history.replaceState({}, '', url);
}


document.addEventListener('DOMContentLoaded', function() {
  // Function to get query parameters
  function getQueryParam(param) {
    let urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
  }

  // Get the 'step' query parameter
  let step = getQueryParam('step');

  // If the 'step' parameter exists and is a valid number, move to that step
  if (step && !isNaN(step)) {
    awca_move_to_step(parseInt(step));
  }
});



function awca_show_toast(message, type = "error") {
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

function clear_select(target, type = "varient") {
  const selects = document.getElementsByClassName("varient_selcet");
  const categorySelects = document.getElementsByClassName("category_select");
  const cat_icons = document.getElementsByClassName("clear-cat-icon");
  const icons = document.getElementsByClassName("clear-icon");

  if (type != "varient") {
    categorySelects[target].selectedIndex = 0;
    cat_icons[target].style.display = "none";
  } else {
    console.log(target);
    selects[target].selectedIndex = 0;
    icons[target].style.display = "none";
  }
}
function changeVarient(e, target) {
  const icons = document.getElementsByClassName("clear-icon");

  if (e.value != "0") {
    icons[target].style.display = "block";
  } else {
    icons[target].style.display = "none";
  }
}

function changeCategorySelect(e, target) {
  const icons = document.getElementsByClassName("clear-cat-icon");

  if (e.value != "0") {
    icons[target].style.display = "block";
  } else {
    icons[target].style.display = "none";
  }
}

function awca_complete_desc(desc, title = 'توضیحات کامل محصول') {
  jQuery('#fullDesctitle').html(title);
  jQuery('#fullDescContent').html(desc);
  jQuery('#fullDescModal').show();
}

(function ($) {
  "use strict";

  /**
   * All of the code for your admin-facing JavaScript source
   * should reside in this file.
   *
   * Note: It has been assumed you will write jQuery code here, so the
   * $ function reference has been prepared for usage within the scope
   * of this function.
   *
   * This enables you to define handlers, for when the DOM is ready:
   *
   * $(function() {
   *
   * });
   *
   * When the window is loaded:
   *
   * $( window ).load(function() {
   *
   * });
   *
   * ...and/or other possibilities.
   *
   * Ideally, it is not considered best practise to attach more than a
   * single DOM-ready or window-load handler for a particular page.
   * Although scripts in the WordPress core, Plugins and Themes may be
   * practising this, we should strive to set a better example in our own work.
   */



  jQuery(document).ready(function ($) {

    jQuery('#fullDescModal .close-btn').click(function () {
      jQuery('#fullDescModal').hide();
    });

    // Close modal when clicking outside the modal content
    jQuery('#fullDescModal').click(function (event) {
      if (jQuery(event.target).is(jQuery(this))) {
        jQuery(this).hide();
      }
    });

    var PluginSyncForm = $("#plugin_sync_form")
    PluginSyncForm.on("submit", function (e) {
      e.preventDefault();

      var form = jQuery(this);
      var LoadingIcon = PluginSyncForm.find('.spinner-loading')

      jQuery.ajax({
        url: form.attr("action"),
        type: "POST",
        dataType: "json",
        data: form.serialize(),
        beforeSend: function () {
          LoadingIcon.show();
        },
        success: function (response) {
          if (response.success) {
            awca_show_toast(response.message, "success");
          } else {
            awca_show_toast(response.message, "error");
            if (response.activation_status) {
              awca_show_toast(response.message, "success");
            }
          }

        },
        error: function (xhr, status, err) {
          LoadingIcon.hide();

          awca_show_toast(xhr.responseText)

        },
        complete: function () {
          LoadingIcon.hide();
        },
      });
    });

    jQuery("#plugin_activation_form").on("submit", function (e) {
      e.preventDefault();

      var form = jQuery(this);

      jQuery.ajax({
        url: form.attr("action"),
        type: "POST",
        dataType: "json",
        data: form.serialize(),
        beforeSend: function () {
          jQuery(".spinner-loading").show();
        },
        success: function (response) {
          console.log(response)
          if (response.success) {
            awca_show_toast(response.message, "success");
            window.location.reload();
          } else {
            awca_show_toast(response.message, "error");
            if (response.activation_status) {
              awca_show_toast(response.message, "success");
            }
          }

        },
        error: function (xhr, status, err) {
          jQuery(".spinner-loading").hide();

          awca_show_toast(xhr.responseText)

        },
        complete: function () {
          jQuery(".spinner-loading").hide();
        },
      });
    });


    jQuery("#plugin_category_creation_form").on("submit", function (e) {
      e.preventDefault();

      var form = jQuery(this);

      jQuery.ajax({
        url: form.attr("action"),
        type: "POST",
        dataType: "json",
        data: form.serialize(),
        beforeSend: function () {
          jQuery(".spinner-loading").show();
          jQuery(".configuration_save_button").attr("disabled", "disabled");
        },
        success: function (response) {
          if (response.success) {
            awca_show_toast(response.message, "success");
            awca_move_to_step(3)
          }
        },
        error: function (xhr, status, err) {
          awca_show_toast(xhr.responseText)
          jQuery(".spinner-loading").hide();
          jQuery(".configuration_save_button").removeAttr("disabled");

        },
        complete: function () {
          jQuery(".spinner-loading").hide();
          jQuery(".configuration_save_button").removeAttr("disabled");

        },
      });
    });


    jQuery("#plugin_attribute_creation_form").on("submit", function (e) {
      e.preventDefault();

      var form = jQuery(this);

      jQuery.ajax({
        url: form.attr("action"),
        type: "POST",
        dataType: "json",
        data: form.serialize(),
        beforeSend: function () {
          jQuery(".spinner-loading").show();
          jQuery(".configuration_save_button").attr("disabled", "disabled");
        },
        success: function (response) {
          if (response.success) {
            awca_show_toast(response.message, "success");
            awca_move_to_step(4)
          }
        },
        error: function (xhr, status, err) {
          awca_show_toast(xhr.responseText)
          jQuery(".spinner-loading").hide();
          jQuery(".configuration_save_button").removeAttr("disabled");

        },
        complete: function () {
          jQuery(".spinner-loading").hide();
          jQuery(".configuration_save_button").removeAttr("disabled");

        },
      });
    });



    var productCreationInterval = null;
    var creatingProductsInProgress = false;
// Store the AJAX request in a variable
    var productCreationAjaxRequest;

    var jobId = 'unique_job_id_' + Date.now();

    jQuery("#plugin_product_creation_form").on("submit", function (e) {
      e.preventDefault();
      creatingProductsInProgress = true;
      runProductCreatedCounter();

      var form = jQuery(this);

      productCreationAjaxRequest = jQuery.ajax({
        url: form.attr("action"),
        type: "POST",
        dataType: "json",
        data: form.serialize() + '&job_id=' + jobId,
        beforeSend: function () {
          $('#abort-ajax-link').show();
          jQuery(".spinner-loading").show();
          jQuery(".configuration_save_button").attr("disabled", "disabled");
        },
        success: function (response) {
          console.log('create product', response)
          if (response.success) {
            awca_show_toast('محصولات شما با موفقیت افزوده شد در حال هدایت به صفحه محصولات ....', "success");

            // Stop the interval when AJAX call completes
            creatingProductsInProgress = false;
            clearInterval(productCreationInterval);

            window.location.href = response.woo_url;
          } else {
            awca_show_toast(response.data.message);
          }
        },
        error: function (xhr, status, err) {
          // Check if the status is 524 (Cloudflare timeout)
          if (xhr.status === 524) {
            // Keep spinner and button in loading state
            console.log('524 error occurred, keeping button in loading state.');
          } else {
            // Handle other errors
            console.log('ajax error occurred');
            awca_show_toast(xhr.responseText);
            jQuery(".spinner-loading").hide();
            jQuery(".configuration_save_button").removeAttr("disabled");
          }
        },
        complete: function (xhr, status) {
          console.log('completed ajax')
          if (xhr.status === 524) {
            // Keep spinner and button in loading state
            console.log('524 error occurred, keeping button in loading state.');
          } else {
            $('#abort-ajax-link').hide();
            jQuery(".spinner-loading").hide();
            jQuery(".configuration_save_button").removeAttr("disabled");
          }
        },
      });
    });

    $('#abort-ajax-link').click(function(e) {
      e.preventDefault(); // Prevent default link behavior

      if (confirm('مطمئنید؟ با تایید شما افزودن محصولات متوقف می شود. البته می توانید دوباره افزودن محصولات را از مرحله اول شروع کنید تا ادامه فرآیند انجام شود.')) {
        if (productCreationAjaxRequest) {
          productCreationAjaxRequest.abort();
          console.log('AJAX request aborted.');
          $(".spinner-loading").hide();
          $(".configuration_save_button").removeAttr("disabled");
          $('#abort-ajax-link').hide();

          // Send an AJAX request to mark the job as aborted
          jQuery.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            data: { action: 'awca_abort_job', job_id: jobId },
            success: function(response) {
              console.log('Job marked as aborted.');
            }
          });
        }
      }

    });



    function runProductCreatedCounter() {
      var productCreatedCounter = jQuery('#product-counter')
      if (productCreatedCounter.length !== 0 && productCreationInterval) {
        productCreatedCounter.show(0)

        function checkProductCreationProgress() {
          jQuery.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            data: {
              action: 'awca_get_product_creation_progress'
            },
            success: function (response) {
              if (response.success) {
                console.log('productCreatedCounter:', response)
                productCreatedCounter.text(response.state_message);
              }
            }
          });
        }

        // Check progress every 500 ms
        productCreationInterval = setInterval(checkProductCreationProgress, 1000);
      }
    }


    var dlTheProductImagesBtn =  $('#awca-dl-the-product-images')
    if(dlTheProductImagesBtn.length !== 0){

      dlTheProductImagesBtn.on('click', function(e){
        e.preventDefault();

        var loadingIcon = $(this).find('.spinner-loading')
        var ProductID = $(this).data('product-id')
        var msgType = 'error'

        jQuery.ajax({
          url: awca_ajax_object.ajax_url,
          type: "POST",
          dataType: "json",
          data: {
            product_id : ProductID ,
            action: 'awca_dl_the_product_images_ajax'
          },
          beforeSend: function () {
            loadingIcon.show();
            $(this).attr("disabled", "disabled");
          },
          success: function (response) {
            if (response.success) {
              location.reload();
              msgType = 'success'
            }
            awca_show_toast(response.data.message, msgType);
          },
          error: function (xhr, status, err) {
            awca_show_toast('حطایی در برقراری ارتباط پیش آمده است.')
            loadingIcon.hide();
            $(this).removeAttr("disabled");

          },
          complete: function () {
            loadingIcon.hide();
            $(this).removeAttr("disabled");
          },
        });

      })

    }



    var dlAllProductImagesForm =  $('#awca-dl-all-product-images')
    if(dlAllProductImagesForm.length !== 0){

      dlAllProductImagesForm.on('submit', function(e){
        e.preventDefault();

        var loadingIcon = dlAllProductImagesForm.find('.spinner-loading')
        var msgType = 'error'

        jQuery.ajax({
          url: awca_ajax_object.ajax_url,
          type: "POST",
          dataType: "json",
          data: {
            action: 'awca_dl_all_product_images_ajax'
          },
          beforeSend: function () {
            loadingIcon.show();
            $(this).attr("disabled", "disabled");
          },
          success: function (response) {
            console.log(response)
            if (response.success) {
              msgType = 'success'
            }
            awca_show_toast(response.data.message, msgType);
          },
          error: function (xhr, status, err) {
            awca_show_toast(xhr.responseText)
            loadingIcon.hide();
            $(this).removeAttr("disabled");

          },
          complete: function () {
            loadingIcon.hide();
            $(this).removeAttr("disabled");
          },
        });

      })

    }

    var dlAllProductGalleryImagesForm =  $('#awca-dl-all-product-gallery-images')
    if(dlAllProductGalleryImagesForm.length !== 0){

      dlAllProductGalleryImagesForm.on('submit', function(e){
        e.preventDefault();

        var loadingIcon = dlAllProductGalleryImagesForm.find('.spinner-loading')
        var msgType = 'error'

        jQuery.ajax({
          url: awca_ajax_object.ajax_url,
          type: "POST",
          dataType: "json",
          data: {
            action: 'awca_dl_all_product_gallery_images_ajax'
          },
          beforeSend: function () {
            loadingIcon.show();
            $(this).attr("disabled", "disabled");
          },
          success: function (response) {
            console.log(response)
            if (response.success) {
              msgType = 'success'
            }
            awca_show_toast(response.data.message, msgType);
          },
          error: function (xhr, status, err) {
            awca_show_toast(xhr.responseText)
            loadingIcon.hide();
            $(this).removeAttr("disabled");

          },
          complete: function () {
            loadingIcon.hide();
            $(this).removeAttr("disabled");
          },
        });

      })

    }




    var getAndSaveCategoriesBtn = $('#get-save-categories-btn')
    if(getAndSaveCategoriesBtn.length !== 0){
      getAndSaveCategoriesBtn.on('click', function(e){
        e.preventDefault()

        $.ajax({
          url: awca_ajax_object.ajax_url,
          type: 'POST',
          data: {
            action: 'awca_get_categories_save_on_db_ajax'
          },
          beforeSend: function () {
            $(".spinner-loading").show();
            $("#get-save-categories-btn").attr("disabled", "disabled");
          },
          success: function(response) {
            if (response.success) {
              awca_show_toast(response.message, "success");
              location.reload();
            } else {
              awca_show_toast(response.message, "error");
            }
          },
          error: function (xhr, status, err) {
            $(".spinner-loading").hide();
            $("#get-save-categories-btn").removeAttr("disabled");

            awca_show_toast(xhr.responseText)

          },
          complete: function () {
            $(".spinner-loading").hide();
            $("#get-save-categories-btn").removeAttr("disabled");
          },
        });
      })
    }


    var getAndSaveAttributesBtn = $('#get-save-attributes-btn')
    if(getAndSaveAttributesBtn.length !== 0){
      getAndSaveAttributesBtn.on('click', function(e){
        e.preventDefault()

        var loadingIcon = $(this).find('.spinner-loading')

        $.ajax({
          url: awca_ajax_object.ajax_url,
          type: 'POST',
          data: {
            action: 'awca_get_attributes_save_on_db_ajax'
          },
          beforeSend: function () {
            loadingIcon.show();
            $(this).attr("disabled", "disabled");
          },
          success: function(response) {
            if (response.success) {
              awca_show_toast(response.message, "success");
              location.reload();
            } else {
              awca_show_toast(response.message, "error");
            }
          },
          error: function (xhr, status, err) {
            loadingIcon.hide();
            $(this).removeAttr("disabled");

            awca_show_toast(xhr.responseText)

          },
          complete: function () {
            loadingIcon.hide();
            $(this).removeAttr("disabled");
          },
        });
      })
    }

  });







})(jQuery);



