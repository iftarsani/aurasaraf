
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# Error-prone fields
## 1. tracking_no
When the request parameter has tracking_no_list or tracking_no, first make sure that tracking_no is the value returned by the create order interface, not the order_id passed in by yourselves when creating the order. The tracking_no string usually starts with SPX or CID (VN/TH/SG).
## 2. tracking_no_list
The following example puts 3 tracking_no in a string, the system will recognize a string as a tracking_no, resulting in an order number error and the relevant interface call returns a failure.
Wrong Example:
```
 "tracking_no_list": ["tracking_no_1,tracking_no_2,tracking_no_3"]
```

Correct Example:
```
"tracking_no_list": ["tracking_no_1","tracking_no_2","tracking_no_3"]
```

## 3. Parcel value (express_insured_value)
when “insurance_collection” value is “1(Yes)”, this field is mandatory. The value should less than 20000000 VND.
## 4.Payment role
For TH users, only 1(sender pay) is available. If you input the wrong payment role value, it will cause the failure of order creation and checking shipping fee
## 5.COD amount
When cod_collection=1, this param is mandatory and value must be greater than 0. Otherwise, it will cause the failure of order creation and checking shipping fee.
## 6. Address
Some areas do not support courier pickup or delivery services, which will result in the failure of your order-related operations.
