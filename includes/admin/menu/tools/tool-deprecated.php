<div class="wrapper tools-wrapper">
    <h2 class="awca_plugin_titles">محصولاتی که اخیرا از انار حذف شده اند</h2>
    <p class="awca_plugin_subTitles">پلاگین انار بطور دایم محصولاتی که به هر دلیل از پنل انار حذف شده اند شناسایی میکند و در وب سایت شما به حالت نامجود تغییر میدهد.</p>
    <p class="awca_plugin_subTitles">با کلیک کردن رو لینک زیر میتوانید این محصولات را ببینید و تعیین تکلیف کنید</p>
    <p class="awca_plugin_subTitles">این محصولات لیبل انار ندارند و دیگر جزو محصولات انار نیستند.</p>


    <?php
    $query_args = array(
        'post_type' => 'product',
        'anar_deprecated' => 'true'
    );

    // Generate the URL for viewing deprecated products
    $view_url = add_query_arg($query_args, admin_url('edit.php'));
    ?>
    <div class="stepper_button_container">
        <a href="<?php echo $view_url;?>" class="awca-primary-btn"  target="_blank">نمایش محصولات</a>
    </div>

</div>
