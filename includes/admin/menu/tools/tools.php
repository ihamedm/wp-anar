<?php
$active_tools_group = $_GET['group'] ?? 'sync';
?>

<div id="anar-tools" class="tab-content">

    <ul class="subsubsub" style="float:none">
        <li><a href="?page=tools&tab=tools&group=sync" class="<?php echo $active_tools_group === 'sync' ? 'current' : ''; ?>">بروزرسانی قیمت و موجودی</a></li> |
        <li><a href="?page=tools&tab=tools&group=products" class="<?php echo $active_tools_group === 'products' ? 'current' : ''; ?>">محصولات</a></li> |
        <?php if (class_exists('WeDevs_Dokan')):?>
            <li><a href="?page=tools&tab=tools&group=dokan" class="<?php echo $active_tools_group === 'dokan' ? 'current' : ''; ?>">دکان</a></li> |
        <?php endif;?>
        <li><a href="?page=tools&tab=tools&group=reset" class="<?php echo $active_tools_group === 'reset' ? 'current' : ''; ?>">ریست</a></li>

    </ul>
    <br>

    <div class="awca-wrap-all-tools">
        <?php include_once dirname(__FILE__) . '/group-'.$active_tools_group.'.php';?>
    </div>

</div>


<?php include ANAR_PLUGIN_PATH . 'includes/admin/menu/footer.php';?>
