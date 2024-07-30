<?php
//$awca_products = awca_get_stored_response('product');
?>
<div class="wrapper">
<div class="stepContent active" id="stepContent2">

    <h1 class='awca_plugin_titles'><?php echo esc_html__('لیست محصولات انار', 'anar-360'); ?></h1>
    <p class="awca_plugin_subTitles">
        در این مرحله محصولاتی که قراره توی وبسایت ووکامرسیت به فروش برسن رو می‌بینی :))
        این محصولات همون محصولاتی هستن که به پنل انار خودت اضافه کردی. در صورتی که مایل بودی محصولات بیشتری داخل وبسایت خودت به فروش برسونی یا بعضی از محصولات رو حذف کنی، از داخل اکانت انارت این کار رو انجام بده.
    </p>


        <table class="awca_product_list_table">
            <thead>
            <tr>
                <th>ردیف</th>
                <th>تصویر</th>
                <th>نام محصول</th>
                <th>توضیحات</th>
                <th>ویژگی‌ها</th>
                <th>قیمت همکاری</th>
                <th>قیمت فروش شما</th>
                <th>اطلاعات ارسال</th>
            </tr>
            </thead>
            <tbody class="list">
            <?php for ($i = 0; $i < 25; $i++): ?>
                <tr class="placeholder">
                    <td>—</td>
                    <td><div class="placeholder-image"></div></td>
                    <td><div class="placeholder-text"></div></td>
                    <td><div class="placeholder-text"></div></td>
                    <td><div class="placeholder-text"></div></td>
                    <td><div class="placeholder-text"></div></td>
                    <td><div class="placeholder-text"></div></td>
                    <td><div class="placeholder-text"></div></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>

        <div class="pagination listPage">
<!--            <li id="prev-page" disabled>قبلی</li>-->
            <div id="pagination-numbers">
                <li class="active">1</li>
            </div>
<!--            <li id="next-page">بعدی</li>-->
        </div>


        <div class="stepper_button_container">
            <?php
            submit_button('مرحله بعد', 'plugin_activation_button stepper_btn', 'plugin_activation_button', false, array(
                'id' => 'next_stepper_btn',
                'onClick' => 'awca_move_to_step(2)'
            )); ?>
        </div>

</div>
