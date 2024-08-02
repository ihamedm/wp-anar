<?php
$api_url = 'https://api.anar360.com/api/360/notification';
//$awca_messages = awca_get_data_from_api($api_url);
$awca_messages = array(
    (object) ['id' => '1', 'date' => '1403-04-27 , 12:24', 'title' => 'حذف کالا', 'description' => 'کالای هدفون از طرف تامین کننده حذف شد.'],
    (object) ['id' => '1', 'date' => '1403-03-27 , 12:24', 'title' => 'بروزرسانی', 'description' => 'قوانین و مقررات همکاری در فروش انار بروز رسانی شد'],
    (object) ['id' => '1', 'date' => '1403-01-27 , 12:24', 'title' => 'تبریک', 'description' => 'درخواست همکاری شما تایید شد'],
);

?>
<div class="wrapper">
    <h2 class="awca_plugin_titles">اعلان های انار</h2>
    <p class="awca_plugin_subTitles">
        همه اعلان هایی که در پنل انار برای شما ارسال شده است را در این صفحه می توانید ببینید.
    </p>

    <?php if ($awca_messages != null) : ?>
        <table class="awca_notification_list_table">
            <thead>
            <tr>
                <th>ردیف</th>
                <th>تاریخ</th>
                <th>عنوان</th>
                <th>متن</th>
                <th>#</th>
            </tr>
            </thead>

            <tbody class="list">
            <?php foreach ($awca_messages as $index => $item) : ?>

                <tr class="item">
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo $item->date; ?></td>
                    <td class="awca_product_title"><?php echo $item->title; ?></td>
                    <td><?php echo $item->description; ?></td>
                    <td>
                        <button class="mark-as-read" >
                            <span class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                  <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                                </svg>
                            </span>

                            <span>مشاهده</span>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>

            </tbody>

        </table>

    <?php else : ?>
        بازیابی اطلاعات محصولات با مشکل مواجه شد لطفا صفحه را بروزرسانی نمایید
    <?php endif; ?>

</div>

<?php include ANAR_WC_API_ADMIN . 'partials/menu-contents/footer.php';?>