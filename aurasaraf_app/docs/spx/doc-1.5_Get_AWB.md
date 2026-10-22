
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 1.5 Get AWB
This API is used for Batch get waybill label.
Note: At most 100 per batch.

| Basic Info |  |
| HTTP URL | {{host}}/open/api/v1/order/batch_get_shipping_label |
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
| user_id | integer | Y | Unique user identification. / Generated via Create Account API or provided by open platform. |
| user_secret | string | Y | The user secret corresponding to the user id. / Generated via the Create Account API or provided by open platform. |
| tracking_no_list | list  | Y | the list of tracking number ( string) obtained from 1.2 Create order |

Be careful! You can only print orders that have not been cancelled. And the link is valid within half an hour, and can be opened up to 10 times.
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
| ret_code | integer | Response code |
| message | string | Response message |
| data | object |  |
|  | awb_link |  | string | Waybill label’s link |
|  | fail_list |  | list | Failed get awb orders list |
|  |  | ret_code | int | error code |
|  |  | message | string | error message |
|  |  | tracking_no | string | the list of failed  tracking number |

Examples of Response Parameters:
```
{
    "ret_code": 0,
    "message": "success.",
    "data": {
        "awb_link": "https://test.spx.co.th/shipment/order/open/order/batch_get_shipping_label?order_sn_list=TH225914294308519RT&access_token=e85f41c9-0cc5-47f1-a90d-a960d565342b",
        "fail_list": [
            {
                "ret_code": 13200,
                "message": "Get Shipping Label Failed. ",
                "tracking_no": "SPXTH022083013592"
            }
        ]
    }
}
```

