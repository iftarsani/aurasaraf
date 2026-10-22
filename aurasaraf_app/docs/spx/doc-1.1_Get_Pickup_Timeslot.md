
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 1.1 Get Pickup Timeslot
This API is used for getting the available pickup time supported by the current service.

| Basic Info |  |
| HTTP URL | {{host}}/open/api/v1/order/get_pickup_time |
| HTTP Method | POST |
| Basic request parameter | Content-Type: application/ JSON, UTF-8 encoding |

## Request :
## Request header:

| Parameters | Type | Mandatory (Y/N) | Description |
| app-id | integer | Y | Issued by SPX Open platform, each APP is unique. |
| check-sign | string | Y | Used to verify the source security of requests. For details of how to generate, see API authentication description. |
| timestamp | integer | Y | The requested Unix timestamp, Unit: second |
| random-num | integer | Y | random numberData constraints:signed 64-bit integers |

## Request body:

| Parameters | Type | Mandatory (Y/N) | Description |
| user_id | integer | Y | Unique user identification. / Generated via 3.1 Create Account API or provided by open platform. |
| user_secret | string | Y | The user secret corresponding to the user id. / Generated via 3.1 Create Account API or provided by open platform. |
| service_type | integer | Y | The service type. / Optional value： / 1: standard service |

Examples of Request Parameters:
```
{
    "user_id": xxxxx,
    "user_secret":  "CSFyQ8_JST",
    "service_type": 1
}
```
## Response :
| Parameters | Type | Description |
| ret_code | integer | Response code / Data Type:unsigned 32-bit integers |
| message | string | Response message |
| data | object |  |
|  | date |  | string |  |
|  | pickup_time |  | string | date in timestamp format，can be used as request parameter in Create Order interface-->fulfillment_info-->pickup_time / Data Type:unsigned 32-bit integers |
|  | slots |  | object | Available pickup time of the day |
|  |  | pickup_time_range_id | integer | The id corresponding to the pickup time period, can be used as request parameter in 1.2 Create Order request body-->fulfillment_info-->pickup_time_range_id / Data Type:unsigned 32-bit integers |
|  |  | pickup_time_range | string | The pickup time period string，can be used as request parameter in 1.2 Create Order request body-->fulfillment_info-->pickup_time_range / Data Type:string |

Examples of Response Parameters（for TH）:
```
{
    "ret_code": 0,
    "message": "success. ",
    "data": [
        {
            "date": "05-08-2022",
            "pickup_time": 1659690000,
            "slots": [
                {
                    "pickup_time_range_id": 1,
                    "pickup_time_range": "10:00 - 19:00"
                }
            ]
        },
        {
            "date": "06-08-2022",
            "pickup_time": 1659776400,
            "slots": [
                {
                    "pickup_time_range_id": 1,
                    "pickup_time_range": "10:00 - 19:00"
                }
            ]
        },
        {
            "date": "07-08-2022",
            "pickup_time": 1659862800,
            "slots": [
                {
                    "pickup_time_range_id": 1,
                    "pickup_time_range": "10:00 - 19:00"
                }
            ]
        },
        {
            "date": "08-08-2022",
            "pickup_time": 1659949200,
            "slots": [
                {
                    "pickup_time_range_id": 1,
                    "pickup_time_range": "10:00 - 19:00"
                }
            ]
        },
        {
            "date": "09-08-2022",
            "pickup_time": 1660035600,
            "slots": [
                {
                    "pickup_time_range_id": 1,
                    "pickup_time_range": "10:00 - 19:00"
                }
            ]
        }
    ]
}
```

Examples of Response Parameters（for VN）:
```
{
    "ret_code": 0,
    "message": "success. ",
    "data": [
        {
            "date": "05-08-2022",
            "pickup_time": 1659690000,
            "slots": [
                {
                    "pickup_time_range_id": 1,
                    "pickup_time_range": "10h đến trước 19h"
                }
            ]
        },
        {
            "date": "06-08-2022",
            "pickup_time": 1659776400,
            "slots": [
                {
                    "pickup_time_range_id": 1,
                    "pickup_time_range": "10h đến trước 19h"
                }
            ]
        },
        {
            "date": "07-08-2022",
            "pickup_time": 1659862800,
            "slots": [
                {
                    "pickup_time_range_id": 1,
                    "pickup_time_range": "10h đến trước 19h"
                }
            ]
        }
    ]
}
```

