<div class="mini-tool">

    <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>"  id="anar_tools_not_sync_form" >

        <strong>بررسی محصولاتی که اخیرا آپدیت نشدن</strong>
        <input type="hidden" name="action" value="anar_find_not_synced_products" />
        <?php wp_nonce_field('awca_not_synced_products_nonce', 'security_nonce'); ?>

        <button type="submit" class="awca-btn awca-sm-btn awca-alt-btn" id="submit-form-btn">پیدا کن
            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
            </svg>
        </button>


        <a href="#" class="toggle_show_hide" data-id="not_synced_advanced_settings" style="display: none">تنظیمات</a>
        <div id="not_synced_advanced_settings" style="display: none">
            <p class="awca-form-control" style="display: flex; align-items: center; gap: 16px">
                <input type="number" value="1" id="hours_ago" name="hours_ago" step="any" min="0.1" style="width: 64px" >
                <label for="hours_ago">چند ساعت پیش؟</label>
            </p>
        </div>

        <span class="form-results" style="padding: 0 12px 0;">

        </span>

    </form>

    <div id="anar_tools_sync_outdated" style="display: none">
        <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>"  id="anar-sync-outdated" class="anar-tool-alert anar-tool-alert-warning">
            <strong class="alert-title"><span class="label">توجه</span> با توجه به ضرروت بروز بودن موجودی و قیمت محصولات، لازم است همین الان اقدام به بروزرسانی این محصولات کنید.</strong>
            <button type="submit" class="awca-btn awca-sm-btn awca-success-btn" id="submit-form-btn">شروع بروزرسانی
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>


            <div class="anar-batch-messages"></div>
            <div class="anar-batch-progress"><span class="bar"></span></div>

        </form>
    </div>

</div>
