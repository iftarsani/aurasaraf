
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 1.3 Track Order
This API is used for query details of orders (except information related to fees).
Note: At most 100 per batch.
Order information returned by this API may require a short time to become fully available after order creation.
If this API is called immediately after Create Order succeeds, especially within 1 second, some newly created orders may temporarily return incomplete information, such as empty sort_code_info, or appear in fail_list with ret_code=13251, such as "order id cannot be found" or "tracking no cannot be found".
Please wait for a short period and retry, for example retry after 1-3 seconds with backoff. Do not use Track Order immediately after Create Order as a strong consistency validation.

| Basic Info |  |
| HTTP URL | {{host}}/open/api/v1/order/batch_search_order |
| HTTP Method | POST |
| Basic request parameter | Content-Type: application/ JSON, UTF-8 encoding |

## Request :
## Request header:
| Parameters | Type | Mandatory (Y/N) | Description |
| app-id | integer | Y | Issued by SPX Open platform, each APP is unique. |
| check-sign | string | Y | Used to verify the source security of requests. For details of how to generate, see API authentication description. |
| timestamp | integer | Y | The requested Unix timestamp,  / Unit: second |
| random-num | integer | Y | random number / Data constraints: / signed 64-bit integers |

## Request body:
| Parameters | Type | Mandatory (Y/N) | Description |
| user_id | integer | Y | Unique user identification. / Generated via Create Account API or provided by open platform. |
| user_secret | string | Y | The user secret corresponding to the user id. / Generated via the Create Account API or provided by open platform. |
| tracking_no_list | list  | N | the list of tracking number (string) obtained from 1.2 Create order. / if you send batch_no to us, this param is optional |
| order_id_list | list | N | the list of order id(string), if you send batch_no to us, this param is optional |
| batch_no | integer | N | Batch number of these orders, you can get it from 1.6 Create Order V2 |

Examples of Request Parameters:
```
{
   "user_id": 277447632848309,
   "user_secret": "a51ca7b9-a4ef-49d0-aa9f-cc07ae49f9aa",
   "tracking_no_list": ["SPXTH026817968592", "SPXTH026817099535"],
   "order_id_list": ["123"],
   "batch_no": null
}
```
## Response :

| Parameters |  |  |  | Type | Description |
| ret_code |  |  |  | int | Response code |
| message |  |  |  | string | Response message |
| data |  |  |  | list | Order detail information |
|  | orders |  |  |  |  |
|  |  | tracking_no |  | string | tracking number |
|  |  | tracking_link |  | string | tracking link to query order tracking |
|  |  | order_id |  | string | Customer system order id |
|  |  | order_id_link |  | string | order id link to query order tracking |
|  |  | status |  | string | Current parcel status |
|  |  | status_code |  | string | Current parcel status code |
|  |  | base_info |  | object |  |
|  |  |  | service_type | integer | service type 1: standard service   |
|  |  | sender_info |  |  |  |
|  |  |  | sender_state | string | Sender province name |
|  |  |  | sender_city | string | Sender district name |
|  |  |  | sender_post_code | string | Sender post code |
|  |  |  | sender_longitude | string | sender longitude |
|  |  |  | sender_latitude | string | sender latitude |
|  |  |  | sender_name | string | sender name |
|  |  |  | sender_phone | string | sender phone |
|  |  |  | sender_detail_address | string | sender detail address |
|  |  | fulfillment_info |  |  |  |
|  |  |  | payment_role | integer | For TH：Only 1: sender paypayment role:1: sender pay2: receiver pay |
|  |  |  | collect_type | integer | pickup mode:1:pickup；2:drop off； |
|  |  |  | pickup_time | interger | Scheduled pickup time  |
|  |  |  | pickup_time_range_id | interger | Sequence number. |
|  |  |  | voucher_code | string | voucher_code |
|  |  | deliver_info |  |  |  |
|  |  |  | deliver_state | string | Deliver province name |
|  |  |  | deliver_city | string | Deliver district name |
|  |  |  | deliver_post_code | string | Deliver post code |
|  |  |  | deliver_longitude | string | deliver longitude |
|  |  |  | deliver_latitude | string | deliver latitude |
|  |  |  | deliver_name | string | deliver name |
|  |  |  | deliver_phone | string | deliver phone |
|  |  |  | deliver_detail_address | string | deliver detail address |
|  |  |  | deliver_instruction | string | remark |
|  |  | parcel_info |  |  |  |
|  |  |  | parcel_weight | number | Unit: kg |
|  |  |  | parcel_length | number | Unit: cm |
|  |  |  | parcel_width | number | Unit: cm |
|  |  |  | parcel_height | number | Unit: cm |
|  |  |  | parcel_item_name | string | item name |
|  |  |  | parcel_item_quantity | integer | Item Quantity |
|  |  |  | parcel_value | number | parcel_value |
|  |  | routes |  | list |  |
|  |  |  | status | string | route status |
|  |  |  | status_code | string | route status code |
|  |  |  | message | string | route message |
|  |  |  | timestamp | integer | route timestamp |
|  |  | edd_info |  |  |  |
|  |  |  | edd_max | integer | Max of Estimated Delivery Date |
|  |  |  | edd_min | integer | Min of Estimated Delivery Date |
|  | fail_list |  |  |  | Failed Track orders list |
|  |  | ret_code |  | integer | error code |
|  |  | message |  | string | error message |
|  |  | tracking_no |  | string | Failed tracking number |
|  |  | order_id |  | string | Failed order id |

Examples of Response Parameters:
```
{
	"ret_code": 0,
	"message": "success. ",
	"data": {
		"orders": [{
			"tracking_no": "SPXTH026817099535",
                        "tracking_link": "https://test.spx.co.th/track?SPXTH026817968592",
                        "order_id": "abc",
                        "order_id_link": "https://test.spx.co.th/track?abc",
			"status": "Pending Pickup",
			"status_code": "1001",
			"base_info": {
				"service_type": 1
			},
			"sender_info": {
				"sender_state": "จังหวัดกรุงเทพมหานคร",
				"sender_city": "เขตคลองเตย",
				"sender_district": "",
				"sender_post_code": "10110",
				"sender_longitude": "",
				"sender_latitude": "",
				"sender_name": "API Test",
				"sender_phone": "66898765532",
				"sender_detail_address": "32/23 Test"
			},
			"fulfillment_info": {
				"payment_role": 1,
				"cod_collection": 1,
				"cod_amount": 1,
				"insurance_collection": 1,
				"collect_type": 1,
				"pickup_time": 1676538000,
				"pickup_time_range_id": 0,
				"deliver_type": null,
			},
			"deliver_info": {
				"deliver_state": "จังหวัดบึงกาฬ",
				"deliver_city": "อำเภอเมืองบึงกาฬ",
				"deliver_district": "",
				"deliver_post_code": "38000",
				"deliver_longitude": "",
				"deliver_latitude": "",
				"deliver_name": "test",
				"deliver_phone": "66849876543",
				"deliver_detail_address": "test",
				"deliver_instruction": ""
			},
			"parcel_info": {
				"parcel_weight": 1,
				"parcel_length": 0,
				"parcel_width": 0,
				"parcel_height": 0,
				"parcel_item_name": "Clothing",
				"parcel_item_quantity": 1,
				"parcel_value": 1
			},
			"routes": [{
				"status": "Pending Pickup",
				"status_code": "1001",
				"message": "ผู้ส่งกำลังเตรียมพัสดุ",
				"timestamp": 1676520851
			}]
		}],
		"fail_list": [{
			"ret_code": 13251,
			"message": "order id cannot be found",
			"tracking_no": "",
                        "order_id": "123"
		},
                {
			"ret_code": 13251,
			"message": "tracking no cannot be found",
			"tracking_no": "SPXTH026817968592",
                        "order_id": ""
		}]
	}
}
```

