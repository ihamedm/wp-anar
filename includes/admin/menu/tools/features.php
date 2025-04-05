<?php
// Get saved options

$activate_anar_order_feat = get_option('anar_conf_feat__create_orders', 'yes');
$activate_anar_optional_price_sync = get_option('anar_conf_feat__optional_price_sync', 'no');
$anar_log_level = get_option('anar_log_level', 'info');
$anar_full_sync_schedule = get_option('anar_full_sync_schedule', 5);

// Handle form submission
if (isset($_POST['save_anar_settings'])) {
    // Sanitize and save the values
    $activate_anar_order_feat = $_POST['anar_conf_feat__create_orders'] ?? 'yes';
    $anar_log_level = $_POST['anar_log_level'] ?? 'info';
    $anar_full_sync_schedule = intval($_POST['anar_full_sync_schedule']);


    update_option('anar_conf_feat__create_orders', $activate_anar_order_feat);
    update_option('anar_log_level', $anar_log_level);
    update_option('anar_full_sync_schedule', $anar_full_sync_schedule);

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

<h2>فعال/غیرفعال سازی ویژگی ها</h2>
<h4 style="color:red">در صورتی که از عملکرد هر کدام از آپشن های زیر مطمئن نیستید تنظیمات پیشفرض را تغییر ندهید!</h4>

<form method="post">
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

        <tr>
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

        <tr>
            <th><label for="anar_full_sync_schedule">همگام سازی قیمت و موجودی هر</label></th>
            <td><input type="number" name="anar_full_sync_schedule" id="anar_full_sync_schedule" value="<?php echo esc_attr($anar_full_sync_schedule); ?>" class="small-text" min="5"> دقیقه</td>
        </tr>

    </table>
    <p>
        <input type="submit" name="save_anar_settings" value="ذخیره تنظیمات" class="button button-primary">
    </p>
</form>