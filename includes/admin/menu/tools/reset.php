<?php


?>

<div class="wrapper" style="margin-top:32px">
    <h2 class="awca_plugin_titles">ریست تنظیمات و وضعیت های پلاگین انار</h2>

    <form method="post" id="awca-reset-all-settings">
        <?php wp_nonce_field('reset_options_ajax_nonce', 'reset_options_ajax_field'); ?>
        <div class="stepper_button_container">
            <button type="submit" class="plugin_activation_button stepper_btn" id="awca_reset_options_btn">
                <span>ریست تنظیمات</span>
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>
        </div>

        <div class="" style="display: flex; flex-direction: column; align-items: center">
            <a href="#" class="toggle_show_hide" data-id="advanced_settings">تنظیمات پیشرفته</a>

            <div id="advanced_settings" style="display: none">
                <p class="awca-form-control">
                    <input type="checkbox" id="delete_map_data" name="delete_map_data">
                    <label for="delete_map_data">حذف اطلاعات متناظر سازی های دسته بندی ها و ویژگی ها</label>
                </p>

<!--                <p class="awca-form-control">-->
<!--                    <input type="checkbox" id="skipp_generate_products" name="skipp_generate_products" checked>-->
<!--                    <label for="skipp_generate_products">توقف پردازش محصولات (مشکلی ایجاد نمی‌شود، می توانید مجدد انجام دهید.)</label>-->
<!--                </p>-->
            </div>
        </div>
    </form>

</div>
