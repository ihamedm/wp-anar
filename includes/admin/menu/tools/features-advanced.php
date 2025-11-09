<?php
// Get saved options
$new_api_validate_feat = get_option('anar_conf_feat__api_validate', 'new');
$anar_import_type = get_option('anar_conf_feat__import_type', 'pro');
$activate_anar_optional_price_sync = get_option('anar_conf_feat__optional_price_sync', 'no');
$anar_log_level = get_option('anar_log_level', 'info');
$anar_regular_sync_update_since = get_option('anar_regular_sync_update_since', 10);
$anar_sync_outdated_batch_size = get_option('anar_sync_outdated_batch_size', 100);

// Handle form submission
if (isset($_POST['save_anar_advanced_settings'])) {
    // Sanitize and save the values
    $new_api_validate_feat = $_POST['anar_conf_feat__api_validate'] ?? 'new';
    $anar_import_type = $_POST['anar_conf_feat__import_type'] ?? 'pro';
    $anar_log_level = $_POST['anar_log_level'] ?? 'info';
    $anar_regular_sync_update_since = intval($_POST['anar_regular_sync_update_since']);
    $anar_sync_outdated_batch_size = intval($_POST['anar_sync_outdated_batch_size']);

    update_option('anar_conf_feat__import_type', $anar_import_type);
    update_option('anar_conf_feat__api_validate', $new_api_validate_feat);
    update_option('anar_log_level', $anar_log_level);
    update_option('anar_regular_sync_update_since', $anar_regular_sync_update_since);
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

    <h2>تنظیمات توسعه دهنده</h2>
    <p class="anar-alert anar-alert-warning">تغییر این تنظیمات توسط شما توصیه نمی شود و ممکن است باعث بروز اشکالاتی در عملکرد پلاگین شود!. این گزینه ها صرفا برای پشتیبانی فنی اضافه شده اند.</p>


    <table class="form-table">
        <tr>
            <th><label for="anar_sync_outdated_batch_size">تعداد محصول در هر جاب</label></th>
            <td><input type="number" name="anar_sync_outdated_batch_size" id="anar_sync_outdated_batch_size" value="<?php echo esc_attr($anar_sync_outdated_batch_size); ?>" class="small-text" min="5">
                محصول در هر جاب بروزرسانی روزانه آپدیت شود
            </td>
        </tr>

        <tr style="display: none;">
            <th><label for="anar_regular_sync_update_since">regular sync [updateSince] - minutes</label></th>
            <td>
                <label>
                    <input type="number" name="anar_regular_sync_update_since" id="anar_regular_sync_update_since" value="<?php echo esc_attr($anar_regular_sync_update_since); ?>" class="small-text" min="1"> minutes
                </label>
                <p class="description"><strong>updateSync</strong> param on RegularSync strategy, default 10 minutes</p>
            </td>

        </tr>

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

        <tr>
            <th><label for="anar_conf_feat__import_type">روش درون ریزی محصولات</label></th>
            <td>
                <label>
                    <select name="anar_conf_feat__import_type" id="anar_conf_feat__import_type">
                        <option value="normal" <?php selected($anar_import_type, 'normal'); ?>>عادی</option>
                        <option value="slow" <?php selected($anar_import_type, 'slow'); ?>>آهسته</option>
                        <option value="pro" <?php selected($anar_import_type, 'pro'); ?>>پیشرفته</option>
                    </select>
                </label>
                <p class="description">
                    <strong>عادی:</strong> روش استاندارد درون ریزی محصولات<br>
                    <strong>آهسته:</strong> برای هاست‌هایی که منابع کمتری دارند (۳۰ محصول در دقیقه)<br>
                    <strong>پیشرفته:</strong> روش یکپارچه با جدول مخصوص برای جلوگیری از تکرار محصولات (عملکرد بهتر)
                </p>
            </td>
        </tr>

    </table>

    <p>
        <input type="submit" name="save_anar_advanced_settings" value="ذخیره تنظیمات" class="button button-primary">
    </p>
</form>

