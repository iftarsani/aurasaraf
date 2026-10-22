
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 1.4 Cancel Order
This API is used for batch cancel order which have been successfully created.
NOTE: At most 100 per batch.

| Basic Info |  |
| HTTP URL | {{host}}/open/api/v1/order/batch_cancel_order |
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
| tracking_no_list | list  | Y | the tracking number ( string) obtained from 1.2 Create order |

Examples of Request Parameters:
```
{
    "user_id": 195216188605877,
    "user_secret": "6e1c50c0-93f2-4b66-affe-0c79f9dbe1a3",
    "tracking_no_list":["SPXTH0220830135921","SPXTH0220830135912"]
}
```
## Response :
| Parameters | Type | Description |
| ret_code | int | Response code |
| message | string | Response message |
| data | object |  |
|  | tracking_no_list |  | list (string) | the successful canceled tracking number |
|  | fail_list |  | list | Failed cancel orders list |
|  |  | ret_code | int | error code |
|  |  | message | string | error message |
|  |  | tracking_no | string | the failed tracking number list |

Examples of Response Parameters:
```
{
    "ret_code": 0,
    "message": "success.",
    "data": {
        "tracking_no_list": ["SPXTH0220830135912"],
        "fail_list": [
            {
                "ret_code": 13000,
                "message": "Cancel Order Failed. ",
                "tracking_no": "SPXTH0220830135921"
            }
        ]
    }
}
```

