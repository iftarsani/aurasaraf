
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 2.2 Get Order Fee
This API is used for batch get actual shipping fee after order creation.
Note: At most 100 per batch.

| Basic Info |  |
| HTTP URL | {{host}}/open/api/v1/order/batch_get_asf |
| HTTP Method | POST |
| Basic request parameter | Content-Type: application/ JSON, UTF-8 encoding |

## Request :
## Request header:
| Parameters | Type | Mandatory (Y/N) | Description |
| app-id | integer | Y | Issued by SPX Open platform, each APP is unique. |
| check-sign | string | Y | Used to verify the source security of requests.For details of how to generate, see API authentication description. |
| timestamp | integer | Y | The requested Unix timestamp,  / Unit: second |
| random-num | integer | Y | random number / Data constraints: / signed 64-bit integers |

## Request body:
| Parameters | Type | Mandatory (Y/N) | Description |
| user_id | long | Y | Unique user identification.Generated via Create Account API or provided by open platform. |
| user_secret | string(128) | Y | The user secret corresponding to the user id.Generated via the Create Account API or provided by open platform. |
| tracking_no_list | list | Y | the list of tracking number ( string) obtained from 1.2 Create order |

Examples of Request Parameters:
```
{
    "user_id": 195216188605877,
    "user_secret": "6e1c50c0-93f2-4b66-affe-0c79f9dbe1a3",
    "tracking_no_list":["SPXTH022083013592"]
}
```
## Response :

| Parameters | Type | Description |
| ret_code | int | Response code |
| message | string | Response message |
| data | object |  |
|  | orders | list | order list |
|  | tracking_no | string | tracking number |
| order_id | string | order id |
| parcel_info |  | ActualParcelInfo |
|  | actual_weight | number | actual parcel weightUnit: kg |
| actual_length | number | actual parcel lengthUnit: cm |
| actual_width | number | actual parcel widthUnit: cm |
| actual_height | number | actual parcel heightUnit: cm |
| chargeable_weight | number | parcel chargeable weightUnit: kg |
| insurance_collection | integer | insurance collectionvalue：0: no1: yes |
| express_insured_value | number | express insured value |
| cod_collection | integer | To use COD or not value：0: no1: yes |
| cod_amount | number | cod amount |
| fee_info | object | Actual Fee Info |
|  | payment_status | integer | value:1.Created  2.Collected  3.Cancelled  4.Failed 5.Settled |
| payment_role | integer | value:1: sender pay2: receiver pay；For TH：Only 1: sender pay |
| actual_shipping_fee | number | actual shipping fee |
| return_shipping_fee | number | return shipping fee |
| basic_shipping_fee | number | basic shipping fee |
| insurance_service_fee | number | insurance service fee |
| cod_service_fee | number | cod service fee |
| deliver_info | object |  |
|  | actual_distance | number | actual distance |
| fail_list | list | get order fee failed list |
|  | ret_code | integer | error code |
| message | string | error message |
| tracking_no | string | the list of failed  tracking number |

Examples of Response Parameters:
```
{
    "ret_code": 0,
    "message": "success.",
    "data": {
        "orders": [
            {
                "tracking_no": "SPXTH022083013592",
                "parcel_info": {
                    "actual_weight": 2,
                    "actual_length": 10,
                    "actual_width": 50,
                    "actual_height": 20,
                    "chargeable_weight": 0,
                    "insurance_collection": 0,
                    "express_insured_value": 0,
                    "cod_collection": 0,
                    "cod_amount": 0
                },
                "fee_info": {
                    "payment_status": 1,
                    "payment_role": 1,
                    "actual_shipping_fee": 0,
                    "return_shipping_fee": 0,
                    "basic_shipping_fee": 0,
                    "insurance_service_fee": 0,
                    "cod_service_fee": 0
                },
                "deliver_info": {
                    "actual_distance": 0
                }
            }
        ],
        "fail_list": [{
                "ret_code": 13151,
                "message": "Get Actual Shipping Fee Failed. ",
                "tracking_no": "SPXTH022083013593"
            }
]
    }
}
```

