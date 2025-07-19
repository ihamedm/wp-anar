<div class="wrapper anar-tools-wrapper" >
    <h2 class="awca_plugin_titles">اختصاص محصولات انار به فروشنده دکان</h2>
    <p class="awca_plugin_subTitles">
        از طریق این فرم می توانید کل محصولات انار را به یک فروشنده افزونه دکان اختصاص دهید.
    </p>

    <form method="post" id="anar-set-vendor-form" class="anar-tool-ajax-form">
        <input type="hidden" name="action" value="awca_set_vendor_for_anar_products_ajax">
        <?php wp_nonce_field('anar_set_vendor_ajax_nonce', 'security_nonce'); ?>
        <?php
        $vendors = awca_get_dokan_vendors();

        if (empty($vendors)) {
            echo '<div class="" style="color: red"><p>هنوز هیچ فروشنده ایی تعریف نکرده اید!</p></div>';
        }
        ?>

        <select name="vendor_id" id="vendor_id" class="regular-text">
            <option value=""> انتخاب فروشنده </option>
            <?php foreach ($vendors as $vendor) : ?>
                <option value="<?php echo esc_attr($vendor['id']); ?>">
                    <?php echo esc_html($vendor['text']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="stepper_button_container">
            <button type="submit" class="plugin_activation_button stepper_btn tool_submit_btn" ">
            <span>اختصاص این فروشنده به تمام محصولات انار</span>
            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
            </svg>
            </button>
        </div>
    </form>

</div>
