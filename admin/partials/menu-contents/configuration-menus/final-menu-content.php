<?php
?>

<div class="stepContent" id="stepContent5">
    <h1 class='awca_plugin_titles'><?php echo esc_html__('ساخت محصولات', 'anar-360'); ?></h1>

    <p class="awca_plugin_subTitles">
در این مرحله محصولات از انار دریافت می شود.
        بعد از دریافت پیغام موفقیت می توانید این صفحه را ببندید.
        ما در پس زمینه شروع به ساخت محصولات می کنیم.
    </p>


    <div class="awca-save-products-wrapper" style="display:flex;align-items:center;justify-content:center;flex-direction: column;">

        <button id="get-save-products-btn" class="awca_sync_btn">
            ذخیره نهایی
            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
            </svg>
        </button>

        <div class="awca_step-ajax-result"></div>
        <div class="awca_step-ajax-result-progress"><span class="bar"></span></div>
    </div>

</div>