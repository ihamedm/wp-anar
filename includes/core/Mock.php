<?php

namespace Anar\Core;

class Mock{

    public static $notifications = '{
    "success": true,
    "total": 17,
    "result": [
        {
            "_id": "6863e60e033c2a5893a7a3ff",
            "title": "تغییر عنوان محصول (بسیار مهم)",
            "description": "موجودی محصول فوم شستشو صورت راکوتن مدل Diping مناسب پوست چرب حجم 150 میلی لیتر به اتمام رسیده است. در صورت شارژ مجدد از همین طریق اطلاع رسانی خواهد شد.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "wordpress",
            "reason": "wordpress",
            "type": "info",
            "target": "66bfc1c5b0c12e8ff4fe8b0f",
            "targetType": "products",
            "read": true,
            "__v": 0,
            "createdAt": "2025-07-01T13:43:42.854Z",
            "updatedAt": "2025-07-01T13:43:42.854Z"
        },
        {
            "_id": "6863be7d033c2a5893a6df6c",
            "title": "نسخه جدید پلاگین",
            "description": "موجودی محصول \"تست ۵۲\" که پیش تر به اتمام رسیده بود شارژ شد و برای فروش در دسترس است.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "wordpress",
            "reason": "wordpress",
            "type": "info",
            "target": "680cc9477e4d22dba64db35e",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-07-01T10:54:53.047Z",
            "updatedAt": "2025-07-01T10:54:53.047Z"
        },
        {
            "_id": "6863be24033c2a5893a6dcfa",
            "title": "اتمام موجودی",
            "description": "موجودی محصول تست ۵۲ به اتمام رسیده است. در صورت شارژ مجدد از همین طریق اطلاع رسانی خواهد شد.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-out-of-stock",
            "type": "info",
            "target": "680cc9477e4d22dba64db35e",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-07-01T10:53:24.012Z",
            "updatedAt": "2025-07-01T10:53:24.012Z"
        },
        {
            "_id": "68481b1ff13a947b99f5eeab",
            "title": "شارژ مجدد",
            "description": "موجودی محصول \"ریمل قهوه ای آدارز / قهوه ای\" که پیش تر به اتمام رسیده بود شارژ شد و برای فروش در دسترس است.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-re-stock",
            "type": "info",
            "target": "65a66276e04cdb1a748f298c",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-06-10T11:46:39.973Z",
            "updatedAt": "2025-06-10T11:46:39.973Z"
        },
        {
            "_id": "683af3094fb117e8c6d5500a",
            "title": "اتمام موجودی",
            "description": "موجودی محصول ریمل حجم دهنده آبی آدارز / آبی به اتمام رسیده است. در صورت شارژ مجدد از همین طریق اطلاع رسانی خواهد شد.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-out-of-stock",
            "type": "info",
            "target": "65a66067e04cdb1a748efea5",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-05-31T12:16:09.613Z",
            "updatedAt": "2025-05-31T12:16:09.613Z"
        },
        {
            "_id": "683aef214fb117e8c6d358f9",
            "title": "اتمام موجودی",
            "description": "موجودی محصول ریمل حجم دهنده مشکی آدارز / مشکی به اتمام رسیده است. در صورت شارژ مجدد از همین طریق اطلاع رسانی خواهد شد.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-out-of-stock",
            "type": "info",
            "target": "65a6615ce04cdb1a748f1272",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-05-31T11:59:29.139Z",
            "updatedAt": "2025-05-31T11:59:29.139Z"
        },
        {
            "_id": "683ad6dc4fb117e8c6bc25aa",
            "title": "اتمام موجودی",
            "description": "موجودی محصول سرم ضد جوش راکوتن مدل Anti Acne حجم 30 میلی لیتر به اتمام رسیده است. در صورت شارژ مجدد از همین طریق اطلاع رسانی خواهد شد.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-out-of-stock",
            "type": "info",
            "target": "66bfc1c2d503d3187af972ce",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-05-31T10:15:57.018Z",
            "updatedAt": "2025-05-31T10:15:57.018Z"
        },
        {
            "_id": "6833381fba98c0bec59752e5",
            "title": "اتمام موجودی",
            "description": "موجودی محصول سرم ضد جوش راکوتن مدل Anti Acne حجم 30 میلی لیتر به اتمام رسیده است. در صورت شارژ مجدد از همین طریق اطلاع رسانی خواهد شد.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-out-of-stock",
            "type": "info",
            "target": "66bfc1c2d503d3187af972ce",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-05-25T15:32:47.903Z",
            "updatedAt": "2025-05-25T15:32:47.903Z"
        },
        {
            "_id": "682ed4e3ba98c0bec5e1ce5b",
            "title": "اتمام موجودی",
            "description": "موجودی محصول ریمل قهوه ای آدارز / قهوه ای به اتمام رسیده است. در صورت شارژ مجدد از همین طریق اطلاع رسانی خواهد شد.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-out-of-stock",
            "type": "info",
            "target": "65a66276e04cdb1a748f298c",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-05-22T07:40:19.129Z",
            "updatedAt": "2025-05-22T07:40:19.129Z"
        },
        {
            "_id": "682b2fbf2564e073317d4a2c",
            "title": "شارژ مجدد",
            "description": "موجودی محصول \"ریمل قهوه ای آدارز / قهوه ای\" که پیش تر به اتمام رسیده بود شارژ شد و برای فروش در دسترس است.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-re-stock",
            "type": "info",
            "target": "65a66276e04cdb1a748f298c",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-05-19T13:18:55.502Z",
            "updatedAt": "2025-05-19T13:18:55.502Z"
        },
        {
            "_id": "682987ba2564e07331b66143",
            "title": "اتمام موجودی",
            "description": "موجودی محصول آبرسان راکوتن مدل پوست خشک حجم 50 میلی لیتر به اتمام رسیده است. در صورت شارژ مجدد از همین طریق اطلاع رسانی خواهد شد.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-out-of-stock",
            "type": "info",
            "target": "66bfc1c2d503d3187af972cb",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-05-18T07:09:46.872Z",
            "updatedAt": "2025-05-18T07:09:46.872Z"
        },
        {
            "_id": "681b2779a4bf949a72c04474",
            "title": "شارژ مجدد",
            "description": "موجودی محصول \"ماشین حساب کودک همراه با تخته\" که پیش تر به اتمام رسیده بود شارژ شد و برای فروش در دسترس است.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-re-stock",
            "type": "info",
            "target": "66277589fb0f16c8a66308b4",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-05-07T09:27:21.660Z",
            "updatedAt": "2025-05-07T09:27:21.660Z"
        },
        {
            "_id": "6807cb5fbaf7c707ee3d4621",
            "title": "شارژ مجدد",
            "description": "موجودی محصول \"صندل زنانه طرح گل / 40 / قرمز\" که پیش تر به اتمام رسیده بود شارژ شد و برای فروش در دسترس است.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-re-stock",
            "type": "info",
            "target": "672367fa115ed90bdddc3ec1",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-04-22T17:01:19.292Z",
            "updatedAt": "2025-04-22T17:01:19.292Z"
        },
        {
            "_id": "6807cb5abaf7c707ee3d453f",
            "title": "شارژ مجدد",
            "description": "موجودی محصول \"صندل زنانه طرح گل / 39 / قرمز\" که پیش تر به اتمام رسیده بود شارژ شد و برای فروش در دسترس است.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-re-stock",
            "type": "info",
            "target": "672367fa115ed90bdddc3ec1",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-04-22T17:01:14.209Z",
            "updatedAt": "2025-04-22T17:01:14.209Z"
        },
        {
            "_id": "6807cb54baf7c707ee3d445d",
            "title": "شارژ مجدد",
            "description": "موجودی محصول \"صندل زنانه طرح گل / 38 / قرمز\" که پیش تر به اتمام رسیده بود شارژ شد و برای فروش در دسترس است.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-re-stock",
            "type": "info",
            "target": "672367fa115ed90bdddc3ec1",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-04-22T17:01:08.872Z",
            "updatedAt": "2025-04-22T17:01:08.872Z"
        },
        {
            "_id": "6807cb4fbaf7c707ee3d437b",
            "title": "شارژ مجدد",
            "description": "موجودی محصول \"صندل زنانه طرح گل / 37 / قرمز\" که پیش تر به اتمام رسیده بود شارژ شد و برای فروش در دسترس است.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-re-stock",
            "type": "info",
            "target": "672367fa115ed90bdddc3ec1",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-04-22T17:01:03.476Z",
            "updatedAt": "2025-04-22T17:01:03.476Z"
        },
        {
            "_id": "6804d82f98bbb41a7d20d1d0",
            "title": "اتمام موجودی",
            "description": "موجودی محصول کرم مرطوب کننده راکوتن مدل 500 میلی لیتر به اتمام رسیده است. در صورت شارژ مجدد از همین طریق اطلاع رسانی خواهد شد.",
            "user": "668b74f670ecb3468282eb7b",
            "account": "668b74f670ecb3468282eb7d",
            "application": "all",
            "reason": "product-out-of-stock",
            "type": "info",
            "target": "66bfc1c5b0c12e8ff4fe8b0c",
            "targetType": "products",
            "read": false,
            "__v": 0,
            "createdAt": "2025-04-20T11:19:11.516Z",
            "updatedAt": "2025-04-20T11:19:11.516Z"
        }
    ],
    "skip": 0,
    "limit": 0
}';
}