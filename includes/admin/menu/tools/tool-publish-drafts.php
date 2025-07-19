<div class="wrapper anar-tools-wrapper"">
    <h2 class="awca_plugin_titles">انتشار همه محصولات انار</h2>
    <p class="awca_plugin_subTitles">
        محصولات انار بصورت پیش فرض در حالت <strong>پیش نویس</strong> هستند.
        اگر قصد ندارید محصولات را تک به تک بررسی و منتشر کنید می توانید با کلیک کردن روی دکمه زیر کل محصولات را در چند ثانیه منتشر کنید.
    </p>

    <form method="post" id="publish-anar-products">
        <?php wp_nonce_field('publish_anar_products_ajax_nonce', 'security_nonce'); ?>
        <div class="stepper_button_container">
            <button type="submit" class="awca-btn awca-success-btn submit-button" id="awca_publish_anar_products_btn">
                <span>انتشار همه محصولات انار</span>
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>
        </div>

        <div class="" style="display: flex; flex-direction: column; align-items: center">
            <div id="advanced_settings" style="display: block">
                <p class="awca-form-control">
                    <input type="checkbox" id="skipp_out_of_stocks" name="skipp_out_of_stocks" checked>
                    <label for="skipp_out_of_stocks">محصولات ناموجود را منتشر نکن</label>
                </p>
            </div>
        </div>
        <div class="" style="display: flex; flex-direction: column; align-items: center">
            <a href="#" class="toggle_show_hide" data-id="publish_advanced_settings">تنظیمات پیشرفته</a>
            <div id="publish_advanced_settings" style="display: none">
                <p class="awca-form-control" style="justify-content: center">
                    <input type="checkbox" id="use_sql" name="use_sql" >
                    <label for="use_sql" style="width: auto"><strong>روش جایگزین</strong></label>
                </p>
                <p class="awca-form-control">
                    <label for="use_sql"> اگر حالت عادی خطا دریافت می کنید این گزینه را فعال کنید و مجددتلاش کنید</label>
                </p>

            </div>
        </div>

        <div class="anar-batch-messages"></div>
        <div class="anar-batch-progress"><span class="bar"></span></div>
    </form>

</div>
