<?php
// Get saved options

$activate_anar_order_feat = get_option('anar_conf_feat__create_orders', 'yes');
$activate_anar_optional_price_sync = get_option('anar_conf_feat__optional_price_sync', 'no');
$anar_debug = get_option('anar_debug', 'no');

// Handle form submission
if (isset($_POST['save_anar_settings'])) {
    // Sanitize and save the values
    $activate_anar_order_feat = $_POST['anar_conf_feat__create_orders'] ?? 'yes';
    $anar_debug = $_POST['anar_debug'] ?? 'no';

    update_option('anar_conf_feat__create_orders', $activate_anar_order_feat);
    update_option('anar_debug', $anar_debug);

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

<form method="post">
    <fieldset>
        <h3 style="color:red">توجه</h3>
        <p>ویژگی های آزمایشی ممکن است در بعضی وب سایت ها به درستی کار نکند. </p>
    </fieldset>
    <table class="form-table">
        <tr>
            <th><label for="anar_debug">دیباگ انار</label></th>
            <td>
                <label>
                    <select name="anar_debug" id="anar_debug">
                        <option value="no" <?php selected($anar_debug, 'no'); ?>>غیرفعال</option>
                        <option value="yes" <?php selected($anar_debug, 'yes'); ?>>فعال</option>
                    </select>
                </label>
                <p class="description">فعال کردن این گزینه لاگ های دقیق تری ایجاد خواهد کرد.</p>
            </td>
        </tr>

        <tr>
            <th><label for="anar_conf_feat__create_orders">ثبت سفارش انار (آزمایشی)</label></th>
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

    </table>
    <p>
        <input type="submit" name="save_anar_settings" value="ذخیره تنظیمات" class="button button-primary">
    </p>
</form>