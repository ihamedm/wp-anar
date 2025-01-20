<?php

use Anar\Background_Process_Images;

$image_process = Background_Process_Images::get_instance();
$image_process_status = $image_process->get_status();
$image_process_data = $image_process->get_process_data();

?>

<div class="wrapper" style="margin-top:32px">
    <h2 class="awca_plugin_titles">همگام سازی <span>تصاویر</span> محصولات با انار</h2>

    <form method="post" id="awca-dl-all-product-gallery-images">
        <?php wp_nonce_field('run_dl_product_gallery_images_bg_process_ajax_nonce', 'run_dl_product_gallery_images_bg_process_ajax_field'); ?>
        <div class="stepper_button_container">
            <button type="submit" class="plugin_activation_button stepper_btn" id="dl_product_g_imgs_btn"
                <?php echo (!$image_process_status) ? '' : 'disabled';?>>
                <span>دانلود تصاویر گالری محصولات انار</span>
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>
        </div>


        <?php

        if(in_array($image_process_status, ['processing', 'queued'])) {
            $pause_btn_style = $resume_btn_style = '';
            $progress_bar_percent = 0;
            $process_data_message = "0%";
            if($image_process_data['processed_products'] > 0){
                $progress_bar_percent = ($image_process_data['processed_products'] * 100) / $image_process_data['total_products'];
                $process_data_message = ceil($progress_bar_percent) . '%';
            }

            if ($image_process_status == 'processing') {
                $message = 'دریافت تصاویر در پس زمینه در حال انجام است. می توانید این صفحه را ببندید.';
                $pause_btn_style = ' style="display:none"';
                $resume_btn_style = ' style="display:none"';
            }
            if ($image_process_status == 'queued') {
//                $message = 'دریافت تصاویر در پس زمینه متوقف شده است.';
                $message = 'دریافت تصاویر در پس زمینه در حال انجام است. می توانید این صفحه را ببندید.';

                $resume_btn_style = ' style="display:none"';
                $pause_btn_style = ' style="display:none"';
            }

            $pause_btn = '<a href="#" class="pause" data-action="pause_process" '.$pause_btn_style.' >وقفه</a>';
            $resume_btn = '<a href="#" class="resume" data-action="resume_process" '.$resume_btn_style.'>ادامه</a>';
            $cancel_btn = '<a href="#" class="cancel" data-action="cancel_process">لغو</a>';


            $progress = '<div class="awca_ajax-result-progress" style="display:block"><span class="bar" style="width:'.round($progress_bar_percent).'%"></span></div>';

            $buttons = sprintf('<div class="buttons">%s %s %s</div>', $pause_btn, $resume_btn, $cancel_btn) ;

            printf ('<div class="awca_ajax-result success process-controllers %s" ><p>%s</p><p class="process_data_message">%s</p>%s %s</div>',
                $image_process_status,
                $message,
                $process_data_message,
                $progress,
                $buttons,
            );

        }else{
            echo '<div class="awca_ajax-result"></div>';
        }
        ?>


    </form>

</div>
