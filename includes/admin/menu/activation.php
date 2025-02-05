<div class="wrapper">
<div class="stepContent active" id="stepContent1">
            <h2 class='awca_plugin_titles'><?php echo esc_html__('اتصال انار به وبسایت شما', 'anar-360') ?></h2>
            <div class="wrapper-50 plugin_activation_container">


                <h2 class="text-center">برای فعال‌سازی پلاگین ابتدا کلید احراز هویتی خودتان را در فیلد زیر وارد کنید.</h2>
                <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" class="plugin_activation_form" id="plugin_activation_form">
                    <?php if (Anar\core\Activation::validate_saved_activation_key_from_anar()) : ?>
                        <div class="alert">
                            <span class="closebtn" onclick="this.parentElement.style.display='none';">&times;</span>
                            <strong>پیام:</strong> توکن شما معتبر و سمت انار مورد تایید است
                        </div>
                    <?php endif; ?>
                    <label for="activation_code">وارد کردن کلید احراز هویتی :</label>

                    <?php
                        $activation_code = get_option('_awca_activation_key', '');
                        $textarea_style = $activation_code ? 'direction: ltr; text-align: left' : '';
                    ?>
                    <textarea rows="7" style="<?php echo $textarea_style;?>" id="activation_code" name="activation_code" placeholder="لطفا کلید احراز هویتی فروشگاه‌تون در انار را وارد کنید" required><?php echo $activation_code; ?></textarea>


                    <input type="hidden" name="action" value="awca_handle_token_activation_ajax" />
                    <?php wp_nonce_field('awca_handle_token_activation_ajax_nonce', 'awca_handle_token_activation_ajax_field'); ?>
                    <div class="stepper_button_container">
                        <button type="submit" class="plugin_activation_button stepper_btn" id="next_stepper_btn plugin_activation_button">
                            اتصال                            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>    
</div>


<?php include ANAR_PLUGIN_PATH . 'includes/admin/menu/footer.php';?>
