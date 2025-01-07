<?php

?>
<div class="wrapper">
    <h2 class="awca_plugin_titles">اعلان های انار</h2>
    <p class="awca_plugin_subTitles" style="text-align:center">
        همه اعلان هایی که در پنل انار برای شما ارسال شده است را در این صفحه می توانید ببینید.
    </p>


    <table class="awca_notification_list_table">
        <thead>
        <tr style="text-align:right">
            <th>ردیف</th>
            <th>عنوان</th>
            <th>متن</th>
        </tr>
        </thead>

        <tbody class="list" id="awca_notification_list">

            <tr class="item">
                <td></td>
                <td>
                    <div class="awca-loading-message-spinner">
                        در حال دریافت اعلان ها ...
                        <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                            <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                        </svg>
                    </div>
                </td>
                <td></td>
            </tr>

        </tbody>

    </table>

    <div id="awca_pagination"></div>


</div>

<?php include ANAR_PLUGIN_PATH . 'includes/admin/menu/footer.php';?>