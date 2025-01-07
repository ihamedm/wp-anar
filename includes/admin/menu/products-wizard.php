<div class="stepper">
    <div class="stepper-progress-first"></div>
    <div class="stepper-progress"></div>
    <div class="stepper-container">
        <div class="step-title activeTitle">لیست محصولات</div>
        <a href="#products" class="step active" data-next-step="1"></a>
    </div>
    <div class="stepper-container">
        <div class="step-title">معادل سازی دسته‌بندی‌ها</div>
        <a href="#category" class="step"  data-next-step="2"></a>
    </div>
    <div class="stepper-container">
        <div class="step-title"> معادل سازی ویژگی‌ها </div>
        <a href="#attributes" class="step"  data-next-step="3"></a>
    </div>
    <div class="stepper-container">
        <div class="step-title"> ساخت محصولات </div>
        <a href="#final" class="step"  data-next-step="4"></a>
    </div>
</div>

<?php
$api_data_handler = new \Anar\ApiDataHandler('categories');
$categories_response = $api_data_handler->getStoredApiResponse();
?>

<div class="wrapper">
    <?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/wizard/products.php'; ?>
    <?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/wizard/categories.php'; ?>
    <?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/wizard/attributes.php'; ?>
    <?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/wizard/final.php'; ?>
</div>

<?php include ANAR_PLUGIN_PATH . 'includes/admin/menu/footer.php';?>