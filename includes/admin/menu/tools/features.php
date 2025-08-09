<?php
// Get saved options

$activate_anar_order_feat = get_option('anar_conf_feat__create_orders', 'yes');
$new_api_validate_feat = get_option('anar_conf_feat__api_validate', 'new');
$activate_anar_slow_import_feat = get_option('anar_conf_feat__slow_import', 'no');
$activate_anar_optional_price_sync = get_option('anar_conf_feat__optional_price_sync', 'no');
$anar_log_level = get_option('anar_log_level', 'info');
$anar_full_sync_schedule_hours = get_option('anar_full_sync_schedule_hours', 6);
$anar_sync_outdated_batch_size = get_option('anar_sync_outdated_batch_size', 30);

// Handle form submission
if (isset($_POST['save_anar_settings'])) {
    // Sanitize and save the values
    $activate_anar_order_feat = $_POST['anar_conf_feat__create_orders'] ?? 'yes';
    $new_api_validate_feat = $_POST['anar_conf_feat__api_validate'] ?? 'new';
    $activate_anar_slow_import_feat = $_POST['anar_conf_feat__slow_import'] ?? 'no';
    $anar_log_level = $_POST['anar_log_level'] ?? 'info';
    $anar_full_sync_schedule_hours = intval($_POST['anar_full_sync_schedule_hours']);
    $anar_sync_outdated_batch_size = intval($_POST['anar_sync_outdated_batch_size']);


    update_option('anar_conf_feat__slow_import', $activate_anar_slow_import_feat);
    update_option('anar_conf_feat__create_orders', $activate_anar_order_feat);
    update_option('anar_conf_feat__api_validate', $new_api_validate_feat);
    update_option('anar_log_level', $anar_log_level);
    update_option('anar_full_sync_schedule_hours', $anar_full_sync_schedule_hours);
    update_option('anar_sync_outdated_batch_size', $anar_sync_outdated_batch_size);

    // Success message
    echo '<div class="updated"><p>تنظیمات ذخیره شد.</p></div>';

}

// temporary these options not public so trigger only buy query params
if(isset($_GET['anar_optional_price_sync'])){
    update_option('anar_conf_feat__optional_price_sync', $_GET['anar_optional_price_sync']);

    printf('<div class="updated"><p>تنظیمات ذخیره شد. همگام سازی قیمت های انار %s شد.</p></div>',

    ($_GET['anar_optional_price_sync'] == 'yes') ? 'غیرفعال' : 'فعال'
    );
}
?>
<br class="clear">


<form method="post">

    <h2>تنظیمات بروزرسانی قیمت/موجودی</h2>
    <table class="form-table">
        <tr>
            <th><label for="anar_sync_outdated_batch_size">تعداد محصول در هر جاب</label></th>
            <td><input type="number" name="anar_sync_outdated_batch_size" id="anar_sync_outdated_batch_size" value="<?php echo esc_attr($anar_sync_outdated_batch_size); ?>" class="small-text" min="5">
                محصول در هر جاب بروزرسانی روزانه آپدیت شود
            </td>
        </tr>

        <tr style="display: none;">
            <th><label for="anar_full_sync_schedule_hours">بروزرسانی اجباری کل محصولات</label></th>
            <td>
                <label>
                    <input type="number" name="anar_full_sync_schedule_hours" id="anar_full_sync_schedule_hours" value="<?php echo esc_attr($anar_full_sync_schedule_hours); ?>" class="small-text" min="1"> ساعت
                </label>
                <p class="description">این متد بروزرسانی، علاوه بر بروزرسانی های لحظه ایی قیمت و موجودی که هنگام افزودن به سبد خرید توسط مشتری انجام می شود ،کل محصولات را اجبارا بروزرسانی میکند. </p>
                <p class="description" style="background: rgba(255,0,0,0.13); padding: 4px 12px 6px;border-radius: 5px;"><strong style="color:red">توجه</strong> : فقط در صورتی این عدد را کوچکتر از ۶ ساعت تنظیم کنید که هاست شما منابع کافی داشته باشد، چون این پردازش نسبتا سنگین است و مداوم انجام می شود.</p>
            </td>

        </tr>
    </table>

    <br class="clear">
    <hr>

    <h2>تنظیمات توسعه دهنده</h2>
    <p class="anar-alert anar-alert-warning">تغییر این تنظیمات توسط شما توصیه نمی شود و ممکن است باعث بروز اشکالاتی در عملکرد پلاگین شود!</p>

    <table class="form-table">
        <tr>
            <th><label for="anar_log_level">دیباگ انار</label></th>
            <td>
                <label>
                    <select name="anar_log_level" id="anar_log_level">
                        <option value="error" <?php selected($anar_log_level, 'error'); ?>>error</option>
                        <option value="warning" <?php selected($anar_log_level, 'warning'); ?>>warning</option>
                        <option value="info" <?php selected($anar_log_level, 'info'); ?>>info</option>
                        <option value="debug" <?php selected($anar_log_level, 'debug'); ?>>debug</option>
                    </select>
                </label>
                <p class="description">این آپشن صرفا برای دیباگ توسط توسعه دهنده می باشد. لطفا تغییر ندهید.</p>
            </td>
        </tr>

        <tr style="display: none">
            <th><label for="anar_conf_feat__create_orders">ثبت سفارش انار</label></th>
            <td>
                <label>
                    <select name="anar_conf_feat__create_orders" id="anar_conf_feat__create_orders">
                        <option value="no" <?php selected($activate_anar_order_feat, 'no'); ?>>غیرفعال</option>
                        <option value="yes" <?php selected($activate_anar_order_feat, 'yes'); ?>>فعال</option>
                    </select>
                </label>
                <p class="description">در صورت فعال سازی یک دکمه ثبت سفارش در انار در صفحه ویرایش سفارش اضافه خواهد شد که بطور اتوماتیک یک سفارش در انار براش شما ثبت خواهد کرد.</p>
            </td>
        </tr>

        <tr style="display: none">
            <th><label for="anar_conf_feat__api_validate">ارتباط با API انار</label></th>
            <td>
                <label>
                    <select name="anar_conf_feat__api_validate" id="anar_conf_feat__api_validate">
                        <option value="legacy" <?php selected($new_api_validate_feat, 'legacy'); ?>>روش قدیمی</option>
                        <option value="new" <?php selected($new_api_validate_feat, 'new'); ?>>روش جدید</option>
                    </select>
                </label>
                <p class="description">اگر روش جدید برای شما عملکرد درستی ندارد حتما این مورد را با پشتیبانی در میان بگذارید</p>
            </td>
        </tr>

        <tr >
            <th><label for="anar_conf_feat__slow_import">همگام سازی آهسته؟</label></th>
            <td>
                <label>
                    <select name="anar_conf_feat__slow_import" id="anar_conf_feat__slow_import">
                        <option value="no" <?php selected($activate_anar_slow_import_feat, 'no'); ?>>خیر</option>
                        <option value="yes" <?php selected($activate_anar_slow_import_feat, 'yes'); ?>>بله</option>
                    </select>
                </label>
                <p class="description">این گزینه برای هاست هایی که منابع کمتری دارند مناسب است. در هر دقیقه فقط ۳۰ محصول ساخته می شود .</p>
            </td>
        </tr>

    </table>



    <p>
        <input type="submit" name="save_anar_settings" value="ذخیره تنظیمات" class="button button-primary">
    </p>
</form>