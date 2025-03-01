<div class="wrapper tools-wrapper">
    <h2 class="awca_plugin_titles">محصولات آپدیت نشده</h2>
    <p class="awca_plugin_subTitles">پیدا کردن محصولاتی که اخیرا قیمت و موجودی آنها آپدیت نشده</p>

    <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>"  id="anar_tools_not_sync_form">
        <input type="hidden" name="action" value="anar_find_not_synced_products" />
        <?php wp_nonce_field('awca_not_synced_products_nonce', 'security_nonce'); ?>

        <div class="stepper_button_container">
            <button type="submit" class="awca-primary-btn" id="submit-form-btn">پیدا کن
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>

        </div>
        <div class="" style="display: flex; flex-direction: column; align-items: center; margin-bottom: 32px">
            <a href="#" class="toggle_show_hide" data-id="not_synced_advanced_settings">تنظیمات</a>

            <div id="not_synced_advanced_settings" style="display: none">
                <p class="awca-form-control" style="display: flex; align-items: center; gap: 16px">
                    <input type="number" value="1" id="hours_ago" name="hours_ago" step="any" min="0.1" style="width: 64px" >
                    <label for="hours_ago">چند ساعت پیش؟</label>
                </p>
            </div>
        </div>

        <div class="form-results" style="padding: 12px 0; text-align: center">

        </div>

    </form>


    <form id="process_not_synced_products_batch__" style="display: none">
        <input type="hidden" name="action" value="process_not_synced_products" />
        <button type="submit" >همگام سازی محصولات پیدا شده</button>
    </form>


</div>
