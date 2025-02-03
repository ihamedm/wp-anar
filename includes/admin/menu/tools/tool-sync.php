<div class="wrapper tools-wrapper">
    <h2 class="awca_plugin_titles">همگام سازی <span>قیمت</span> و <span>موجودی</span> محصولات</h2>
    <p class="awca_plugin_subTitles">
        با استفاده از دکمه زیر میتوانید قیمت و موجودی محصولات رو به صورت دستی آپدیت کنید.
        این کار به صورت خودکار هر پنج دقیقه یکبار انجام میشود.
    </p>
    <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" class="plugin_sync_form" id="plugin_sync_form">
        <input type="hidden" name="action" value="awca_sync_products_price_and_stocks" />
        <?php wp_nonce_field('awca_sync_products_price_and_stocks_nonce', 'awca_sync_products_price_and_stocks_field'); ?>
        <div class="stepper_button_container">
            <button type="submit" class="plugin_activation_button stepper_btn" id="next_stepper_btn plugin_activation_button"
                <?php echo get_transient('awca_sync_all_products_lock') ? 'disabled': '';?>>
                همگام سازی                            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>
        </div>
    </form>


    <?php
    $last_sync_time = get_option('awca_last_sync_time');
    if($last_sync_time){
        printf('<p style="text-align:center">آخرین همگام سازی : %s</p>', mysql2date('j F Y' . ' ساعت ' . 'H:i', $last_sync_time));
    }
    ?>

    <?php

    if (get_transient('awca_sync_all_products_lock')) {
        echo('<p style="text-align:center;color:#E11C47FF;">یک همگام سازی در پس زمینه در حال اجرا است، صبر کنید تا تمام شود.</p>');
    }else{
        echo('<p style="text-align:center;color:#E11C47FF;">هر ۲ دقیقه یکبار بصورت اتوماتیک انجام می شود.</p>');
    }

    ?>
</div>
