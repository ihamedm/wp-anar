<?php
// Get saved shipping options
$activate_anar_shipping = get_option('anar_conf_feat__anar_shipping', 'yes');
$activate_anar_ship_to_stock = get_option('anar_conf_feat__ship_to_stock', 'no');

// Multiple package options (when shipping is disabled)
$show_multi_package_alert = get_option('anar_show_multi_package_alert', 'yes');
$multi_package_alert_text = get_option('anar_multi_package_alert_text', 'کالاهای انتخابی شما از چند انبار مختلف ارسال می شوند.');
$multi_package_fee_method = get_option('anar_multi_package_fee_method', 'multiplier'); // multiplier or fixed
$multi_package_fee_amount = get_option('anar_multi_package_fee', 50000);
$shipping_multiplier = get_option('anar_shipping_multiplier', 1);
$multi_package_fee_free_condition = get_option('anar_multi_package_fee_free_condition', 0);

// Determine if multi-package fee is enabled by checking internal flags
$enable_shipping_multiplier_internal = get_option('anar_enable_shipping_multiplier', 'no');
$enable_multi_package_fee_internal = get_option('anar_enable_multi_package_fee', 'no');
$enable_multi_package_fee = ($enable_shipping_multiplier_internal === 'yes' || $enable_multi_package_fee_internal === 'yes') ? 'yes' : 'no';

// Handle form submission
if (isset($_POST['save_anar_shipping_settings'])) {
    // Sanitize and save the values - checkboxes will be 'on' when checked, absent when unchecked
    $activate_anar_shipping = isset($_POST['anar_conf_feat__anar_shipping']) ? 'yes' : 'no';
    $activate_anar_ship_to_stock = isset($_POST['anar_conf_feat__ship_to_stock']) ? 'yes' : 'no';

    update_option('anar_conf_feat__anar_shipping', $activate_anar_shipping);
    update_option('anar_conf_feat__ship_to_stock', $activate_anar_ship_to_stock);

    // Only save multiple package options if Anar shipping is disabled
    // (when enabled, these fields are not in the form)
    if ($activate_anar_shipping === 'no') {
        $show_multi_package_alert = isset($_POST['anar_show_multi_package_alert']) ? 'yes' : 'no';
        $multi_package_alert_text = sanitize_text_field($_POST['anar_multi_package_alert_text'] ?? '');
        $enable_multi_package_fee = isset($_POST['anar_enable_multi_package_fee']) ? 'yes' : 'no';
        $multi_package_fee_method = sanitize_text_field($_POST['anar_multi_package_fee_method'] ?? 'multiplier');
        $multi_package_fee_amount = intval($_POST['anar_multi_package_fee'] ?? 0);
        $shipping_multiplier = floatval($_POST['anar_shipping_multiplier'] ?? 1);
        $multi_package_fee_free_condition = intval($_POST['anar_multi_package_fee_free_condition'] ?? 0);

        update_option('anar_show_multi_package_alert', $show_multi_package_alert);
        update_option('anar_multi_package_alert_text', $multi_package_alert_text);
        update_option('anar_multi_package_fee_method', $multi_package_fee_method);
        update_option('anar_multi_package_fee', $multi_package_fee_amount);
        update_option('anar_shipping_multiplier', $shipping_multiplier);
        update_option('anar_multi_package_fee_free_condition', $multi_package_fee_free_condition);
        
        // Update the internal flags that control which hook is active
        // These are used by Checkout.php constructor to determine which method to hook
        if ($enable_multi_package_fee === 'yes') {
            if ($multi_package_fee_method === 'multiplier') {
                update_option('anar_enable_shipping_multiplier', 'yes');
                update_option('anar_enable_multi_package_fee', 'no'); // Disable fixed fee hook
            } else {
                update_option('anar_enable_shipping_multiplier', 'no');
                update_option('anar_enable_multi_package_fee', 'yes'); // Enable fixed fee hook
            }
        } else {
            update_option('anar_enable_shipping_multiplier', 'no');
            update_option('anar_enable_multi_package_fee', 'no');
        }
    }

    // Success message
    echo '<div class="updated"><p>تنظیمات حمل و نقل ذخیره شد.</p></div>';
}
?>
<br class="clear">

<form method="post">

    <h2>تنظیمات حمل و نقل انار</h2>
    <p class="anar-alert anar-alert-warning">توجه : <strong>غیرفعال کردن حمل و نقل انار</strong> می تواند روی هزینه های حمل و نقلی که از کاربر دریافت می شود تاثیر بگذارد پس لطفا با دقت توضیحات هر ویژگی را بخوانید و درصورتی که با سیستم حمل و نقل ووکامرس آشنایی ندارید این تنظیمات را تغییر ندهید.</p>

    <table class="form-table">
        <tr>
            <th><label for="anar_conf_feat__anar_shipping">حمل و نقل انار</label></th>
            <td>
                <div class="awca-switch-wrapper">
                    <label class="awca-switch">
                        <input type="checkbox" name="anar_conf_feat__anar_shipping" id="anar_conf_feat__anar_shipping" 
                               <?php checked($activate_anar_shipping, 'yes'); ?>>
                        <span class="awca-switch-slider"></span>
                    </label>
                    <span class="awca-switch-label">
                        <?php echo $activate_anar_shipping === 'yes' ? 'فعال' : 'غیرفعال'; ?>
                    </span>
                </div>
                <p class="description">
                    توجه : غیرفعال کردن این ویژگی متدهای ارسال انار در صفحه ثبت سفارش را غیر فعال می کنید و شما باید متدهای حمل و نقل اختصاصی خود را تعریف کنید و هزینه ارسال را از کاربر دریافت کنید.
                </p>
            </td>
        </tr>

        <tr style="display:none">
            <th><label for="anar_conf_feat__ship_to_stock">تحویل درب انار (آزمایشی)</label></th>
            <td>
                <div class="awca-switch-wrapper">
                    <label class="awca-switch">
                        <input type="checkbox" name="anar_conf_feat__ship_to_stock" id="anar_conf_feat__ship_to_stock" 
                               <?php checked($activate_anar_ship_to_stock, 'yes'); ?>>
                        <span class="awca-switch-slider"></span>
                    </label>
                    <span class="awca-switch-label">
                        <?php echo $activate_anar_ship_to_stock === 'yes' ? 'فعال' : 'غیرفعال'; ?>
                    </span>
                </div>
                <p class="description">
                    توجه : فعالسازی این ویژگی متدهای ارسال انار را غیر فعال می کنید و شما باید متدهای حمل و نقل اختصاصی خود را تعریف کنید و هزینه ارسال را از کاربر دریافت کنید.
                </p>
            </td>
        </tr>

    </table>

    <?php if ($activate_anar_shipping === 'no') : ?>
        <h2>تنظیمات چند مرسوله‌ای</h2>
        <p class="description">این تنظیمات زمانی که محصولات از چند انبار مختلف ارسال شوند به شما کمک می‌کند تا مشتریان را مطلع کنید و هزینه ارسال مناسب دریافت کنید.</p>

        <table class="form-table">
            <tr>
                <th><label for="anar_show_multi_package_alert">نمایش هشدار چند مرسوله‌ای</label></th>
                <td>
                    <div class="awca-switch-wrapper">
                        <label class="awca-switch">
                            <input type="checkbox" name="anar_show_multi_package_alert" id="anar_show_multi_package_alert" 
                                   <?php checked($show_multi_package_alert, 'yes'); ?>>
                            <span class="awca-switch-slider"></span>
                        </label>
                        <span class="awca-switch-label">
                            <?php echo $show_multi_package_alert === 'yes' ? 'فعال' : 'غیرفعال'; ?>
                        </span>
                    </div>
                    <p class="description">
                        زمانی که محصولات از چند انبار مختلف باشند، یک پیام اطلاع‌رسانی در صفحه سبد خرید و تسویه حساب نمایش داده می‌شود.
                    </p>
                </td>
            </tr>

            <tr>
                <th><label for="anar_multi_package_alert_text">متن هشدار چند مرسوله‌ای</label></th>
                <td>
                    <input type="text" name="anar_multi_package_alert_text" id="anar_multi_package_alert_text" 
                           value="<?php echo esc_attr($multi_package_alert_text); ?>" 
                           class="regular-text" dir="rtl">
                    <p class="description">
                        متن پیامی که به مشتری نمایش داده می‌شود. می‌توانید از متغیر <code>%d</code> برای نمایش تعداد انبارها استفاده کنید.<br>
                        مثال: "سفارش شما از %d انبار مختلف ارسال خواهد شد."
                    </p>
                </td>
            </tr>

            <tr>
                <th><label for="anar_enable_multi_package_fee">هزینه اضافی چند مرسوله‌ای</label></th>
                <td>
                    <div class="awca-switch-wrapper">
                        <label class="awca-switch">
                            <input type="checkbox" name="anar_enable_multi_package_fee" id="anar_enable_multi_package_fee" 
                                   <?php checked($enable_multi_package_fee, 'yes'); ?>>
                            <span class="awca-switch-slider"></span>
                        </label>
                        <span class="awca-switch-label">
                            <?php echo $enable_multi_package_fee === 'yes' ? 'فعال' : 'غیرفعال'; ?>
                        </span>
                    </div>
                    <p class="description">
                        برای هر انبار اضافی (بعد از اولین مرسوله)، هزینه ارسال اضافه می‌شود.<br>
                        با فعال کردن این گزینه می‌توانید روش محاسبه هزینه را انتخاب کنید.
                    </p>

                    <div id="awca_multi_package_wrapper" class="awca-multi-package-wrapper" style="display: none; margin-top: 20px;">
                        
                        <!-- Method Selection Tabs -->
                        <div class="awca-fee-method-tabs">
                            <label>
                                <input type="radio" name="anar_multi_package_fee_method" value="multiplier" 
                                       <?php checked($multi_package_fee_method, 'multiplier'); ?>>
                                <span>ضریب به ازای هر انبار</span>
                            </label>
                            <label>
                                <input type="radio" name="anar_multi_package_fee_method" value="fixed" 
                                       <?php checked($multi_package_fee_method, 'fixed'); ?>>
                                <span>هزینه ثابت به ازای هر انبار</span>
                            </label>
                        </div>

                        <!-- Multiplier Method Content -->
                        <div id="awca_multiplier_method_content" class="awca-fee-method-content">
                            <h4>ضریب هزینه حمل و نقل به ازای هر مرسوله اضافی</h4>
                            
                            <div class="awca-fee-option-field">
                                <label for="anar_shipping_multiplier">ضریب به ازای هر انبار اضافی</label>
                                <input type="number" name="anar_shipping_multiplier" id="anar_shipping_multiplier" 
                                       value="<?php echo esc_attr($shipping_multiplier); ?>" 
                                       min="0" max="10" step="0.1">
                                <p class="description">
                                    این عدد برای هر انبار اضافی به هزینه حمل و نقل پایه اضافه می‌شود.<br>
                                    <strong>فرمول محاسبه:</strong> <code>نرخ نهایی = نرخ پایه × (۱ + (تعداد انبارهای اضافی × ضریب))</code><br><br>
                                    <strong>مثال‌های کاربردی:</strong><br>
                                    • <strong>ضریب ۱:</strong> هر انبار اضافی، یک برابر نرخ پایه به آن اضافه می‌شود<br>
                                    &nbsp;&nbsp;→ ۳ انبار با نرخ پایه ۳۰,۰۰۰: ۳۰,۰۰۰ × (۱ + ۲×۱) = ۳۰,۰۰۰ × ۳ = <strong>۹۰,۰۰۰ تومان</strong><br><br>
                                    • <strong>ضریب ۱.۵:</strong> هر انبار اضافی، یک و نیم برابر نرخ پایه به آن اضافه می‌شود<br>
                                    &nbsp;&nbsp;→ ۳ انبار با نرخ پایه ۳۰,۰۰۰: ۳۰,۰۰۰ × (۱ + ۲×۱.۵) = ۳۰,۰۰۰ × ۴ = <strong>۱۲۰,۰۰۰ تومان</strong><br><br>
                                    • <strong>ضریب ۰.۵:</strong> هر انبار اضافی، نصف نرخ پایه به آن اضافه می‌شود<br>
                                    &nbsp;&nbsp;→ ۳ انبار با نرخ پایه ۳۰,۰۰۰: ۳۰,۰۰۰ × (۱ + ۲×۰.۵) = ۳۰,۰۰۰ × ۲ = <strong>۶۰,۰۰۰ تومان</strong>
                                </p>
                            </div>
                        </div>

                        <!-- Fixed Fee Method Content -->
                        <div id="awca_fixed_fee_method_content" class="awca-fee-method-content">
                            <h4>هزینه ثابت به ازای هر انبار اضافی</h4>
                            
                            <div class="awca-fee-option-field">
                                <label for="anar_multi_package_fee">مبلغ ثابت برای هر انبار اضافی</label>
                                <input type="number" name="anar_multi_package_fee" id="anar_multi_package_fee" 
                                       value="<?php echo esc_attr($multi_package_fee_amount); ?>" 
                                       min="0" step="1000">
                                <span><?php echo get_woocommerce_currency_symbol(); ?></span>
                                <p class="description">
                                    مبلغ ثابتی که برای هر انبار اضافی (بعد از اولین انبار) به صورت مستقل به سبد خرید اضافه می‌شود.<br>
                                    این هزینه مستقل از نرخ حمل و نقل انتخابی مشتری است.<br><br>
                                    <strong>مثال‌های کاربردی:</strong><br>
                                    • اگر <strong>۵۰,۰۰۰ تومان</strong> تنظیم کنید و مشتری از <strong>۳ انبار</strong> خرید کند:<br>
                                    &nbsp;&nbsp;→ ۲ انبار اضافی × ۵۰,۰۰۰ = <strong>۱۰۰,۰۰۰ تومان</strong> به سبد خرید اضافه می‌شود<br><br>
                                    • اگر <strong>۳۰,۰۰۰ تومان</strong> تنظیم کنید و مشتری از <strong>۴ انبار</strong> خرید کند:<br>
                                    &nbsp;&nbsp;→ ۳ انبار اضافی × ۳۰,۰۰۰ = <strong>۹۰,۰۰۰ تومان</strong> به سبد خرید اضافه می‌شود
                                </p>
                            </div>
                        </div>

                        <!-- Free Shipping Condition (Common for both methods) -->
                        <div class="awca-fee-option-field" style="border-top: 1px solid #e5e5e5; padding-top: 20px; margin-top: 0;">
                            <label for="anar_multi_package_fee_free_condition">شرط رایگان شدن هزینه اضافی</label>
                            <input type="number" name="anar_multi_package_fee_free_condition" id="anar_multi_package_fee_free_condition" 
                                   value="<?php echo esc_attr($multi_package_fee_free_condition); ?>" 
                                   min="0" step="10000">
                            <span><?php echo get_woocommerce_currency_symbol(); ?></span>
                            <p class="description">
                                اگر مجموع سفارش بیشتر از این مبلغ باشد، هزینه چند مرسوله‌ای اضافه نمی‌شود (رایگان می‌شود).<br>
                                این شرط برای هر دو روش (ضریب و هزینه ثابت) اعمال می‌شود.<br>
                                برای غیرفعال کردن این شرط، مقدار را <strong>0</strong> قرار دهید.<br>
                                <strong>مثال:</strong> اگر ۱,۰۰۰,۰۰۰ تومان تنظیم کنید، برای سفارش‌های بالای یک میلیون تومان، هزینه اضافی دریافت نمی‌شود.
                            </p>
                        </div>

                    </div>
                </td>
            </tr>

        </table>
    <?php endif; ?>

    <p>
        <input type="submit" name="save_anar_shipping_settings" value="ذخیره تنظیمات" class="button button-primary">
    </p>
</form>

