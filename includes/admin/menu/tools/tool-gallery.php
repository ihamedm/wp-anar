<div class="wrapper anar-tools-wrapper" style="margin-top:32px">
    <h2 class="awca_plugin_titles">دانلود تصاویر گالری محصولات انار</h2>
    <p class="awca_plugin_subTitles">
    </p>

    <form method="post" id="anar-dl-products-gallery">
        <?php wp_nonce_field('anar_dl_products_gallery_ajax_nonce', 'security_nonce'); ?>
        <div class="stepper_button_container">
            <button class="awca-btn awca-success-btn awca-outline-btn submit-button" id="anar_estimate_products_gallery">
                <span>تخمین فضای مورد نیاز</span>
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>

        </div>
        <div class="estimate-messages" style="color: green; text-align: center"></div>

        <div class="" style="display: flex; flex-direction: column; align-items: center;">
            <div id="advanced_settings" style="display: block">
                <p class="awca-form-control">
                    <span>حداکثر</span>
                    <input type="number" id="max_images" name="max_images" value="5" min="1" max="15" style="width: 50px;margin: 0">
                    <label for="max_images">تصویر برای گالری هر محصول دانلود کن</label>
                </p>
            </div>
        </div>

        <div class="stepper_button_container" >
            <button  class="awca-btn awca-success-btn submit-button" id="anar_dl_products_gallery" style="display:none !important">
                <span>دانلود تصاویر گالری</span>
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>
        </div>

        <div class="anar-batch-messages"></div>
        <div class="anar-batch-progress"><span class="bar"></span></div>
    </form>

</div>
