<?php
$log_content = awca_get_log_content();

// Handle download log action
if (isset($_POST['awca_download_log'])) {
    awca_download_log();
}

// Handle clear log action
if (isset($_POST['awca_clear_log'])) {
    awca_clear_log();
}
?>


<div class="wrap">
    <h1>لاگ انار</h1>
    <div id="logContent" style="direction:ltr;text-align:left;background-color: #fff; padding: 10px; height: 400px; overflow-y: scroll; white-space: pre-wrap; margin-top: 20px;">
        <?php echo $log_content;?>
    </div>

    <form method="post" style="margin-top:24px">
        <input type="submit" name="awca_download_log" class="button button-primary" value="دانلود فایل لاگ">
        <input type="submit" name="awca_clear_log" class="button button-secondary" value="پاکسازی لاگ">
    </form>
</div>




<?php
// Add a button to download the log file
function awca_add_download_log_button() {
    echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
    echo '<input type="hidden" name="action" value="download_awca_log">';
    echo '<button type="submit" class="button">Download Log File</button>';
    echo '</form>';
}

add_action('admin_notices', 'awca_add_download_log_button');

// Function to get the last 100 lines of the log file
function awca_get_log_content() {
    $log_file_path = WP_CONTENT_DIR . '/anar.log'; // Path to the log file

    if (file_exists($log_file_path)) {
        $lines = awca_read_last_lines($log_file_path, 100);
        $content = implode("", array_map('esc_html', $lines));
        return nl2br($content);
    } else {
        return 'Log file not found.';
    }
}

// Function to read the last N lines of a file
function awca_read_last_lines($file, $lines) {
    $handle = fopen($file, "r");
    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = [];

    while ($linecounter > 0) {
        $t = " ";
        while ($t != "\n") {
            if (fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true;
                break;
            }
            $t = fgetc($handle);
            $pos--;
        }
        $linecounter--;
        if ($beginning) {
            rewind($handle);
        }
        $text[$lines - $linecounter - 1] = fgets($handle);
        if ($beginning) break;
    }
    fclose($handle);
    return array_reverse($text);
}

// Function to download the log file
function awca_download_log() {
    $log_file_path = WP_CONTENT_DIR . '/anar.log'; // Path to the log file

    if (file_exists($log_file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($log_file_path));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($log_file_path));
        readfile($log_file_path);
        exit;
    } else {
        echo '<div class="error"><p>Log file not found.</p></div>';
    }
}

// Function to clear the log file
function awca_clear_log() {
    $log_file_path = WP_CONTENT_DIR . '/anar.log'; // Path to the log file

    if (file_exists($log_file_path)) {
        file_put_contents($log_file_path, '');
        echo '<div class="updated"><p>Log file cleared.</p></div>';
    } else {
        echo '<div class="error"><p>Log file not found.</p></div>';
    }
}