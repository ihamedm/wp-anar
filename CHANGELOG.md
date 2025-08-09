# تغییرات
تمام تغییرات قابل توجه این پروژه در این فایل مستند خواهد شد.

## [0.4.2] - 1404/05/18
- feat : force and auto activation wp-persian plugin
- fix : localization Iran address format even if wp-persian not installed

## [0.4.0] - 1404/05/18
- feat : limit plugin use only for Pro users


## [0.3.22] - 1404/05/16
- fix : null variation_id in some cases
- fix : prevent duplicate product creation

## [0.3.21] - 1404/05/13
- feat : add DB-Index system to improve query performance more than 90%
- feat : add performance tests tool on states page
- feat : DB-Index tools on states page
- fix : improve daily sync strategy performance


## [0.3.20] - 1404/05/08
- fix : update shipment and order status Persian labels

## [0.3.19] - 1404/05/07
- fix : validating postcode on order creating
- fix : improved gallery downloader tool
- fix : update shipment and order status Persian labels 


## [0.3.18] - 1404/04/29
- fix : some styles
- fix : sync product type when product type changed from anar
- fix : validate post_code & phone on create anar order


## [0.3.17] - 1404/04/25
- feat : checking for Persian WordPress plugin installation
- feat : add button to mark as read notification
- feat : add sql method to publish products tool
- fix : add dismiss button to cronjob alert
- fix : improve counting unread notifications 
- fix : change menu and tab names more readable for users
- fix : issue about updating variable products on checkout


## [0.3.16] - 1404/04/22
- feat : notification center
- fix : required postcode on create anar order


## [0.3.15] - 1404/04/11
- fix : change scheduler cleanup to once pr day
- fix : restore deprecated variation products set _anar_sku as null


## [0.3.14] - 1404/04/08
- fix : _anar_sku of deprecated variable products not restored properly


## [0.3.13] - 1404/04/08
- feat : restore deprecation on syncOutdated
- feat : show shopUrl and subscription data on setting page
- feat : show anar product link based on shopUrl
- 

## [0.3.11] - 1404/03/12
- feat : slow Import method for low resources hosts
- feat : option to switch between slow/fast import
- feat : option to control how many products must update on each batch 
- feat : check some php requirements to have better compatibility on low resource hosts
- feat : new sync strategy to decrease resource usage: sync existence products every 24 hours + sync realtime per user product views
- feat : monitor and free up resources during import and sync
- feat : add jobManager methods to better control import process 
- fix : better strategy for deprecate and pending products
- fix : some shipping names
- fix : change import strategy to prevent stuck on low resource hosts
- fix : handle removed products on realtime sync
- fix : remove some dev logs from public js
- fix : remove unneeded data on anar orders details on WordPress order edit screen
- fix : log file links have problem on system status
- remove : full and partial sync completely disable to free up resources


## [0.3.10] - 1404/02/29
- fix : improve styles of anar shipping methods on checkout
- fix : improve some anar shipment names
- fix : remove some dev js logs

## [0.3.9] - 1404/02/16
- fix : issue with orders state name on some websites

## [0.3.8] - 1404/02/10
- fix : issue about state name send to anar orders when PWS plugin installed
- fix : improved updating shipments 


## [0.3.7] - 1404/02/08
- fix : issue about sync variable products with 1 variant 


## [0.3.6] - 1404/02/07
- new : add async real-time product updates on loading product page and checkout
- new : add labelPrice to products as regular price

## [0.3.5] - 1404/01/18
- fix : checkout process sometimes doesn't save anar order data
- fix : show deprecated products alert dismiss not working
- fix : syncOutdated log file doesn't archive correctly
- fix : recently not synced product link have problem by timezone
- fix : improved estimated time to complete import products calculation
- fix : anar automatic order creation option not working
- update : refactor and add table for system status on tools page 


## [0.3.4] - 1404/01/16
- new : set outofstock for removed variant on sync
- new : download galley images of all products
- update : improved log system and add log level option on plugin features


## [0.3.3] - 1403/12/23
- new : SyncOutdated class
- new : force to woocommerce activate first
- new : alert to update to the new version
- new : fullSync schedule time option on tools/feature tab 
- new : organize tools with new sub tabs
- update : upgrade styles and features of sync widget to have better UX 

## [0.3.2] - 1403/12/14
- رفع باگ نمایش دکمه پرداخت بعد از تغییر وضعیت به پرداخت شده
- رفع باگ کرش کردن ایمپورت محصولات
- بهبود پرفورمنس و لاگ ایمپورت محصولات
- بهبود ابزار ریست تنظیمات
- افزودن امکان دانلود فایل گزارش برای ارسال به پشتیبان
- بهبود گزارشات سیستم : افزودن آمارهای دقیق تر از انار
- رفع برخی اشکالات جزیی


## [0.3.1] - 1403/12/12
- محصولاتی که تو وضعیت ادیتینگ پندیگ هستند پیش نویس میکنیم
- رفع باگ : در صورتی که در پروسه سینک به تایم اوت میخوردیم پروسه چک کردن محصولات منسوخ شده استارت میخورد و گروهی از محصولات ناموجود می شدن
- افزودن بخش دریافت گزارش سیستم
- - رفع برخی اشکالات جزیی

## [0.3.0] - 1403/12/11
- نمایش پیغام تغییر تعداد محصولات در شرایطی که هنوز شمارش تمام نشده بود ایجاد خطا میکرد
- شمارش محصولات انار  و مقایسه با تعداد محصولات سمت سرور
- بهینه سازی ابزار انتشار همه محصولات برای تعداد محصول زیاد در هاست هایی با منابع پایین
- بهینه سازی اساسی همگام سازی قیمت و موجودی محصولات بطور مداوم

## [0.2.1] - 1403/11/28
- افزودن لینک نمایش محصول در انار به صفحه ویرایش محصول
- اضافه شدن ورژن و آخرین زمان آپدیت شدن دیتای محصول به متای صفحه
- بهبود شمارش محصولات انار
- منوی نوتیفیکیشن فعلا غیر فعال شد
- شهر و استان پیش فرض برای کاربر زمانی که آدرس وارد نکرده است تهران در نظر گرفته می شود
- بهبود نمایش مبلغ های متدهای حمل و نقل انار
- رفع مشکل نمایش تصویر محصول در نمایش وضعیت مرسوله ها در صفحه جزییات سفارش داشبورد کاربر
- بروزرسانی صفحه راهنما
- رفع برخی اشکالات جزیی


## [0.2.0] - 1403/11/17
- آپشن غیر فعال کردن سینک قیمت توسط کاربر
- نمایش قیمت بروز محصولات انار در صفحه ویرایش محصول و لیست محصولات ووکامرس
- بهینه سازی استراتژی سینک قیمت، اگر به هر دلیل ارتباط قطع بشه یا یه تایمی سینک انجام نشه بعد از اولین ارتباط آپدیت های قیمت و موجودی که احتمالا از دست دادیم شناسایی و بروز میشه
- هر یک ساعت یکبار سینک کل محصولات انجام میشه

## [0.1.14] - 1403/11/16
- سازگاری کامل با افزونه های دکان و دکان پرو
- افزودن ابزار تنظیم کردن فروشنده (پلاگین دکان) برای محصولات انار
- تنظیم فروشنده پیش فرض هنگام درون ریزی محصولات
- رفع باگ انتشار گروهی محصولات انار
- رفع برخی مشکلات جزیی


## [0.1.13] - 1403/11/15
- فعالسازی بتا ثبت سفارش انار از داشبورد وردپرس (بصورت پیش فرض غیر فعال است از بخش ابزارها / امکانات باید فعال شود)
- اضافه شدن تب پیکربندی در زیرمنوی ابزارها
- حل برخی مشکلات ثبت سفارش
- حل مشکل محصولاتی که از پنل انار حذف شده بودند
- ابزار انتشار کل محصولات انار بصورت یکجا به بخش ابزارها اضافه شد
- رفع برخی مشکلات جزیی


## [0.1.12] - 1403/11/11
- اخطار تغییر تعداد کالای پنل انار با تعداد محصولات درون ریزی شده در سایت
- اضافه شدن لینک همگام سازی به اکشن های پلاگین در صفحه لیست پلاگین ها
- افزودن جاب آی دی ایمپورت به محصولات برای تشخیص محصولات حذف شده از پنل انار
- افزودن تایم آخرین همگام سازی محصول با انار
- پیدا کردن محصولات حذف شده از پنل انار و تغییر وضعبت به ناموجود در سایت


## [0.1.11] - 1403/11/07
- بهبود تجربه کاربری افزودن محصول
- رفع مشکل نمایش جمع کل هزینه حمل و نقل انار زمانی که کاربر از ویژگی autofill مرورگر کروم استفاده می کند.
- مدت زمان اکسپایر دیتای دسته بندی ها و اتریبیوت ها به ۱ دقیقه کاهش داده شد


## [0.1.10] - 1403/11/03
- بهبود استایل متدهای حمل و نقل
- بهبود آیکن انار
- بهبود تجربه کاربری متدهای حمل و نقل انار در صفحه تسویه حساب
- رفع برخی باگ های گزارش شده


## [0.1.9] - 1403/11/01
- بهبود تجربه کاربری مراحل ۴ گانه افزودن محصول
- افزودن بخش راهنما و سوالات متداول
- افزودن امکان ریست تنظیمات در بخش ابزارها



## [0.1.8] - 1403/10/29
- امکان انتشار گروهی محصولات



## [0.1.7] - 1403/10/25
- نمایش لیبل انار روی سفارشات حاوی محصول انار
- نمایش اطلاعات متدهای ارسال انتخاب شده توسط کاربر در صفحه جزییات سفارش در داشبورد وردپرس
- در فرآیند ساخت محصولات مواردی که از قبل موجود هستند رفرش می شوند و متادیتاها و متغیر ها و اتریبیوت های محصول مجدد ساخته می شوند
- رفع مشکل نوار پیشرفت پردازش محصولات


## [0.1.6] - 1403/10/18
- افزودن لینک فیلتر انار در صفحه لیست محصولات
- پیاده‌سازی کرون‌جاب برای سازگاری بهتر با هاست‌های کم منابع
- جایگزینی تسک‌های پس‌زمینه با سیستم کرون‌جاب برای سازگاری بهتر با هاست‌ها
- رفع چندین باگ جزئی و بهبود عملکرد