import {awca_toast} from "./functions";
import {paginateLinks} from "./functions";

(function($){

  let AnarHandler = {

    move_to_step : function (stepNumber) {
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
  },

    refreshData: function (){
    var refreshAnarData = jQuery('#refresh_anar_data')
    if(refreshAnarData.length !== 0){

      var catItem = refreshAnarData.find('.data-item.categories')
      var attrItem = refreshAnarData.find('.data-item.attributes')

      jQuery.ajax({
        url: awca_ajax_object.ajax_url,
        type: 'POST',
        data: {
          action: 'awca_get_categories_save_on_db_ajax'
        },
        success: function (response) {
          if (response.success) {

            catItem.addClass('loaded')
            getAndSaveAttributes()

          } else {
            awca_toast(response.message, "error");
          }
        },
        error: function (xhr, status, err) {
          awca_toast(xhr.responseText)

        },
      });


      function getAndSaveAttributes(){
        jQuery.ajax({
          url: awca_ajax_object.ajax_url,
          type: 'POST',
          data: {
            action: 'awca_get_attributes_save_on_db_ajax'
          },
          success: function (response) {
            if (response.success) {
              attrItem.addClass('loaded')
              awca_toast('همه اطلاعات دریافت شدند.', 'success')

              setTimeout(function(){
                location.reload()
              }, 2000)

            } else {
              awca_toast(response.message, "error");
            }
          },
          error: function (xhr, status, err) {
            awca_toast(xhr.responseText)

          },
        });
      }

    }
  },

    moveToProperStep: function (){
      let urlParams = new URLSearchParams(window.location.search);
      let step = urlParams.get('step');
      if (step && !isNaN(step)) {
        AnarHandler.move_to_step(parseInt(step));
      }
    },

    fetchNotifications: function (page, limit){
      var anarNotifications =  jQuery('#awca_notification_list')
      if(anarNotifications.length !== 0){

        var loadingIcon = anarNotifications.find('.spinner-loading')
        var msgType = 'error'

        jQuery.ajax({
          url: awca_ajax_object.ajax_url,
          type: "POST",
          dataType: "json",
          data: {
            action: 'awca_fetch_notifications_ajax',
            page: page,
            limit: limit
          },
          beforeSend: function () {
            loadingIcon.show();
          },
          success: function (response) {
            if (response.success) {
              anarNotifications.html(response.data.output)
              msgType = 'success'
              paginateNotifications(response.data.total, response.data.page, response.data.limit)
            }
            awca_toast(response.data.message, msgType);
          },
          error: function (xhr, status, err) {
            awca_toast(xhr.responseText)
            loadingIcon.hide();

          },
          complete: function () {
            loadingIcon.hide();
          },
        });

        function paginateNotifications(total, page, limit) {
          var pagination = jQuery('#awca_pagination');
          var totalPages = Math.ceil(total / limit);
          var paginationHtml = '';

          for (var i = 1; i <= totalPages; i++) {
            paginationHtml += '<button class="pagination-btn" data-page="' + i + '">' + i + '</button>';
          }

          pagination.html(paginationHtml);

          pagination.find('.pagination-btn').on('click', function () {
            this.fetchNotifications(jQuery(this).data('page'), limit);
          });
        }

      }

    },

    fetchPendingPayments: function (page, limit){
      var anarPayments =  jQuery('#awca_payments')
      if(anarPayments.length !== 0){

        var loadingIcon = anarPayments.find('.spinner-loading')
        var payableEl = anarPayments.find('#awca_payable')
        var listEl = anarPayments.find('#awca_payment_list')
        var loadingFrame = anarPayments.find('#awca-loading-frame')
        var msgType = 'error'

        jQuery.ajax({
          url: awca_ajax_object.ajax_url,
          type: "POST",
          dataType: "json",
          data: {
            action: 'awca_fetch_payments_ajax',
            page: page,
            limit: limit
          },
          beforeSend: function () {
            loadingFrame.show();
          },
          success: function (response) {
            if (response.success) {
              listEl.html(response.data.output)
              payableEl.html(response.data.payable)
              paginatePendingPayments(response.data.total, page, limit)
              msgType = 'success'
            }
            awca_toast(response.data.message, msgType);
          },
          error: function (xhr, status, err) {
            awca_toast(xhr.responseText)
            loadingFrame.hide();

          },
          complete: function () {
            loadingFrame.hide();
          },
        });

      }

      function paginatePendingPayments(total, page, limit) {
        var pagination = jQuery('#awca_pagination');
        var totalPages = Math.ceil(total / limit);

        // Generate pagination links using WordPress-style pagination
        var paginationHtml = paginateLinks({
          current: page,
          total: totalPages,
          base: '#page-%#%', // Placeholder for pagination links
          format: '?page=%#%', // URL structure for pages
          prev_text: '&laquo;', // Previous page link text
          next_text: '&raquo;', // Next page link text
        });

        pagination.html(paginationHtml);

        // Handle pagination clicks
        pagination.find('a').on('click', function (e) {
          e.preventDefault();
          var newPage = jQuery(this).data('page');
          AnarHandler.fetchPendingPayments(newPage, limit);
        });
      }
    },

    clearSelect: function(target, type = "varient"){
      const selects = jQuery(".varient_selcet");
      const categorySelects = jQuery(".category_select");
      const cat_icons = jQuery(".clear-cat-icon");
      const icons = jQuery(".clear-icon");

      if (type !== "varient") {
        categorySelects[target].selectedIndex = 0;
        cat_icons[target].style.display = "none";
      } else {
        selects[target].selectedIndex = 0;
        icons[target].style.display = "none";
      }
    },

    changeCategorySelect: function (e, target) {
      const icons = jQuery(".clear-cat-icon");
      if (e.value !== "0") {
        icons[target].style.display = "block";
      } else {
        icons[target].style.display = "none";
      }
    }
  }


  $(document).ready(function () {

        jQuery("#plugin_sync_form").on("submit", function (e) {
          e.preventDefault();

          var form = jQuery(this);
          var LoadingIcon = form.find('.spinner-loading')

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
                awca_toast(response.message, "success");
              } else {
                awca_toast(response.message, "error");
                if (response.activation_status) {
                  awca_toast(response.message, "success");
                }
              }

            },
            error: function (xhr, status, err) {
              LoadingIcon.hide();

              awca_toast(xhr.responseText)

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
                awca_toast(response.message, "success");
                window.location.reload();
              } else {
                awca_toast(response.message, "error");
                if (response.activation_status) {
                  awca_toast(response.message, "success");
                }
              }

            },
            error: function (xhr, status, err) {
              jQuery(".spinner-loading").hide();

              awca_toast(xhr.responseText)

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
                awca_toast(response.message, "success");
                AnarHandler.move_to_step(3)
              }
            },
            error: function (xhr, status, err) {
              awca_toast(xhr.responseText)
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
                awca_toast(response.message, "success");
                AnarHandler.move_to_step(4)
              }
            },
            error: function (xhr, status, err) {
              awca_toast(xhr.responseText)
              jQuery(".spinner-loading").hide();
              jQuery(".configuration_save_button").removeAttr("disabled");

            },
            complete: function () {
              jQuery(".spinner-loading").hide();
              jQuery(".configuration_save_button").removeAttr("disabled");

            },
          });
        });

        jQuery('#awca-dl-the-product-images').on('click', function(e){
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
              awca_toast(response.data.message, msgType);
            },
            error: function (xhr, status, err) {
              awca_toast('حطایی در برقراری ارتباط پیش آمده است.')
              loadingIcon.hide();
              $(this).removeAttr("disabled");

            },
            complete: function () {
              loadingIcon.hide();
              $(this).removeAttr("disabled");
            },
          });

        })

        jQuery('#get-save-categories-btn').on('click', function(e){
          e.preventDefault()

          function getAndSaveCategories() {
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
              success: function (response) {
                if (response.success) {
                  awca_toast(response.message, "success");
                  location.reload();
                } else {
                  awca_toast(response.message, "error");
                }
              },
              error: function (xhr, status, err) {
                $(".spinner-loading").hide();
                $("#get-save-categories-btn").removeAttr("disabled");

                awca_toast(xhr.responseText)

              },
              complete: function () {
                $(".spinner-loading").hide();
                $("#get-save-categories-btn").removeAttr("disabled");
              },
            });
          }

          getAndSaveCategories();

        })

        jQuery('#get-save-attributes-btn').on('click', function(e){
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
              awca_toast(response.message, "success");
              location.reload();
            } else {
              awca_toast(response.message, "error");
            }
          },
          error: function (xhr, status, err) {
            loadingIcon.hide();
            $(this).removeAttr("disabled");

            awca_toast(xhr.responseText)

          },
          complete: function () {
            loadingIcon.hide();
            $(this).removeAttr("disabled");
          },
        });
      })

        $('#handle-bg-process a').on('click', function(e) {
          e.preventDefault();

          var action = $(this).data('action');

          $.ajax({
            url: awca_ajax_object.ajax_url, // WordPress AJAX URL
            type: 'POST',
            data: {
              action: 'handle_process_actions',
              process_action: action // This is the action to perform (resume, pause, cancel)
            },
            success: function(response) {
              awca_toast('با موفقیت انجام شد.', 'success')
            },
            error: function(xhr, status, error) {
              awca_toast('مشکلی بوجود آمده. خطا: ' + error, 'error')
            }
          });
        });

        $('[data-next-step]').on('click', function(){
          AnarHandler.move_to_step($(this).data('next-step'))
        })

        AnarHandler.fetchNotifications(1, 10);
        AnarHandler.fetchPendingPayments(1, 10)
        AnarHandler.refreshData()
        AnarHandler.moveToProperStep()

  })

})(jQuery);

