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

        <?php
        $save_product_lock = get_option('awca_product_save_lock')
        ?>

        <button id="get-save-products-btn" class="awca_sync_btn" <?php echo $save_product_lock ? 'disabled' : '';?> >
            ذخیره نهایی
            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
            </svg>
        </button>

        <?php if($save_product_lock):?>

            <div class="awca_step-ajax-result success">محصولات در پس زمینه در حال ساخته شدن هستند. می توانید این صفحه را ببندید تا افزودن محصولات به اتمام برسد.</div>

        <?php endif;?>

        <div class="awca_step-ajax-result"></div>
        <div class="awca_step-ajax-result-progress"><span class="bar"></span></div>
    </div>

</div>