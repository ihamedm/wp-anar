
<div class="wrapper">
<div class="stepContent active" id="stepContent2">

    <?php
    if(!isset($categories_response) || awca_check_expiration_by_db_time($categories_response['created_at'] ?? null)):?>

    <div id="refresh_anar_data">
        <h1 class='awca_plugin_titles'>بروزرسانی اطلاعات</h1>
        <p class="awca_plugin_subTitles">
            در حال دریافت اطلاعات مورد نیاز برای ساخت محصولات انار
        </p>

        <ul class="">
            <li class="data-item categories">
                <div class="status-icon">
                    <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                        <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                    </svg>

                    <svg class="done-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="green" >
                      <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <span>دریافت دسته بندی های محصولات انار</span>
            </li>

            <li class="data-item attributes">
                <div class="status-icon">
                    <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                        <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                    </svg>

                    <svg class="done-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="green" >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <span>دریافت ویژگی های محصولات انار</span>
            </li>
        </ul>
    </div>


    <?php else:?>

    <h1 class='awca_plugin_titles'>لیست محصولات انار</h1>
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
            <div id="pagination-numbers">
                <li class="active">1</li>
            </div>
        </div>


        <div class="stepper_button_container">
            <?php
            submit_button('مرحله بعد', 'plugin_activation_button stepper_btn', 'plugin_activation_button', false, array(
                'id' => 'next_stepper_btn',
                'data-next-step' => '2'
            )); ?>
        </div>

        <div class="awca-alert-area"></div>

    <?php endif;?>

</div>
