<div class="anar-tools-wrapper">
    <h2 class="awca_plugin_titles">دانلود تصاویر شاخص محصولات انار</h2>
    <p class="awca_plugin_subTitles">
    </p>

    <form method="post" id="anar-dl-products-thumbnail">
        <?php wp_nonce_field('anar_dl_products_thumbnail_ajax_nonce', 'security_nonce'); ?>
        <div class="stepper_button_container">
            <button class="awca-btn awca-success-btn submit-button" id="anar_dl_products_thumbnail">
                <span>دانلود تصاویر شاخص</span>
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>
        </div>

        <div class="anar-batch-messages"></div>
        <div class="anar-batch-progress"><span class="bar"></span></div>
    </form>

</div>
