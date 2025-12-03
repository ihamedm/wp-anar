<?php

if (!defined('ABSPATH')) {
    exit;
}

$next_step_url = add_query_arg(
    [
        'page' => 'wp-anar-import-v2',
        'step' => 'fetch-data',
    ],
    admin_url('admin.php')
);

?>
<div class="awca-card awca-card--center">
    <h2>به درون‌ریزی جدید محصولات انار خوش آمدید</h2>
    <p>
        در این نسخه تازه، همه مراحل لازم برای دریافت داده‌ها، معادل‌سازی دسته‌بندی‌ها و ویژگی‌ها و ساخت
        محصولات ووکامرس به‌صورت قدم‌به‌قدم پیش می‌روند. با کلیک روی دکمه زیر، فرآیند دریافت داده‌های مورد نیاز آغاز
        خواهد شد.
    </p>

    <a class="button button-primary button-hero" href="<?php echo esc_url($next_step_url); ?>">
        شروع فرآیند
    </a>
</div>

