
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 2.1 Check Shipping Fee
This API is used for batch get estimated shipping fee before order creation. 
Note: At most 100 per batch.

| Basic Info |  |
| HTTP URL | {{host}}/open/api/v1/order/batch_check_order |
| HTTP Method | POST |
| Basic request parameter | Content-Type: application/ JSON, UTF-8 encoding |

## Request :
## Request header:
| Parameters | Type | Mandatory (Y/N) | Description |
| app-id | integer | Y | Issued by SPX Open platform, each APP is unique. |
| check-sign | string | Y | Used to verify the source security of requests. For details of how to generate, see API authentication description. |
| timestamp | integer | Y | The requested Unix timestamp,  / Unit: second |
| random-num | integer | Y | random number / Data constraints:signed 64-bit integers |

## Request body:
| Parameters | Type | Mandatory (Y/N) | Description |
| user_id |  |  | integer | Y | Unique user identification. / Generated via Create Account API or provided by open platform. |
| user_secret |  |  | string | Y | The user secret corresponding to the user id. / Generated via the Create Account API or provided by open platform. |
| orders |  |  | list | Y | Order detail information |
|  | base_info |  |  |  |  |
|  |  | service_type | integer | Y | service type  / Optional value： / 1: standard service   |
|  | sender_info |  |  |  |  |
|  |  | sender_state | string | Y | State name /  / Data constraints: / max length:64 /  / Example: / For TH: “Province name” e.g. จังหวัดกรุงเทพมหานคร;  / For VN: “Province name” e.g.TP. Hồ Chí Minh |
|  |  | sender_city | string | Y | City name /  / Data constraints: / max length:64 /  / Example: / For TH: “District name” e.g. เขตวัฒนา;  / For VN: “City name” e.g. Quận 1 |
|  |  | sender_post_code | string | Y | sender post code |
|  |  | sender_longitude | string | N | sender longitude /  / Data constraints: / max length:16 |
|  |  | sender_latitude | string | N | sender latitude /  / Data constraints: / max length:16 |
|  |  | sender_detail_address | string | N | sender detail address /  / Data constraints: / max length:256 |
|  | fulfillment_info |  |  |  |  |
|  |  | collect_type | integer | N | collect type /  / Optional value： / 1: pickup / 2: drop off |
|  |  | voucher_code | string | N | voucher_code |
|  | deliver_info |  |  |  |  |
|  |  | deliver_state | string | Y | the deliver state name /  / Data constraints: / max length: 64 /  / Example: / For TH: “Province name” e.g. จังหวัดกรุงเทพมหานคร;  / For VN: “Province name” e.g.TP. Hồ Chí Minh |
|  |  | deliver_city | string | Y | the deliver city name /  / Data constraints: / max length: 64 /  / Example: / For TH: “District name” e.g. เขตวัฒนา;  / For VN: “City name” e.g. Quận 1 |
|  |  | deliver_post_code | string | Y | the deliver post code |
|  |  | deliver_longitude | string | N | deliver longitude /  / Data constraints: / max length:16 |
|  |  | deliver_latitude | string | N | deliver latitude /  / Data constraints: / max length:16 |
|  |  | deliver_detail_address | string | N | the receiver’s detail address /  / Data constraints: / max length: 256 |
|  | parcel_info |  |  |  |  |
|  |  | parcel_weight | number | Y | parcel weight / Unit: kg / - limit for TH: 0< parcel_weight |
|  |  | parcel_length | number | N | parcel length / Unit: cm |
|  |  | parcel_width | number | N | parcel width / Unit: cm |
|  |  | parcel_height | number | N | parcel height / Unit: cm |
|  |  | parcel_item_name | string | N | parcel item name /  / Data constraints: / max length: 256 |
|  |  | parcel_item_quantity | integer | N | Item quantity |
|  |  | parcel_value | number | N | parcel_value |
|  | return_info |  | object | N |  |
|  |  | return_state | string | N | SPX will auto map based on the return_post_code; hence to exclude this parameter. |
|  |  | return_city | string | N | SPX will auto map based on the return_post_code; hence to exclude this parameter. |
|  |  | return_post_code | string | N | Return Post Code Data constraints:max length:6 |
|  |  | return_longitude | string | N | return longitudeData constraints:max length:16 |
|  |  | return_latitude | string | N | Return latitudeData constraints:max length:16 |
|  |  | return_name | string | N | Return nameData constraints:max length:64 |
|  |  | return_phone | string | N | MY:Phone number for Return should have 9 - 11 digits if starts with 0 or 10 - 12 digits if starts with country code (60) |
|  |  | return_detail_address | string | N | the return detail addressData constraints:max length: 256 |

Examples of Request Parameters:
```
{
   "user_id": 219216466249141,
   "user_secret": "704c0865-eee7-47bd-9a76-a3bb73d60e8b",
   "orders": [
       {
           "base_info": {
               "service_type": 1
           },
           "sender_info": {
               "sender_city": "อำเภอเมืองบึงกาฬ",
               "sender_state": "จังหวัดบึงกาฬ",
               "sender_longitude": "100.992541",
               "sender_latitude": "15.870032",
               "sender_country": "TH",
               "sender_detail_address": "dashi",
               "sender_post_code": "38000",
           },
           "fulfillment_info": {

               "collect_type": 2,
           },
           "deliver_info": {
               "deliver_longitude": "100.992541",
               "deliver_latitude": "15.870032",
               "deliver_country": "TH",
               "deliver_detail_address": "sdfsdf",
               "deliver_post_code": "38000",
               "deliver_state": "จังหวัดบึงกาฬ",
               "deliver_city": "อำเภอเมืองบึงกาฬ"
           },
           "parcel_info": {
               "parcel_weight": 1,
               "parcel_item_name": "Clothing",
               "parcel_item_quantity": 1,
               "parcel_length": 1,
               "parcel_width": 1,
               "parcel_height": 1
           },
            "return_info": {
                "return_state": "", 
                "return_city": "",
                "return_post_code": "123456",
                "return_name": "return test", 
                "return_phone": "60987654321", 
                "return_detail_address": "1"
            }
       }
   ]
}
```
## Response :
| Parameters |  |  | Type | Description |
| ret_code |  |  | integer | Response code |
| message |  |  | string | Response message |
| data |  |  | object |  |
|  | orders |  | list |  |
|  |  | order_id | string | the id related to an order. |
|  |  | estimated_shipping_fee | number | Estimated Shipping Fee |
|  |  | basic_shipping_fee | number | Basic Shipping Fee |
|  |  | cod_service_fee | number | COD Service Fee |
|  |  | insurance_service_fee | number | Insurance Service Fee |
|  |  | vat_fee | number | Vat Fee |
|  |  | estimated_distance | number | Estimated Distance |
|  |  | voucher_shipping_fee | number | Voucher Shipping Fee |
|  |  | edt_max | integer | Max of Estimated Delivery Time |
|  |  | edt_min | integer | Min of Estimated Delivery Time |
|  | fail_list |  |  |  |
|  |  | ret_code | integer | error code |
|  |  | message | string | error message |
|  |  | debug_msg | string | message for debug |
|  |  | order_id | string | the id related to failed order. |

Examples of Response Parameters:
```
{
    "ret_code": 0,
    "message": "success. ",
    "data": {
        "orders": [
            {
                "order_id": "GW123456789",
                "estimated_shipping_fee": 23.2,
                "basic_shipping_fee": 29,
                "cod_service_fee": 0,
                "insurance_service_fee": 0,
                "vat_fee": 0,
                "estimated_distance": 0,
                "voucher_shipping_fee": 0
            }
        ],
        "fail_list": [
            {
                "ret_code": 13050,
                "message": "Check Order Failed.  ",
                "debug_msg": "order xxx check failed",
                "order_id": "GW12345690"
            }
        ]
    }
}
```

