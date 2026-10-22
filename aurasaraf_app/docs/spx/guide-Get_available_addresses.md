
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# Get available addresses
In order to improve the efficiency and quality of the service, we will provide an excel file containing all the standard addresses supported by the latest service range of Shopee Xpress. 
This data is mainly used in address fields in the 1.2 create order API. It is recommended to use the address listed in the file as the input parameter for creating an order, so as to reduce the failure of placing an order caused by the address error or the service range of the selected address is not supported.
Through 5.1 Get Address file download link, you can get the download link of the latest address library excel file in real time.

## How to use the address file?
In create order request body, you have to input the address info both in sender_info and  deliver_info. 
The correspondence between the columns in the excel file and the fields for creating orders is as follows:

TH Example file
| for TH：State / City / PostCode |
| Data in excel | State | City | PostCode | Longitude | Latitude |
| จังหวัดขอนแก่น | อำเภออุบลรัตน์ | 40250 | 102.6676 | 16.7547 |
| API Corresponding Fields / Type: string | sender_state / deliver_state | sender_city / deliver_city | sender_post_code / deliver_post_code | sender_longitude / deliver_longitude | sender_latitude / deliver_latitude |
| Mandatory (Y/N) | Y | Y | Y | N | N |

VN Example file
| for VN：State / District / Ward |
| Data in excel | State | District | Ward | Longitude | Latitude |
| Nam Định | Huyện Trực Ninh | Xã Trực Tuấn | 106.2787 | 20.27511 |
| API Corresponding Fields / Type: string | sender_state / deliver_state | sender_city / deliver_city | sender_district / deliver_district | sender_longitude / deliver_longitude | sender_latitude / deliver_latitude |
| Mandatory (Y/N) | Y | Y | Y | N | N |

ID Example file
| for ID：Province / City / District / PostCode |  |
| Data in excel | Province | City | District | PostCode | Longitude | Latitude |
| JAWA TIMUR | KAB. MAGETAN | PANEKAN | 63352 | 111.305702 | -7.628713 |
| API Corresponding Fields / Type: string | sender_state / deliver_state | sender_city / deliver_city | sender_district / deliver_district | sender_post_code / deliver_post_code | sender_longitude / deliver_longitude | sender_latitude / deliver_latitude |
| Mandatory (Y/N) | Y | Y | Y | Y | N | N |

