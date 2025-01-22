<?php
// Define the path to the changelog.md file
$help_file_path = ANAR_PLUGIN_PATH . 'HELP.md';

// Check if the file exists
if (file_exists($help_file_path)) {
    // Get the contents of the changelog.md file
    $help_content = file_get_contents($help_file_path);

    $Parsedown = new Parsedown();
    $help_html = $Parsedown->text($help_content);

    // Display the changelog content
    echo '<div class="changelog-content" style="max-width: 100%">';
    echo $help_html;
    echo '</div>';
} else {
    echo '<p>فایل تغییرات در پروژه پیدا نشد!</p>';
}
?>