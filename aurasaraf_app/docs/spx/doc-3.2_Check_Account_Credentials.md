
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 3.2 Check Account Credentials
This API is used to check whether account credentials provided are valid.

| Basic Info |  |
| HTTP URL | {{host}}/open/api/v1/account/verify |
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
| user_id | integer | Y | the user id to check |
| user_secret | string | Y | the user secret to check |

Examples of Request Parameters:
```
{
    "user_id": xxxxx,
    "user_secret": "xxxxx"
}
```
## Response :
| Parameters | Type | Description |
| ret_code | int | Response code |
| message | string | Response message |
| data | object |  |
|  | match_result | boolean | matched: true / unmatched:false  |

Examples of Response Parameters:
```
{
    "ret_code": 0,
    "message": "success",
    "data": {
            "match_result": true
     }
}
```

