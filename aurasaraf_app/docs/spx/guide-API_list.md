
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# API list
Click on "API" above to view the interface details or click here.

[IMG]

| API | Description | Calling relationships with other API |
| 1. Order (need the user-id and user-secret generated via 3.1 Create Account) |
| 1.1 Get pickup timeslot | Get the available pickup time supported by the current service. | - |
| 1.2 Create Order | Batch create order, return generated waybill number. At most 100 per batch | If the collect_type is pick up. You need to call 1.1 Get pickup timeslot first. |
| 1.3 Track Order | Batch query details of orders (except information related to fees). | You need to call 1.2 Create Order first to get the tracking number. |
| 1.4 Cancel Order | Batch cancel order. | You need to call 1.2 Create Order first to get the tracking number. |
| 1.5 Get AWB | Batch get waybill label, a maximum of 30 orders can be requested at a time. | You need to call 1.2 Create Order first to get the tracking number. |
| 2. Shipping fee (need the user-id and user-secret generated via 3.1 Create Account) |
| 2.1 Check Shipping Fee | Batch get estimated shipping fee before order creation. At most 100 per batch. | - |
| 2.2 Get Order Fee | Batch get actual shipping fee after order creation | You need to call 1.2 Create Order first to get the tracking number. |
| 3.Account |
| 3.1 Create Account | Create a new account by email or phone to generate user-id and user-secret. | The user-id and user-secret will be used for other APIs. |
| 3.2 Check Account Credentials | To check whether account credentials provided are valid  | - |

