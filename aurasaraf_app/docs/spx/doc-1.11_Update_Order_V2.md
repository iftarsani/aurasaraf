
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 1.11 Update Order V2
This API is used for updating order information. Partner should use this API to replace the 1.9 Update Order API(Deprecated)
Instructions
1）One request can only update one order.
2）The AWB will need to be reprinted after order update.
3）All API fields need to be passed, and fields will be overwritten as zero values when fields are not passed.
4）The request params are divided into several update info types
- 1-sender info
- 2-delivery info
- 3-parcel info
- 4-fulfillment info
- 5-shipping method info
refer to request body param tables column "Update Info Type"
5) The respond param "update_order_result_list" means: In one update order request, which fields were updated failed. Partner should retry update order again if partners want the failed fields to be updated successfully

| Basic Info |  |
| HTTP URL | {{host}}/open/api/v2/order/update_order |
| HTTP Method | POST |
| Basic request parameter | Content-Type: application/ JSON, UTF-8 encoding |

## Request :
## Request header:

| Parameters | Type | Mandatory (Y/N) | Description |
| app-id | integer | Y | Issued by SPX Open platform, each APP is unique. |
| check-sign | string | Y | Used to verify the source security of requests. / For details of how to generate, see API authentication description. |
| timestamp | integer | Y | The requested Unix timestamp,  / Unit: second |
| random-num | integer | Y | random number / Data constraints: signed 64-bit integers |

## Request body:

| Parameters |  |  |  | Type | Mandatory (Y/N) | Description |
| user_id |  |  |  | integer | Y | Unique user identification.Generated via 3.1 Create Account API or provided by open platform. |
| user_secret |  |  |  | string | Y | The user secret corresponding to the user id.Generated via 3.1 Create Account API or provided by open platform. |
| order |  |  |  | object | Y | Order details.Data constraints:Maximum order quantity is 100 |
|  | tracking_no |  |  | string | Y | SPX tracking number |
|  | order_id |  |  | string | N | Customer system order id.Data constraints:max length:32 |
|  | base_info |  |  |  |  |  |
|  |  | service_type |  | integer | Y | Service typeOptional value：1: standard service(Modification is not supported, the original value needs to be passed)    |
|  | sender_info |  |  |  |  |  |
|  |  | sender_state |  | string | Y / Can be updated before Pickup/Dropoff | State nameData constraints:max length:64Example:For TH: “Province name” e.g. จังหวัดกรุงเทพมหานคร; For VN: “Province name” e.g.TP. Hồ Chí Minh |
|  |  | sender_city |  | string | Y / Can be updated before Pickup/Dropoff | City nameData constraints:max length:64Example:For TH: “District name” e.g. เขตวัฒนา; For VN: “City name” e.g. Quận 1 |
|  |  | sender_post_code |  | string | for MY Y | sender post codeFor TH: MandatoryFor VN: OptionalData constraints:max length:32 |
|  |  | sender_longitude |  | string | N / Can be updated before Pickup/Dropoff | sender longitudeData constraints:max length:16 |
|  |  | sender_latitude |  | string | N / Can be updated before Pickup/Dropoff | sender latitudeData constraints:max length:16 |
|  |  | sender_name |  | string | Y / Can be updated before Pickup/Dropoff | sender nameData constraints:max length:64 |
|  |  | sender_phone |  | string | Y / Can be updated before Pickup/Dropoff | MY:Phone number for sender/receiver should have 9 - 11 digits if starts with 0 or 10 - 12 digits if starts with country code (60) |
|  |  | sender_detail_address |  | string | Y / Can be updated before Pickup/Dropoff | sender detail addressData constraints:max length:256 |
|  | fulfillment_info |  |  | object |  |  |
|  |  | payment_role |  | integer | Y | payment roleOptional value：1: sender pay2: receiver payFor TH: Only 1 is available(Modification is not supported, the original value needs to be passed) |
|  |  | collect_type |  | integer | Y | collect typeOptional value：1: pickup2: drop off(Modification is not supported, the original value needs to be passed) |
|  |  | pickup_time |  | integer | N  / Can be updated before Pickup/Dropoff | Expected pickup time(timestamp).Mandatory only when “collection type” is “pick up”, otherwise this field is optional.Note: 1. Available pick up time can be obtained via “1.1 Get the available pick up time” 2. It's better to check “1.1 Get the available pick up time” again before creating order, in order to avoid create failed by "over pick up cutoff time" |
|  |  | pickup_time_range_id |  | integer | N  / Can be updated before Pickup/Dropoff | pick up time slot id.Mandatory only when “collection type” is “pick up”, otherwise this field is optional.Note:  pick up time range id can be obtained via“1.1 Get the available pick up time”  |
|  | deliver_info |  |  |  |  |  |
|  |  | deliver_state |  | string | Y / Can be updated before Pickup/Dropoff | the deliver state nameData constraints:max length: 64Example:For TH: “Province name” e.g. จังหวัดกรุงเทพมหานคร; For VN: “Province name” e.g.TP. Hồ Chí Minh |
|  |  | deliver_city |  | string | Y / Can be updated before Pickup/Dropoff | the deliver city nameData constraints:max length: 64Example:For TH: “District name” e.g. เขตวัฒนา; For VN: “City name” e.g. Quận 1 |
|  |  | deliver_post_code |  | string | Y for MY | the deliver post codeFor TH: MandatoryFor VN: OptionalData constraints:max length:32 |
|  |  | deliver_longitude |  | string | NCan be updated before Pickup/Dropoff | deliver longitudeData constraints:max length:16 |
|  |  | deliver_latitude |  | string | NCan be updated before Pickup/Dropoff | deliver latitudeData constraints:max length:16 |
|  |  | deliver_name |  | string | YCan be updated before Pickup/Dropoff | the receiver’s nameData constraints:max length:64 |
|  |  | deliver_phone |  | string | YCan be updated before Pickup/Dropoff and before delivery | MY:Phone number for sender/receiver should have 9 - 11 digits if starts with 0 or 10 - 12 digits if starts with country code (60) |
|  |  | deliver_detail_address |  | string | YCan be updated before Pickup/Dropoff | the receiver’s detail addressData constraints:max length: 256 |
|  |  | deliver_instruction |  | string | NCan be updated before Pickup/Dropoff | receiver’s remarkData constraints:max length: 256 |
|  | parcel_info |  |  |  |  |  |
|  |  | parcel_weight |  | number | Y / Can be updated before Pickup/Dropoff | parcel weightUnit: kgParcel weight limit for VN: |
|  |  | parcel_length |  | number | N / Can be updated before Pickup/Dropoff | parcel lengthUnit: cmParcel length limit for VN: |
|  |  | parcel_width |  | number | N / Can be updated before Pickup/Dropoff | parcel widthUnit: cmParcel width limit for VN: |
|  |  | parcel_height |  | number | N / Can be updated before Pickup/Dropoff | parcel heightUnit: cmParcel height limit for VN: |
|  |  | parcel_item_name |  | string | Y / Can be updated before Pickup/Dropoff | parcel item nameData constraints:max length: 64 |
|  |  | parcel_item_quantity |  | integer | Y / Can be updated before Pickup/Dropoff | Item quantity |
|  |  | parcel_value |  | number | Y  | parcel_value |
|  |  | item_list |  | list | N |  |
|  |  |  | item_id | integer | N | if you already have an item, you can pass the item_id, then the others field will be automatically filled |
|  |  |  | item_name | string | Y / Can be updated before Pickup/Dropoff | item name |
|  |  |  | item_weight | string | Y (mandatory only when “allow partial delivery” = yes) / Can be updated before Pickup/Dropoff and before delivery | item weight |
|  |  |  | item_price | string | Y (mandatory only when “allow partial delivery” = yes and cod_collection = yes) / Can be updated before Pickup/Dropoff and before delivery | item price |
|  |  |  | item_picture | string | N / Can be updated before Pickup/Dropoff | item picture |
|  |  |  | item_quantity | integer | N / Can be updated before Pickup/Dropoff and before delivery | item quantity, default value 1 |

```
{
   "user_id": 219216466249141,
   "user_secret": "704c0865-eee7-47bd-9a76-a3bb73d60e8b",
   "order": {
           "tracking_no": "SPXTH026817968592",           
           "order_id": "GW123456789",
           "base_info": {
               "service_type": 1
           },
           "sender_info": {
               "sender_city": "อำเภอเมืองบึงกาฬ",
               "sender_state": "จังหวัดบึงกาฬ",
               "sender_longitude": "100.992541",
               "sender_latitude": "15.870032",
               "sender_address_id": "5274296120341751",
               "sender_country": "TH",
               "sender_name": "cc",
               "sender_detail_address": "dashi",
               "sender_post_code": "38000",
               "sender_phone": "660898765432"
           },
           "fulfillment_info": {
               "payment_role": 1,
               "collect_type": 2
           },
           "deliver_info": {
               "deliver_longitude": "100.992541",
               "deliver_latitude": "15.870032",
               "deliver_country": "TH",
               "deliver_detail_address": "sdfsdf",
               "deliver_name": "1232",
               "deliver_post_code": "38000",
               "deliver_phone": "6676112222",
               "deliver_state": "จังหวัดบึงกาฬ",
               "deliver_city": "อำเภอเมืองบึงกาฬ",
               "deliver_instruction": "sdfsdf"
           },
           "parcel_info": {
               "parcel_weight": 1,
               "parcel_item_name": "Clothing",
               "parcel_item_quantity": 1,
               "parcel_length": 1,
               "parcel_width": 1,
               "parcel_height": 1,
               "item_list": [
                    {
                        "item_id": 1,
                        "item_name": "xxx",
                        "item_weight": "1.1",
                        "item_price": "1.1",
                        "item_picture": "https://www.123.com",
                        "item_quantity": 1
                    }
                ]
           }
       }
}
```
## Response :

| Parameters |  | Type |  | Description |
| ret_code |  | int | int | Response code |
| message |  | string | string | Response message |
| data |  | object | object |  |
|  | order_id |  | string | Customer system order id |
|  | order_id_link |  | string | order id link to query order tracking |
|  | tracking_no |  | string | SPX tracking number |
|  | tracking_no_link |  | string | tracking no link to query order tracking |
|  | r_first_sort_code |  | string | Delivery sort code  (Optional return. If you need this data, please request it from us in advance.) |
|  | r_third_sort_code |  | string | Delivery zone ID (Optional return. If you need this data, please request it from us in advance.) |
|  | return_first_sort_code |  | string | Return sort code (Optional return. If you need this data, please request it from us in advance.) |
|  | edt_max |  | integer | Max of Estimated Delivery Time |
|  | edt_min |  | integer | Min of Estimated Delivery Time |
|  | update_order_result_list |  | list |  |
|  |  | update_info_type | string | update info type / 1-sender info / 2-delivery info / 3-parcel info / 4-fulfillment info / 5-shipping method info |
|  |  | result_msg | string | fail message |
|  |  | message | int | Specific reasons for failure |
|  |  | diff_field_list | list | list all the changed field name |
|  | ret_code | integer |  | error code |

Examples of Response Parameters:
```
 "ret_code": 0,
  "message": "success. ",
  "data": {
    "order_id": "xx",
    "order_id_link": "xx",
    "tracking_no": "xx",
    "tracking_no_link": "xx",
    "r_first_sort_code": "xx",
    "r_third_sort_code": "xx",
    "r_fifth_sort_code":"xx",
    "return_first_sort_code": "xx",
    "destination_base_cod": "xx",
    "channel_code": "xx",
    "update_order_result_list": [
      {
        "update_info_type": "1",
        "result_msg": "succss",
        "result_code": 0,
        "diff_field_list": [
          "sender_name"
        ]
      },
      {
        "update_info_type": "2",
        "result_msg": "failed",
        "result_code": 12300,
        "diff_field_list": [
          "deliver_name"
        ]
      }
    ]
  }
}
```

