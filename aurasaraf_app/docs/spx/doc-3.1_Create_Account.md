
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 3.1 Create Account
This API is used for create a new account by email or phone.
## 
| Basic Info |  |
| HTTP URL | {{host}}/open/api/v1/account/create |
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
| phone | string | Y | Phone number related to account.Data constraints:max length: 32 |
| email | string | N | Email.Data constraints:max length: 64 |

Examples of Request Parameters:
```
{
    "phone": "xxxxx",
    "email": "xxxxx"
}
```
## Response :

| Parameters | Type | Description |
| ret_code | int | Response code |
| message | string | Response message |
| data | object |  |
|  | user_id | integer | user id |
| user_secret | string | The user secret corresponding to the user id. |

Examples of Response Parameters:
```
{
    "ret_code": 0,
    "message": "success.",
    "data": {
        "user_id": 110592440436149,
        "user_secret": "d32e4ddc-c979-44a2-bf21-789a80c551d5"
    }
}
```

