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
          const fullSync = form.find("#full_sync")

          jQuery.ajax({
            url: form.attr("action"),
            type: "POST",
            dataType: "json",
            timeout: 2000000,
            data: form.serialize(),
            beforeSend: function () {
              LoadingIcon.show();

              if(fullSync.is(':checked')) {
                awca_toast('همگام سازی کل محصولات اندکی زمان بر خواهد بود و در پس زمینه انجام می شود.', "success");
                LoadingIcon.hide();
              }
            },
            success: function (response) {

              if (response.success) {
                awca_toast(response.message, "success");
              } else {
                awca_toast(response.message, "error");
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
              jQuery(".spinner-loading").hide();

              awca_toast(xhr.responseText)

            },
            complete: function () {
              jQuery(".spinner-loading").hide();
              window.location.reload();
            },
          });
        });

        jQuery("#plugin_category_creation_form").on("submit", function (e) {
          e.preventDefault();

          var form = jQuery(this);

          jQuery.ajax({
            url: form.attr("action"),
            type: "POST",
            // dataType: "json",
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

        $('.awca-faq').on('click', '.faq-question', function(e){
          e.preventDefault()
          var faqItem = $(this).parents('.faq-item')
          faqItem.toggleClass('active')
        });


        $('.toggle_show_hide').on('click', function(e){
          e.preventDefault()
          var elementID = $(this).data('id')
          $('#' + elementID).toggle()
        })

        AnarHandler.refreshData()
        AnarHandler.moveToProperStep()

  })



})(jQuery);

