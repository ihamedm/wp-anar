<?php
// Define the path to the changelog.md file
$changelog_file_path = ANAR_PLUGIN_PATH . 'USERCHANGELOG.md';

// Check if the file exists
if (file_exists($changelog_file_path)) {
    // Get the contents of the changelog.md file
    $changelog_content = file_get_contents($changelog_file_path);

    $Parsedown = new Parsedown();
    $changelog_html = $Parsedown->text($changelog_content);

    // Display the changelog content
    echo '<div class="changelog-content">';
    echo $changelog_html;
    echo '</div>';
} else {
    echo '<p>فایل تغییرات در پروژه پیدا نشد!</p>';
}
?>