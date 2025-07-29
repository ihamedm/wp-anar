<div class="wrapper" id="awca_payments">
    <h2 class="awca_plugin_titles">تسویه حساب با انار</h2>
    <p class="awca_plugin_subTitles" style="text-align:center">
برای تسویه حساب اردر ها با انار روی لینک زیر کلیک کنید.
    </p>

    <p class="awca_plugin_subTitles" style="color:red; text-align:center" id="awca_payable">
    </p>
    <form class="plugin_pay_form" id="plugin_pay_form">
            <div class="stepper_button_container">
                <?php
                $token = anar_get_saved_token();
                $callback = admin_url('admin.php?page=awca-sync-pay');
                $pay_link = "https://api.anar360.com/wp/orders/payment/pay?token=$token&callback=$callback";
                ?>
                <a href="<?php echo $pay_link;?>" target="_blank" class="stepper_btn" >
                    تسویه حساب                          <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                        <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                    </svg>
                </a>
            </div>
    </form>

    <div class="" style="position:relative">
        <div id="awca-loading-frame" style="display:none">
            <div class="msg">
                در حال دریافت لیست بدهی ها ...
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </div>
        </div>
        <table class="awca_payment_list_table">
        <thead>
        <tr style="text-align:right">
            <th>ردیف</th>
            <th>شماره سفارش</th>
            <th>شماره سفارش - گروهی</th>
            <th>وضعیت</th>
            <th>قابل پرداخت</th>
            <th>توضیحات</th>
        </tr>
        </thead>

        <tbody class="list" id="awca_payment_list">

        <tr class="item"><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr class="item"><td></td><td></td><td></td><td></td><td></td><td></td></tr>

        </tbody>

    </table>
    </div>
    <div id="awca_pagination"></div>

</div>

<?php include ANAR_PLUGIN_PATH . 'includes/admin/menu/footer.php';?>