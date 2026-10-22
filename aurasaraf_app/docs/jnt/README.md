# J&T Express Open Platform (Malaysia) — ringkasan

Sumber: https://ylopen.jtexpress.my/apiDoc/index (laman SPA). Teks mentah setiap halaman
(kebanyakannya bahasa Cina) diekstrak ke `raw/`. Disahkan terhadap sandbox pada 2026-10-22.

## Hos
- Sandbox: `https://demoopenapi.jtexpress.my/webopenplatformapi/api/{path}`
- Live:    `https://ylopenapi.jtexpress.my/webopenplatformapi/api/{path}`

## Permintaan
- `POST`, `application/x-www-form-urlencoded`, satu medan `bizContent` = rentetan JSON.
- Header: `apiAccount`, `timestamp` (milisaat), `digest`.
- `digest = base64(md5(bizContent + privateKey))` — md5 bait mentah, bukan hex.
- Dalam bizContent: `customerCode` dan `password` = `UPPER(MD5(kata laluan biasa + "jadada369t3"))`.
  (Satu halaman dokumen menyebut "jadada236t2" — itu SALAH; data ujian rasmi
  `Sfx6H8d4 -> AA7EDDC3B82704CA3717E88E67A3CAF1` hanya sepadan dengan "jadada369t3".)
- Respons: `code` 1 (int atau string) = berjaya; selain itu baca `msg`.

## Kelayakan
- Sandbox (awam, sama untuk semua): apiAccount `640826271705595946`,
  privateKey `8e88c8477d4e4939859c560192fcafbc`, customerCode `ITTEST0001`, password `Sfx6H8d4`.
- Live: apiAccount/privateKey di konsol (Pusat Peribadi) selepas pengesahan syarikat (1–3 hari);
  customerCode + kata laluan diberi cawangan J&T. Setiap antara muka perlu "联调" (lulus ujian)
  dan "申请上线" (mohon live) di konsol; antara muka tolakan perlu URL pengeluaran.

## Antara muka yang digunakan
| Path | Guna | Nota |
|---|---|---|
| `order/addOrder` | Tempah | actionType `add`, serviceType `1` pickup / `6` drop-off, expressType `EZ`, payType `PP_PM`/`PP_CASH`/`CC_CASH`, sender/receiver {name, phone, countryCode `MYS`, address, postCode} — negeri/bandar/kawasan dipeta dari poskod. items[], packageInfo {packageQuantity, weight kg, packageValue, goodsType `ITN8` parcel}. Pulang billCode, sortingCode, totalFreight. **txlogisticId yang sama memulangkan billCode yang sama, walaupun selepas batal.** |
| `order/printOrder` | Label | txlogisticId + billCode. Pulang `base64EncodeContent` (PDF) atau `urlContent` (pautan PDF). Satu label setiap panggilan. |
| `order/getOrders` | Semak pesanan | id tidak wujud = code 1 tanpa data (digunakan untuk uji kelayakan). |
| `order/cancelOrder` | Batal | txlogisticId + billCode + reason. 999002010 = status tidak boleh dibatal. |
| `logistics/trace` | Jejak | billCode → data {billCode, details[{scanTime, scanTypeCode, scanType, desc…}]}. |
| `trace/subscribe` | Langgan tolakan | list[{traceNode "10&20&30&94&100&110&172&173&200", billCode}]. |
| `spmComCost/getComCost` | Anggaran kos | sender/receiver {prov, city, area, address}, weight kg → data.totalPrice (RM). **Akaun ujian tiada kebenaran (145003012)** — perlu dibuka oleh J&T. |
| (tolakan) statusFeedback | Webhook | J&T POST borang `bizContent` = [{billCode, txlogisticId, details[]}] + header digest; jawab `{"code":"1","msg":"success","data":"SUCCESS"}`. Formula digest tolakan tidak dinyatakan. |

## scanTypeCode
10 diambil · 20 keluar hab · 30 tiba hab · 94 sedang dihantar · 100 diterima · 110 masalah ·
172 dipulangkan · 173 dipulangkan ke pengirim · 200 diserah di kaunter ·
300 rosak · 301 hilang · 302 dilupus · 303 ditolak · 304 ditahan kastam · 305 tamat tempoh · 306 dilupus (rentas) ·
700 tiba di stesen · 701 notis ambil · 702 diambil di stesen · 703 tidak diambil, dipulangkan · 704 serahan stesen ·
400–405 kastam.
