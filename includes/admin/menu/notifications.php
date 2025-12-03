<?php

?>
<div class="wrapper">
    <h2 class="awca_plugin_titles">اعلان های انار</h2>
    <p class="awca_plugin_subTitles" style="text-align:center">
        همه اعلان هایی که در پنل انار برای شما ارسال شده است را در این صفحه می توانید ببینید.
    </p>

    <div class="anar-notification-tabs">
        <button class="anar-tab-btn active" data-application="wordpress">
            اعلان های وردپرس
        </button>
        <button class="anar-tab-btn" data-application="all">
           اعلان های انار
        </button>
    </div>

    <div style="display:none; justify-content: end; padding: 0 0 32px">
        <button type="submit" class="awca-btn awca-success-btn" id="mark-page-as-read-btn" data-page="1">
            نشانه گذاری همه به عنوان خوانده شده                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
            </svg>
        </button>
    </div>

    <div class="list" id="anar_notification_list">

        <div class="awca-loading-message-spinner">
            در حال دریافت اعلان ها ...
            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
            </svg>
        </div>

    </div>

    <div id="awca_pagination"></div>


</div>

<?php include ANAR_PLUGIN_PATH . 'includes/admin/menu/footer.php';?>