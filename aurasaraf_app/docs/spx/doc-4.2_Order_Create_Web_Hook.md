
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 4.2 Order Create Web Hook
description for order create progress when using the Create Order V2 api to create orders., the access party can register a Webhook URL. When the order create progress changes, the SPX Open Service platform will send an HTTP Post request to the registered URL, and the parameters of the request are JSON data.
## Web Hook Url configuration
The access party needs to provide an external public network URL as the Web hook URL. When allocating app-id and app-secret, we will bind this URL to the APP and enable the Web hook push.
## Web Hook parameter description
| Parameter | Type | Description |
| batch_no | integer | the batch no of the order creation |
| task_status | integer | task status enumeration (1: New, 2: Waiting, 3: Running, 4: Done, 5: Failed) |
| description | string | task status (English description) |
| total_count | integer | total count of this batch orders |
| success_count | integer | success count of this batch orders |
| fail_count | integer | fail count of this batch orders |
| progress | string | order creation progress, percentage |

## Examples
Here are some examples of JSON data for order create progress.

```
{
    "batch_no": 207982800897461,
    "task_status": 3,
    "description": "Running",
    "total_count": 10,
    "success_count": 5,
    "fail_count": 1,
    "progress": "60.00%",
}
```
## 
## Web Hook authentication
SPX will digitally sign the parameters in the body with the key of the access party and provide in the check-sign attribute of the request header. The signature generation process will be the same as other API requests (refer to the Guide >API authentication > Generates the signature content check-sign in Introduction) .
After receiving the request, the access party can generate signature and compare it with the check-sign in the header for authentication. If matched, it is determined that the request was sent by SPX.
## Web Hook response
You will need to return 200 HTTP status code to indicate that you have received a Web hook. SPX will set a timeout period of 3 seconds. It is recommended to return 200 responses after receiving a Web hook request before processing the request.
## 
