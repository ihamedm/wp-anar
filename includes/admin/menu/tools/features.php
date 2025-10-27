<?php
// Get active sub-tab
$active_subtab = $_GET['subtab'] ?? 'shipping';
?>

<ul class="subsubsub" style="float:none">
    <li><a href="?page=tools&tab=features&subtab=shipping" class="<?php echo $active_subtab === 'shipping' ? 'current' : ''; ?>">تنظیمات حمل و نقل</a></li> |
    <li><a href="?page=tools&tab=features&subtab=advanced" class="<?php echo $active_subtab === 'advanced' ? 'current' : ''; ?>">تنظیمات پیشرفته</a></li>
</ul>
<br class="clear">

<?php
// Include the appropriate sub-tab file
$subtab_file = ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/features-' . $active_subtab . '.php';
if (file_exists($subtab_file)) {
    include_once $subtab_file;
} else {
    echo '<p>صفحه مورد نظر یافت نشد.</p>';
}
?>
