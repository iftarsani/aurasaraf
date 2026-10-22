
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# 4.6 Shipping Fee Webhook
description for current order shipping fee when connecting with the SPX Open Service platform, the access party can register a Webhook URL. When the order shipping fee changes, the SPX Open Service platform will send an HTTP Post request to the registered URL, and the parameters of the request are JSON data containing the order shipping fee.
## Web Hook Url configuration
The access party needs to provide an external public network URL as the Web hook URL. When allocating app-id and app-secret, we will bind this URL to the APP and enable the Web hook push.
## Web Hook parameter description
## 
| Parameter | Type | Description |
| tracking_no | string | express tracking number |
| tracking_link | string |  |
| order_id | string | customer order id |
| order_id_link | string |  |
| id | string | the only ID for current request |
| timestamp | int | the timestamp when this status happened |
| latest_chargeable_weight | float | latest calculates the weight of shipping fee |
| latest_actual_weight | float | latest actual weight |
| latest_size_id | int |  |
| latest_chargeable_parcel_length | float |  |
| latest_chargeable_parcel_width | float |  |
| latest_chargeable_parcel_height | float |  |
| cod_amount | float | if COD bill, cod_amount field will have a value |
| latest_shipping_fee | float | latest shipping fee |
| latest_actual_basic_shipping_fee | float |  |
| latest_actual_cod_service_fee | float |  |
| latest_actual_insurance_service_fee | float |  |
| latest_actual_high_value_processing_fee | float |  |
| latest_actual_remote_area_fee | float |  |
| latest_actual_try_on_fee | float |  |
| latest_actual_partial_delivery_fee | float |  |
| latest_actual_collect_fee_service_fee | float | buyer reject, collect fee service fee |
| actual_return_shipping_fee | float | latest shipping fee of return bill |
| collect_fee_status | int | 1: not collected / 2. collected |
| collect_fee_amount | float | buyer reject, collect fee |

## Examples
Here are some examples of JSON data for order status.
```
{
	"tracking_no": "SPXVN04927934604C",
	"order_id": "ET20241226000OL",
	"timestamp": 1745578552,
	"latest_shipping_fee": 100
}
```
## Web Hook authentication
SPX will digitally sign the parameters in the body with the key of the access party and provide in the check-sign attribute of the request header. The signature generation process will be the same as other API requests (refer to the Guide >API authentication > Generates the signature content check-sign in Introduction) . After receiving the request, the access party can generate signature and compare it with the check-sign in the header for authentication. If matched, it is determined that the request was sent by SPX.
## Web Hook response
You will need to return 200 HTTP status code to indicate that you have received a Web hook. SPX will set a timeout period of 3 seconds. It is recommended to return 200 responses after receiving a Web hook request before processing the request.
Remark：If there are repeatedly rejected or timeout Web hook requests, the sending of Web hook requests will stop, which may result in the loss of some order status.
