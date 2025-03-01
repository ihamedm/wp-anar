
<?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/tool-sync.php';?>
<?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/tool-not-synced.php';?>
<?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/tool-publish-drafts.php';?>
<?php
if (class_exists('WeDevs_Dokan')) {
    include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/set-vendor.php';
}
?>
<?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/tool-reset.php';?>

<?php include ANAR_PLUGIN_PATH . 'includes/admin/menu/footer.php';?>
