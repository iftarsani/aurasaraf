
Warning: Undefined array key 3 in C:\Users\iftar\AppData\Local\Temp\claude\C--xampp-htdocs-aurasaraf\e00774f5-64d1-4ada-8949-41c516e2cc9a\scratchpad\dump.php on line 2
# API authentication guide
## Get app-ID and app-Secret
The open platform assigns a unique set of app-id and app-secret to each accessed app.

For example：
app-id = 100000
app-secret = H25HY53GO4BA2GQ
## 
## Generates the signature content check-sign
The following details are needed to generate check-sign
app-id, app-secret, timestamp (in seconds, type: int64), random-num (type: int64) set in the request header
payload in JSON format set in the request body (need to be converted to a string before input to GenerateCheckSign)
The check-sign signature parameter is implemented via HMAC-SHA256 algorithm.
The process of user generated check sign needs to be the same as the example below.
The unit of timestamp needs to be second not millisecond.
The following is an example of generating check-sign in Go/Python3/Node.js/Java/PHP/C# language:
```
package main

import (
    "bytes"
    "crypto/hmac"
    "crypto/sha256"
    "encoding/hex"
    "encoding/json"
    "fmt"
    "io/ioutil"
    "math/rand"
    "net/http"
    "strconv"
    "time"
)

const _checkSignFormatStr = "%d_%d_%d_%s"

type HttpReqData struct {
    UserId      uint64 `json:"user_id"`
    UserSecret  string `json:"user_secret"`
    ServiceType uint32 `json:"service_type"`
}

func main() {
    var appId = uint64(100000)
    var appSecret = "H25HY53GO4BA2GQ"
    var timestamp = time.Now().Unix()
    rand.Seed(timestamp)
    var randomNum = rand.Int63()
    reqData := &HttpReqData{
	UserId:      239404781503925,
    	UserSecret:  "85f0d570-a265-4d9d-857e-b30aa57c4fbe",
    	ServiceType: 1,
    }
    payload, err := json.Marshal(reqData)
    if err != nil {
	return
    }
    // Generate check sign
    checkSign, err := GenerateCheckSign(appId, appSecret, timestamp, randomNum, payload)
    if err != nil {
	return
    }
    fmt.Println(checkSign)

    // Do http request
    httpReq, err := http.NewRequest(http.MethodPost, "https://test-stable.spx.vn/open/api/v1/order/get_pickup_time", bytes.NewReader(payload))
    if err != nil {
	return
    }
    httpReq.Header.Set("app-id", strconv.FormatUint(appId, 10))
    httpReq.Header.Set("check-sign", checkSign)
    httpReq.Header.Set("timestamp", strconv.FormatInt(timestamp, 10))
    httpReq.Header.Set("random-num", strconv.FormatInt(randomNum, 10))
    httpResp, err := http.DefaultClient.Do(httpReq)
    if err != nil {
	return
    }
    defer httpResp.Body.Close()
    body, err := ioutil.ReadAll(httpResp.Body)
    if err != nil {
	return
    }
    print(string(body))
}

func GenerateCheckSign(appId uint64, secret string, timestamp, random int64, payload []byte) (string, error) {
    originalValue := fmt.Sprintf(_checkSignFormatStr, appId, timestamp, random, payload)
    h := hmac.New(sha256.New, []byte(secret))
    _, err := h.Write([]byte(originalValue))
    if err != nil {
	return "", err
    }
    checkSign := hex.EncodeToString(h.Sum(nil))
    return checkSign, nil
}
import hmac
import json
import requests
import time
import random
import hashlib

# Set up request body
data = {
    "user_id": 239404781503925,
    "user_secret": "85f0d570-a265-4d9d-857e-b30aa57c4fbe",
    "service_type": 1
}

# Set up request headers, headers need to be str, so modify to str
app_id = str(100000)
app_secret = "H25HY53GO4BA2GQ"
timestamp = str(int(time.time()))
random_num = str(random.randint(1, int(timestamp)))

# Generate check sign
string_to_hash = "{0}_{1}_{2}_{3}".format(app_id, timestamp, random_num, json.dumps(data))
check_sign = hmac.new(app_secret.encode('utf-8'), string_to_hash.encode('utf-8'), digestmod=hashlib.sha256).hexdigest()
print(check_sign)

headers = {
    "app-id": app_id,
    "check-sign": check_sign,
    "timestamp": timestamp,
    "random-num": random_num
}

# Send POST request
url = "https://test-stable.spx.vn/open/api/v1/order/get_pickup_time"
response = requests.post(url, headers=headers, json=data)

# Print response status code and content
print(response.status_code)
print(response.content)
const crypto = require("crypto")
const appId = 100000
const appSecret = 'H25HY53GO4BA2GQ'
const timestamp = parseInt(Date.now() / 1000)
const randomInt = Math.round(Math.random()*timestamp);

const generateCheckSign = (payload) => {
    const originalValue = [appId, timestamp, randomInt, JSON.stringify(payload)].join('_')
    const hmac = crypto.createHmac('sha256', appSecret)
    hmac.update(originalValue)

    return hmac.digest('hex')
}

const httpReqData = {
    "user_id": 239404781503925,
    "user_secret": "85f0d570-a265-4d9d-857e-b30aa57c4fbe",
    "service_type": 1,
}

console.log(`Timestamp: ${timestamp}`)
console.log(`Random Int: ${randomInt}`)
console.log(`Payload: ${JSON.stringify(httpReqData)}`)
console.log(`CheckSign: ${generateCheckSign(httpReqData)}`)
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.util.Random;
import com.google.gson.Gson;
import com.google.gson.annotations.SerializedName;

class HttpReqData {
    @SerializedName("user_id")
    private long userId;
    @SerializedName("user_secret")
    private String userSecret;
    @SerializedName("service_type")
    private int serviceType;

    // Getters and Setters (or use Lombok annotations)
    public long getUserId() {
        return userId;
    }

    public void setUserId(long userId) {
        this.userId = userId;
    }

    public String getUserSecret() {
        return userSecret;
    }

    public void setUserSecret(String userSecret) {
        this.userSecret = userSecret;
    }

    public int getServiceType() {
        return serviceType;
    }

    public void setServiceType(int serviceType) {
        this.serviceType = serviceType;
    }
}

public class Main {
    private static final String CHECK_SIGN_FORMAT_STR = "%d_%d_%d_%s";

    public static void main(String[] args) {
        long appId = 100000;
        String appSecret = "H25HY53GO4BA2GQ";
        long timestamp = System.currentTimeMillis() / 1000;
        long randomNum = new Random().nextLong();

        HttpReqData reqData = new HttpReqData();
        reqData.setUserId(239404781503925L);
        reqData.setUserSecret("85f0d570-a265-4d9d-857e-b30aa57c4fbe");
        reqData.setServiceType(1);

        // parse to json str, can use gson
        Gson gson = new Gson();
        String payload = gson.toJson(reqData);

        String checkSign;
        try {
            checkSign = generateCheckSign(appSecret, appId, timestamp, randomNum, payload);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
        System.out.println(checkSign);
    }

    private static String generateCheckSign(String secret, long appId, long timestamp, long random, String payload)
            throws Exception {
        String originalValue = String.format(CHECK_SIGN_FORMAT_STR, appId, timestamp, random, payload);
        Mac mac = Mac.getInstance("HmacSHA256");
        SecretKeySpec secretKey = new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256");
        mac.init(secretKey);
        byte[] signData = mac.doFinal(originalValue.getBytes(StandardCharsets.UTF_8));
        StringBuilder sb = new StringBuilder();
        for (byte item : signData) {
            sb.append(Integer.toHexString((item & 0xFF) | 0x100), 1, 3);
        }
        return sb.toString();
    }
}
<?php
$appid = 100192;
$appsecret = "106122402719a5800d92e8fc7eab16ac153fc9401299d69a37207914b3959997";
$timestamp = 1699422945;
$randNum = 564047;
$payload = '{"phone":"0826368193"}';

function generateCheckSign($payload, $timestamp, $randNum, $appid, $appsecret) {
	$string = sprintf("%d_%d_%d_%s", $appid, $timestamp, $randNum, $payload);
	$sign = hash_hmac('sha256', $string, $appsecret);
	return $sign;
}
echo generateCheckSign($payload, $timestamp, $randNum, $appid, $appsecret);
// the result should be 7067c7ee76f82ee23c752339a2415ce2b7eb6c21c247ade1abe02273b5c26827
?>
using System;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;

namespace CSharpDemo
{
    public class HttpReqData
    {
        public ulong UserId { get; set; }
        public string UserSecret { get; set; }
        public uint ServiceType { get; set; }
    }

    class Program
    {
        private const string _checkSignFormatStr = "{0}_{1}_{2}_{3}";

        static void Main(string[] args)
        {
            ulong appId = 100000;
            string appSecret = "H25HY53GO4BA2GQ";
            long timestamp = DateTimeOffset.Now.ToUnixTimeSeconds();
            Random rand = new Random((int)timestamp);
            long randomNum = rand.Next();

            var reqData = new HttpReqData
            {
                UserId = 239404781503925,
                UserSecret = "85f0d570-a265-4d9d-857e-b30aa57c4fbe",
                ServiceType = 1
            };

            string payload = JsonSerializer.Serialize(reqData);

            // Convert payload to snake case JSON string
            payload = ConvertToSnakeCase(payload);

            // Generate check sign
            string checkSign = GenerateCheckSign(appId, appSecret, timestamp, randomNum, payload);
            Console.WriteLine(checkSign);
        }

        static string GenerateCheckSign(ulong appId, string secret, long timestamp, long random, string payload)
        {
            string originalValue = string.Format(_checkSignFormatStr, appId, timestamp, random, payload);
            using (HMACSHA256 hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret)))
            {
                byte[] hashBytes = hmac.ComputeHash(Encoding.UTF8.GetBytes(originalValue));
                string checkSign = BitConverter.ToString(hashBytes).Replace("-", "").ToLower();
                return checkSign;
            }
        }

        static string ConvertToSnakeCase(string input)
        {
    	    string snakeCase = Regex.Replace(input, @"(\p{Ll})(\p{Lu})", "$1_$2").ToLower();
    	    return snakeCase;
	}
    }
}
```
## 
## Test your generate check-sign method:
We have provided a set of test data and the expected correct results should be returned. Please use the data below to check that your own method of generating check-sign produces the same results. 

Test data:
| parameter | data type | value |
| app-id | integer | 100000 |
| app-secret | string | H25HY53GO4BA2GQ |
| timestamp | integer | 1677918414 |
| random-num | integer | 926611981 |
| payload | string | note: Shown in the code block below. Please keep the content exactly the same, including the format. |

## payload value :
```
{"user_id":239404781503925,"user_secret":"85f0d570-a265-4d9d-857e-b30aa57c4fbe","service_type":1}
```

## Expected result (string):
”97e21b23940e4ddc96fb4d2474f02425353d2b0e23aee384f3f94c3d0b9ba17d”
## Note:
If the results are not the same you can troubleshoot in the following ways:
Check whether the data type is correct
Check whether the HMAC-SHA256 algorithm is used.
Check whether the format of the payload is consistent with that provided, line feeds and different spaces will lead to different generated results.
If you are using Windows OS for development and testing, please refer to the Windows version at the bottom.
## 
## For Windows development&testing environment:
When using Postman for interface testing, the request body includes newlines that are not explicitly displayed in addition to the visible characters entered by the user. The line break is parsed as \n on unix systems, and \r\n on windows systems. This results in the actual payload byte [] for the same request body with different content on different systems. In this case, the same encryption process must generate different check-sign.
So, below we also provide the expected results of generating signatures using windows system test. If your development environment is windows or you are using a windows operating system to test the check-sign algorithm, when your payload has line breaks and you copy the payload we provide into Postman, the line breaks will be represented as \r\n. In this case, using the test data above, the result generated by the standard algorithm should be:
## Expected result (string):
”97e21b23940e4ddc96fb4d2474f02425353d2b0e23aee384f3f94c3d0b9ba17d”

If your results are not the same as both of the above expected results, then you will need to follow our guidelines to check and revise your algorithm again.
## Note:
In order to eliminate the problems caused by the differences between different OS, you need to inform our staff if you use Windows for development & testing, and you are generating signatures by copying data and pasting it into a Unix OS to generate the result. For example: using an online website "https://xxx.com " to generate a signature (the website line break corresponding to the binary content of \n), and then put the signature results in Postman(here the request body line break is \r\n) to test API, so you will encounter the problem that the content and signature results do not match, the system will return check-sign verification failed in response. 
In this way, at the stage of debugging with the platform, we will provide a compatible cross-platform solution. But subsequently, for the sake of the uniformity of the protocol and the accuracy of the message and security considerations, we will assist you to solve the problem of inconsistency in line feeds between Unix and Windows. After that, the API calling live environment will only accept the unique check-sign that meets the specification.
## 
