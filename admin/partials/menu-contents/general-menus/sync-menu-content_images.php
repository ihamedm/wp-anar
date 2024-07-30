<div class="wrapper" style="margin-top:32px">
    <h2 class="awca_plugin_titles">همگام سازی <span>تصاویر</span> محصولات با انار</h2>
    <p class="awca_plugin_subTitles">
        با استفاده از دکمه زیر میتوانید تصاویر همه محصولات را از انار دانلود کنید و روی محصولات ووکامرسی خود تنظیم کنید.
    </p>
    <form method="post" id="awca-dl-all-product-images">
        <input type="hidden" name="action" value="awca_dl_all_product_images_ajax" />
        <?php wp_nonce_field('awca_dl_all_product_images_ajax_nonce', 'awca_dl_all_product_images_ajax_field'); ?>
        <div class="stepper_button_container">
            <button type="submit" class="plugin_activation_button stepper_btn" id="next_stepper_btn plugin_activation_button"
                <?php echo get_transient('awca_dl_all_product_images_lock') ? 'disabled': '';?>>
                <span>دانلود تصاویر شاخص محصولات انار</span>
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>
        </div>
    </form>
    <hr>
    <form method="post" id="awca-dl-all-product-gallery-images">
        <input type="hidden" name="action" value="awca_dl_all_product_gallery_images_ajax" />
        <?php wp_nonce_field('awca_dl_all_product_gallery_images_ajax_nonce', 'awca_dl_all_product_gallery_images_ajax_field'); ?>
        <div class="stepper_button_container">
            <button type="submit" class="plugin_activation_button stepper_btn" id="next_stepper_btn plugin_activation_button"
                <?php echo get_transient('awca_dl_all_product_gallery_images_lock') ? 'disabled': '';?>>
                <span>دانلود تصاویر گالری محصولات انار</span>
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>
        </div>
        <p style="text-align:center;color:#E11C47FF;">
            هشدار : تعداد تصاویر گالری زیاد است و ممکن است فضای زیادی از هاست شما را اشغال کند.
        </p>
        <p style="text-align:center;">می توانید فقط تصاویر گالری محصول مورد نظر خود را از <strong>صفحه ویرایش محصول</strong> دانلود کنید</p>
    </form>

    <?php

    if (get_transient('awca_dl_all_product_gallery_images_lock')) {
        echo('<p style="text-align:center;color:#E11C47FF;">دانلود تصاویر در پس زمینه در حال اجرا است، صبر کنید تا تمام شود.</p>');
    }

    ?>
</div>
