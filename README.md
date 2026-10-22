# Kedai Minyak Aura Saraf (PHP + MySQL)

Sistem e-dagang ringkas untuk Warisan Maktok Empire. PHP native (procedural) + PDO + MySQL/MariaDB.
Kedai guna CSS sendiri; panel admin guna Tailwind CDN.

## Ciri
- Halaman jualan + troli + borang pesanan (harga & stok dikira semula di server)
- Bayaran: **FPX Billplz (API + callback, sah automatik)**, transfer/DuitNow QR + muat naik resit, atau urus melalui WhatsApp
- Produk: tambah / sunting / sorok / padam, gambar, SKU, harga asal, susunan
- Stok: masuk / keluar / pelarasan, no. batch, tarikh luput, kos seunit, pembekal, sejarah penuh
  - Stok **ditolak automatik** bila pesanan Dibayar, **dipulangkan** bila Batal (idempotent, dalam transaction)
- Pesanan: Baru → Dibayar → Dihantar (kurier + tracking) → Selesai / Batal, nota dalaman, pautan WhatsApp pelanggan
- Halaman status pesanan untuk pelanggan (pautan bertoken)
- Papan pemuka: jualan bulan ini, stok rendah, batch hampir luput
- Keselamatan: prepared statements, CSRF, `password_hash`, had cubaan login (5 kali / 15 minit), sesi HttpOnly + SameSite,
  semakan MIME fail muat naik, resit disimpan **di luar web root**, tiada PHP boleh jalan dalam `uploads/`, honeypot + had kadar pesanan

## Struktur
```
aurasaraf_app/        <- LUAR web root (config, logik, skema, resit)
  config.sample.php   -> salin jadi config.php
  database/schema.sql
  storage/receipts/
public_html/          <- web root
  index.php checkout.php order.php payment-return.php payment-callback.php setup.php
  admin/  assets/  uploads/
```
`public_html` memuatkan `../aurasaraf_app/bootstrap.php`, jadi dua folder ini mesti **bersebelahan**.

## Pasang di XAMPP
1. Letak folder projek di `htdocs/aurasaraf/` (mengandungi `aurasaraf_app` dan `public_html`).
2. phpMyAdmin: cipta database `aurasaraf` (utf8mb4_unicode_ci), import `aurasaraf_app/database/schema.sql`.
3. Salin `config.sample.php` → `config.php`. `base_url` = `http://localhost/aurasaraf/public_html`.
4. Buka `/setup.php`, cipta admin, kemudian **padam `setup.php`**.
5. Admin → Stok → rekod "Stok masuk" (produk bermula dengan stok 0). Admin → Tetapan → isi bank / WhatsApp.

## Deploy ke cPanel (ServerFreak)
1. **MySQL Databases**: cipta database + user, beri ALL PRIVILEGES. (Nama akan ada prefix, cth. `user_aurasaraf`.)
2. **phpMyAdmin**: import `schema.sql`.
3. **Select PHP Version / MultiPHP**: PHP 8.1 atau lebih baharu. Extension: `pdo_mysql`, `curl`, `fileinfo`, `mbstring`.
4. Muat naik (File Manager, SFTP atau Git melalui SSH):
   - `aurasaraf_app/` → `/home/USER/aurasaraf_app/`
   - **isi** `public_html/` → `/home/USER/public_html/`
   - Addon domain: pastikan `aurasaraf_app` berada satu aras di atas document root domain tersebut.
5. `config.php`: `base_url` = `https://domainanda.com.my`, maklumat DB, kunci Billplz. `debug` = false.
6. Pastikan AutoSSL aktif (`.htaccess` memaksa HTTPS).
7. Folder boleh tulis (755): `public_html/uploads/`, `public_html/uploads/products/`, `aurasaraf_app/storage/receipts/`.
8. Buka `/setup.php` → cipta admin → **padam `setup.php`**.

## Billplz
1. Uji dahulu di **sandbox**: daftar di https://www.billplz-sandbox.com (akaun berasingan daripada live), cipta *Collection*.
2. Isi `secret_key` (Settings → Keys & Integration → API Secret Key) dan `collection_id` dalam `config.php`, `sandbox` = true.
3. Buat pesanan ujian. Aliran: `POST /v3/bills` → pelanggan bayar → Billplz panggil `payment-callback.php` → sistem sahkan melalui
   `GET /v3/bills/{id}` → pesanan jadi **Dibayar** dan stok ditolak. Pelanggan dibawa balik ke `payment-return.php`.
4. Callback perlukan URL awam (HTTPS). Di localhost callback tidak sampai, tetapi `payment-return.php` dan butang
   "Semak bayaran di Billplz" dalam admin tetap mengesahkan bayaran.
5. Bila lulus ujian: tukar ke kunci dan collection **live** dan `sandbox` = false.
- Parameter POST/GET daripada gateway **tidak dipercayai**; status hanya diterima selepas disahkan dengan API (bil `paid`,
  collection dan `reference_1` sepadan), dan jumlah dibayar mesti ≥ jumlah pesanan.
- Saluran bayaran (FPX, kad, e-wallet) dan siapa tanggung caj ditetapkan pada *Collection* di papan pemuka Billplz.

## Senarai semak sebelum live
- [ ] `setup.php` dipadam
- [ ] `debug` = false, `sandbox` = false, kunci live
- [ ] HTTPS aktif, `config.php` tidak berada dalam Git
- [ ] Stok sebenar direkod, gambar produk dimuat naik
- [ ] No. SSM / MAL / KKLIU diisi di Tetapan jika berkenaan (dipapar di footer)
- [ ] Backup database berkala (cPanel → Backup, atau cron `mysqldump`)

## Nota pematuhan
Halaman jualan sengaja tidak memaparkan dakwaan perubatan, lencana "KKM/MAL/GMP" atau testimoni. Sahkan status pendaftaran
produk (NPRA) dan kelulusan iklan (KKLIU) sebelum menambah kandungan sebegitu. Sediakan notis privasi PDPA kerana laman
mengutip nama, telefon dan alamat.
