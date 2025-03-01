<div class="wrapper tools-wrapper">
    <h2 class="awca_plugin_titles">همگام سازی <span>قیمت</span> و <span>موجودی</span> محصولات</h2>
    <p class="awca_plugin_subTitles" style="text-align:center">
        با استفاده از دکمه زیر میتوانید قیمت و موجودی محصولاتی که اخیرا (۱۰ دقیقه اخیر) در انار تغییر داشتن رو به صورت دستی آپدیت کنید.
        این کار به صورت خودکار هر دو دقیقه یکبار انجام میشود.
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
        <div class="" style="display: flex; flex-direction: column; align-items: center; margin-bottom: 32px">
            <a href="#" class="toggle_show_hide" data-id="advanced_settings">تنظیمات پیشرفته</a>

            <div id="advanced_settings" style="display: none">
                <p class="awca-form-control">
                    <input type="checkbox" id="full_sync" name="full_sync">
                    <label for="full_sync">همگام سازی برای کل محصولات انجام شود.</label>
                </p>
            </div>
        </div>
    </form>


    <?php
    $sync = new \Anar\Sync();
    $last_sync_time = $sync->getLastSyncTime();
    $sync->fullSync = true;
    $last_full_sync_time = $sync->getLastSyncTime();

    if($last_sync_time){
        printf('<p style="text-align:center">آخرین همگام سازی قیمت و موجودی محصولاتی که طی ۱۰ دقیقه گذشته در انار تغییر قیمت یا موجودی داشته اند<br><strong>%s</strong></p>', mysql2date('j F Y' . ' ساعت ' . 'H:i', $last_full_sync_time));
    }

    if($last_full_sync_time){
        printf('<hr><p style="text-align:center">آخرین همگام سازی قیمت و موجودی محصولاتی که طی ۱۰ دقیقه گذشته در انار تغییر قیمت یا موجودی داشته اند<br><strong>%s</strong></p>', mysql2date('j F Y' . ' ساعت ' . 'H:i', $last_full_sync_time));
    }

    ?>

    <?php

    if (get_transient('awca_sync_all_products_lock')) {
        echo('<p style="text-align:center;color:#E11C47FF;">یک همگام سازی در پس زمینه در حال اجرا است، صبر کنید تا تمام شود.</p>');
    }else{
        echo('<p style="text-align:center;color:#E11C47FF;">هر ۲ دقیقه یکبار بصورت اتوماتیک انجام می شود.</p>');
    }

    if(ANAR_IS_ENABLE_OPTIONAL_SYNC_PRICE == 'yes'){?>
        <p style="text-align:center;color: #8e0ec7;background: #f0e6fe;padding: 11px 0;border-radius: 12px;">
            همگام سازی قیمت ها غیر فعال شده است.
            <a href="<?php echo admin_url('admin.php?page=tools&tab=features&anar_optional_price_sync=no');?>">فعال کن</a>
        </p>
    <?php } ?>

</div>
