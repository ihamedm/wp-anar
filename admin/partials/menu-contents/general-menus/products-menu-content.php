<div class="stepper">
    <div class="stepper-progress-first"></div>
    <div class="stepper-progress"></div>
    <div class="stepper-container">
        <div class="step-title">لیست محصولات</div>
        <a href="#products" class="step" onclick="<?php echo awca_check_activation_state() ? 'awca_move_to_step(2)' : "awca_show_toast('لطفا برای ادامه پیکربندی یک کلید معتبر انار وارد کنید');"; ?> "></a>
    </div>
    <div class="stepper-container">
        <div class="step-title">معادل سازی دسته‌بندی‌ها</div>
        <a href="#category" class="step" onclick="<?php echo awca_check_activation_state() ? 'awca_move_to_step(3)' : "awca_show_toast('لطفا برای ادامه پیکربندی یک کلید معتبر انار وارد کنید');"; ?> "></a>
    </div>
    <div class="stepper-container">
        <div class="step-title"> معادل سازی ویژگی‌ها </div>
        <a href="#attributes" class="step" onclick="<?php echo awca_check_activation_state() ? 'awca_move_to_step(4)' : "awca_show_toast('لطفا برای ادامه پیکربندی یک کلید معتبر انار وارد کنید');" ;                                                                                                                        ?> "></a>
    </div>
</div>



<div class="wrapper">
    <?php include ANAR_WC_API_ADMIN . 'partials/menu-contents/configuration-menus/activation-form-content.php'; ?>
    <?php include ANAR_WC_API_ADMIN . 'partials/menu-contents/configuration-menus/products-menu-content.php'; ?>
    <?php include ANAR_WC_API_ADMIN . 'partials/menu-contents/configuration-menus/categories-menu-content.php'; ?>
    <?php include ANAR_WC_API_ADMIN . 'partials/menu-contents/configuration-menus/varient-menu-content.php'; ?>
</div>