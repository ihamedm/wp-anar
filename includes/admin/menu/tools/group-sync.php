<div class="wrapper anar-tools-wrapper anar-tool">

    <div class="access-menu">
        <span class="access-menu-toggle"><?php echo get_anar_icon('dots-vertical', 24);  ?></span>
        <ul>
            <li>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=product&anar_deprecated=true'));?>" target="_blank">محصولات منسوخ شده</a>
                <small>محصولاتی که اخیر از پنل انار شما حذف شده اند</small>
            </li>
            <li>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=product&sync=late&hours_ago=1'));?>" target="_blank">محصولات آپدیت نشده</a>
                <small>محصولاتی که یک ساعت اخیر آپدیت نشده اند</small>
            </li>
            <li>
                <span id="clear-sync-times-btn" style="cursor: pointer">بروزرسانی اجباری</span>
                <small>بروزرسانی کل محصولات زودتر از برنامه</small>
            </li>


        </ul>
    </div>

    <h2 class="awca_plugin_titles">بروزرسانی <span>قیمت</span> و <span>موجودی</span> محصولات</h2>
    <div style="display: none">
    <p class="awca_plugin_subTitles" style="text-align:center">
        با استفاده از دکمه زیر میتوانید قیمت و موجودی محصولاتی که ۱۰ دقیقه اخیر در انار تغییرات موجودی و قیمت داشتن رو به صورت دستی آپدیت کنید.
        <br>
        البته به صورت خودکار <strong>هر دو دقیقه یکبار</strong> انجام می‌شود.
    </p>
    <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" class="plugin_sync_form" id="plugin_sync_form">
        <input type="hidden" name="action" value="awca_sync_products_price_and_stocks" />
        <?php wp_nonce_field('awca_sync_products_price_and_stocks_nonce', 'awca_sync_products_price_and_stocks_field'); ?>
        <div class="stepper_button_container">
            <button type="submit" class="awca-btn awca-success-btn " id="next_stepper_btn plugin_activation_button"
                <?php echo get_transient('awca_sync_all_products_lock') ? 'disabled': '';?>>
                بروزرسانی دستی                           <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>

        </div>
        <div class="" style="display: flex; flex-direction: column; align-items: center; margin:-16px 0 32px 0; display: none">
            <a href="#" class="toggle_show_hide" data-id="advanced_settings">تنظیمات پیشرفته</a>

            <div id="advanced_settings" style="display: none; background: #f9f0c3; padding: 10px 16px; border-radius: 10px; margin: 16px auto">
                <p class="awca-form-control" style="text-align: center">
                    <input type="checkbox" id="full_sync" name="full_sync">
                    <label for="full_sync">همگام سازی برای کل محصولات انجام شود.</label>
                <p class="description" style="color: orangered">بصورت پیش فرض فقط محصولاتی که به تازگی تغییر موجودی یا قیمت داشته اند بروزرسانی می شود.</p>
                </p>
            </div>
        </div>
    </form>

    </div>

    <?php
    $sync = \Anar\Sync::get_instance();
    $last_sync_time = $sync->getLastSyncTime();
    $sync->fullSync = true;
    $last_full_sync_time = $sync->getLastSyncTime();
    $since_nex_full_sync_minutes = get_transient('anar_since_next_full_sync');
    $is_full_sync_running = get_transient('awca_full_sync_products_lock');
    $is_partial_sync_running = get_transient('awca_sync_all_products_lock');

    ?>
    <table>
        <thead>
        <tr>
            <th>نوع</th>
            <th>آخرین بار</th>
            <th>بعدی</th>
        </tr>
        </thead>
        <tbody>
        <?php if($last_sync_time):?>
            <tr>
                <td>
                    <strong>بروزرسانی محصولاتی که ۱۰ دقیقه اخیر در انار تغییر موجودی و قیمت داشته اند</strong>
                </td>
                <td>
                    <?php echo mysql2date('j F Y' . ' ساعت ' . 'H:i', $last_sync_time);?>
                </td>
                <td>

                    <?php if($is_partial_sync_running){
                        echo '<span style="color:red">در حال اجرا ...</span>';
                    }else{
                        echo '<span style="color:green">هر ۲ دقیقه یکبار</span>';
                    }?>

                </td>
            </tr>
        <?php endif?>
        <?php if($last_full_sync_time && false):?>
            <tr>
                <td>
                    <strong>همگام سازی کل محصولات</strong>
                </td>
                <td>
                    <?php echo mysql2date('j F Y' . ' ساعت ' . 'H:i', $last_full_sync_time);?>
                </td>
                <td>
                    <?php
                    if($is_full_sync_running){
                        echo '<span style="color:red">در حال اجرا ...</span>';
                    }else{
                        echo $since_nex_full_sync_minutes ? sprintf('<span style="text-align:center; color:green"><strong>%s دقیقه دیگر</strong> تا همگام سازی بعدی</span>', $since_nex_full_sync_minutes) :  '-';
                    }
                    ?>
                </td>
            </tr>
        <?php endif?>
        </tbody>
    </table>

    <?php
    if(ANAR_IS_ENABLE_OPTIONAL_SYNC_PRICE == 'yes'){?>
        <p style="text-align:center;color: #8e0ec7;background: #f0e6fe;padding: 11px 0;border-radius: 12px;">
            همگام سازی قیمت ها غیر فعال شده است.
            <a href="<?php echo admin_url('admin.php?page=tools&tab=features&anar_optional_price_sync=no');?>">فعال کن</a>
        </p>
    <?php } ?>


    <div class="tool-footer">
        <?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/mini-tool-forced.php';?>
        <?php //include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/mini-tool-outdated.php';?>
        <?php //include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/mini-tool-deprecated.php';?>
    </div>

</div>
