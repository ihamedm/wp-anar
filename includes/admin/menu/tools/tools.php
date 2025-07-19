<?php
$active_tools_group = $_GET['group'] ?? 'general';
?>

<div id="anar-tools" class="tab-content">

    <ul class="subsubsub" style="float:none">
        <li><a href="?page=tools&tab=tools&group=general" class="<?php echo $active_tools_group === 'general' ? 'current' : ''; ?>">عمومی</a></li> |
        <li><a href="?page=tools&tab=tools&group=pro" class="<?php echo $active_tools_group === 'pro' ? 'current' : ''; ?>">پیشرفته</a></li>
    </ul>
    <br>

    <div class="awca-wrap-all-tools">
        <?php include_once dirname(__FILE__) . '/group-'.$active_tools_group.'.php';?>
    </div>

</div>


<?php include ANAR_PLUGIN_PATH . 'includes/admin/menu/footer.php';?>
