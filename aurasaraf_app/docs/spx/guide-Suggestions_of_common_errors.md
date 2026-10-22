
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# Suggestions of common errors
We have counted the types of errors that fail when creating orders using the open api and have provided a list of the 10 most frequent error codes and the corresponding workarounds here. 
Some of the error codes that are not listed can be found in the Error codes mapping for a description of the error codes and suggestions for troubleshooting.

1. You can get the error code in response body "fail_list"-->"ret_code" when fail in creating orders.
[IMG]

2. First find the corresponding error code and try to follow the solution suggestions and retry. If the error still occurs, please feel free to contact our platform staff and we will help you resolve it as soon as possible.

| Error code | Suggestions  |
| 10080 | Check “1.1 Get the available pick up time” to obtain available pick up date and time then use for the fields ”pickup_time” and “pickup_time_range_id”. Please use the latest returned results in case the time available for selection has elapsed. |
| 10077 | Please use the longitude and latitude related to the address which is provided in the Address file. (You can use 5.1 Get Address file download link to obtine the address file ). Or you don’t have to fill in the longitude and latitude, just input the address only. |
| 10078 | Please use the longitude and latitude related to the address which is provided in the Address file. (You can use 5.1 Get Address file download link to obtine the address file ). Or you don’t have to fill in the longitude and latitude, just input the address only. |
| 10021 | Please use the standard address provided in the Address file. (You can use 5.1 Get Address file download link to obtine the address file ).  |
| 10022 | Please check the address info in order creation request. The sender_address and deliver_address may be too close. |
| 10018 | Please use the standard address provided in the Address file. (You can use 5.1 Get Address file download link to obtine the address file ). If the error still occurs, please contact us. |
| 10019 | Please use the standard address provided in the Address file. (You can use 5.1 Get Address file download link to obtine the address file ). If the error still occurs, please contact us. |
| 10001 | Check the field "parcel_weight": x, / Parcel weight limit for VN: |
| 10003 | Check the field "cod_amount": x, / COD Amount limit for VN: |
| 10084/10085/10086 | Check the field "parcel_height","parcel_width","parcel_length": / For VN: |
| 10083 | Check the field "parcel_weight": x, / Max weight For VN: 15  kg,  / Volume (L*W*H) should not exceed 90,000 cm³ and at least 0 cm³ /  / For TH: 20kg,  / Volume (L*W*H) should not exceed 100,000 cm³ and at least 5 cm³ |

3. Related links and infomations:
- How to obtain the standard address: get available address.
- Get the available pickup time slot: get pickup timeslot
- Parcel limit:
| Parcel_info limit | TH | VN |
| parcel_weight | 0.001kg < weight < 20kg | 0kg < weight<  15kg |
| parcel_length | Max:100cm | Max: 60cm |
| parcel_width | Max:100cm | Max: 60cm |
| parcel_height | Max:100cm | Max: 60cm |
| Volume Limit: | Volume (L*W*H) should not exceed 100,000 cm³ and at least 5 cm³ | Volume (L*W*H) should not exceed 90,000 cm³ and at least 0 cm³ |
| cod_max_limit | Max: 36000 | Max: 20000000 |
| express_insured_value | 2000-50000 | 20000000 |

