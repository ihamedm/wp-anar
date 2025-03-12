<?php
if (isset($_GET['awca_hide_notice']) && wp_verify_nonce($_GET['nonce'], 'awca_hide_notice')) {
    delete_option('awca_deprecated_products_count', 0);
    return;
}

// Only show to administrators
if (!current_user_can('manage_options')) {
    return;
}

$removed_count = get_option('awca_deprecated_products_count', 0);
if ($removed_count > 0) :
    $deprecated_time = get_option('awca_deprecated_products_time');
    $deprecated_time_ui = $deprecated_time ? '<strong>( ' . mysql2date('j F Y' . ' ساعت ' . 'H:i', $deprecated_time) . ' )</strong>' : '';

    // Create query parameters for the products page
    $query_args = array(
        'post_type' => 'product',
        'anar_deprecated' => 'true'
    );

    // Generate the URL for viewing deprecated products
    $view_deprecated_url = add_query_arg($query_args, admin_url('edit.php'));

    // Generate the dismiss URL (redirect back to current page after dismissing)
    $current_url = add_query_arg(null, null);
    $dismiss_nonce = wp_create_nonce('awca_hide_notice');
    $dismiss_url = add_query_arg([
        'awca_hide_notice' => '1',
        'nonce' => $dismiss_nonce
    ], $current_url);

    ?>
    <div class="mini-tool">
        <div id="anar_check_deprecated_products">
            <div class="anar-tool-alert anar-tool-alert-warning">
                <strong class="alert-title"><span class="label">توجه</span>محصولات منسوخ شده</strong>
                <p>در بروزرسانی های اخیر <?php echo $deprecated_time_ui;?> به نظر می رسید محصولاتی از پنل انار شما به هر دلیل حذف شده اند، جهت اطمینان از غیر قابل سفارش شدن این محصولات در وب سایت شما ما این محصولات را <strong>ناموجود</strong> و لیبل انار را از آنها برداشته ایم.</p>
                <p>از طریق لینک زیر میتوانید این محصولات را ببینید و تعیین تکلیف کنید.</p>
                <div class="buttons">
                    <a class="awca-sm-btn awca-primary-btn" href="<?php echo esc_url($view_deprecated_url);?>" target="_blank">نمایش محصولات منسوخ شده</a>
                    <a href="<?php echo esc_url($dismiss_url);?>" >x دیگر نمایش نده</a>
                </div>
            </div>
        </div>
    </div>

<?php endif;?>
