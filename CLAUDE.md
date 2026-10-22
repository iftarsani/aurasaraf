# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Single-product e-commerce shop ("Kedai Minyak Aura Saraf", Warisan Maktok Empire). Procedural PHP 8.1+ with PDO and MySQL/MariaDB. No framework, no composer, no npm, no build step, no test suite. The storefront uses hand-written CSS; the admin panel uses Tailwind via CDN.

All user-facing strings, comments, and commit-worthy prose are in **Malay** — match that when adding features.

## Two-folder layout (non-negotiable)

```
aurasaraf_app/    <- OUTSIDE web root: config, business logic, schema, uploaded receipts
public_html/      <- web root: entry points, admin/, assets/, uploads/
```

Every public entry point starts with `require __DIR__ . '/../aurasaraf_app/bootstrap.php'` (admin pages use `/../../`). The two folders must stay siblings. `aurasaraf_app/.htaccess` denies all web access to it.

On the live server the folders are split — `aurasaraf_app/` goes to `/home/USER/aurasaraf_app/` and the *contents* of `public_html/` go to `/home/USER/public_html/` — so never introduce a path that assumes `aurasaraf_app` is reachable via URL.

## Local commands (XAMPP on Windows)

```bash
# Syntax-check a file / all files before considering a change done (no linter configured)
/c/xampp/php/php.exe -l public_html/checkout.php
find . -name "*.php" -exec /c/xampp/php/php.exe -l {} \;

# DB access
/c/xampp/mysql/bin/mysql.exe -u root aurasaraf -e "SHOW TABLES;"

# Re-import schema (CREATE TABLE IF NOT EXISTS + INSERT IGNORE, so it is safe to re-run)
/c/xampp/mysql/bin/mysql.exe -u root aurasaraf < aurasaraf_app/database/schema.sql

# Exercise app-layer code from the CLI (bootstrap skips session start when PHP_SAPI === 'cli')
cd public_html && /c/xampp/php/php.exe -r "require '../aurasaraf_app/bootstrap.php'; var_dump(settings_all());"
```

Apache and MySQL are started from the XAMPP control panel. Local PHP is 8.2.12. The CLI prints a harmless `Module "openssl" is already loaded` warning from a duplicated `extension=` line in XAMPP's `php.ini`.

URLs: storefront `http://localhost/aurasaraf/public_html`, admin `.../admin/login.php`, first-run admin creation `.../setup.php` (delete `setup.php` after use — it is guarded by an `admins` row count, not by auth).

`aurasaraf_app/config.php` is gitignored and must exist; copy `config.sample.php` if it is missing. `debug => true` is for local only.

## Architecture

**No router, no autoloader, no classes.** Each `.php` file under `public_html/` is a standalone entry point. `bootstrap.php` defines `APP_PATH`/`PUBLIC_PATH`, loads config into the `$CONFIG` global, starts a hardened session, defines `db()` (lazy PDO singleton, exceptions on, emulated prepares off), then unconditionally requires `helpers.php`, `auth.php`, `orders.php`, `billplz.php`. Everything is plain global functions — add new shared logic as a function in the matching `aurasaraf_app/` file, not a new class.

Pages follow one shape: bootstrap → `require_admin()` if admin → handle `is_post()` with `csrf_check()` first → mutate → `flash()` → `redirect()` (POST/redirect/GET always) → query → render inline HTML. Admin pages wrap output in `admin_header()`/`admin_footer()` from `admin/_layout.php`; storefront pages use `page_head()`/`page_foot()` from `public_html/_partials.php`.

**Config/settings split:** `config.php` holds infrastructure (DB, base_url, Billplz keys, timezone, debug) and is read-only at runtime. The `settings` table holds shop-owner-editable values (shop name, WhatsApp numbers, shipping rates, bank details, SSM/MAL/KKLIU numbers) accessed via `setting()`/`setting_set()` — `settings_all()` caches in a static, so pass `true` to refresh after writing within the same request.

## Invariants to preserve

These carry the correctness and security weight of the app — changes near them need care.

**Money and stock are recomputed server-side.** `checkout.php` accepts only product IDs and quantities from the form; prices, line totals, shipping (`shipping_fee()` using the state→region `SM`/`SS` map in `states()`), and the total are all re-derived from the database. Never trust a posted price or subtotal.

**Payment status is never taken from the gateway's POST/GET parameters.** Both `payment-callback.php` (server-to-server) and `payment-return.php` (browser) — plus the public `order.php` and the admin "verify" button — funnel through `bp_sync_order()` → `bp_verify()`, which re-queries Billplz's `GET /v3/bills/{id}` and additionally requires that the bill is `paid`, belongs to the configured collection, its `reference_1` equals the order number, and `paid_amount` is ≥ the order total. Orders are located by `orders.bill_code` (the Billplz bill ID). `payment-callback.php` deliberately has no CSRF check and returns HTTP 500 on verification failure so Billplz retries.

**Stock mutations must be transactional and idempotent.** `stock_move()` must only be called inside an open transaction — it does `SELECT … FOR UPDATE`, writes `products.stock`, and appends a `stock_movements` row with `balance_after`. `order_mark_paid()` and `order_cancel()` own their own transactions, re-select the order `FOR UPDATE`, and gate the stock adjustment on the `orders.stock_deducted` flag. Paid is reachable from three directions (callback, return, admin), so it must stay safe to call repeatedly. `stock_movements` is an append-only ledger — never update or delete rows to correct a mistake; record an `adjust` movement instead.

**Uploads.** `handle_upload()` sniffs the real MIME with `finfo` against an allowlist (`MIME_IMAGES`, `MIME_RECEIPT`) and renames to a random filename. Product images go to the public `uploads/products/`; customer receipts go to `aurasaraf_app/storage/receipts/` **outside the web root** and are served only through `admin/receipt.php`, which requires an admin and re-checks the MIME. `public_html/uploads/.htaccess` disables script execution — keep it. A product has one main `image` (used by cards, cart, hero) plus up to `PRODUCT_GALLERY_MAX` extra images in `products.gallery` (JSON list of filenames); `product_images()` returns main + extras for the slider on `product.php` and the fading hero on `index.php` (`assets/gallery.js`). `product_gallery()` drops any name that is not a plain filename.

**Every POST is CSRF-checked** with `csrf_check()` against the session token, and every form embeds `csrf_field()`. The only exception is the gateway callback. Customer order pages are authorised by a per-order `access_token` compared with `hash_equals()` — there are no customer accounts.

**Escape on output** with `e()`; the codebase renders HTML inline, so any echoed variable needs it.

Admin login (`admin_login()`) rate-limits via the `login_attempts` table (5 failures per 15 minutes, keyed on IP *or* username), always runs `password_verify` against a dummy hash to keep timing flat, regenerates the session ID on success, and rehashes when `password_needs_rehash()`. Checkout has its own defences: a `website` honeypot field and a 5-orders-per-10-minutes-per-IP cap.

## Order lifecycle

`new` / `pending_payment` → `paid` → `shipped` → `completed`, with `cancelled` reachable throughout. FPX orders start at `pending_payment`; transfer and WhatsApp orders start at `new`. Only `order_mark_paid()` deducts stock; only `order_cancel()` returns it. Labels and badge classes live in `order_statuses()` — add new statuses there and in the `orders.status` ENUM together.

Payment methods are offered conditionally: `fpx` only when `bp_enabled()` (both Billplz secret key and collection ID set), `transfer` only when a bank account or QR image is configured in settings, `whatsapp` always. `checkout.php` re-derives that allowlist server-side.

## Billplz notes

Sandbox (`www.billplz-sandbox.com`) and live are separate accounts with separate keys and collections; `config.php`'s `sandbox` flag switches the base URL. API v3 uses HTTP Basic auth (secret key as username, empty password). Amounts are sent and returned in **sen** (`amount` = ringgit × 100). The browser return arrives as `billplz[id]`/`billplz[paid]` GET params. The `payment_method` value stays `fpx` (no schema change); payment channels and who bears fees are configured on the Billplz collection, not in code. Callbacks need a public HTTPS URL, so on localhost the callback never arrives — `payment-return.php`, the public order page, and the admin "Semak bayaran" button all re-verify, which is what makes local testing work. All gateway traffic is recorded in `payment_logs` via `payment_log()`.

## SPX Express notes

`aurasaraf_app/spx.php` integrates the SPX Open API (Malaysia). A local copy of the API docs lives in `aurasaraf_app/docs/spx/`. Credentials (`app_id`/`app_secret` for the platform, `user_id`/`user_secret` for the shop's sender account) sit in `config.php` under `spx`. The sender address and packaging weight (`spx_sender_*`, `spx_pack_g`) are shared with J&T despite the prefix; `ship_sender_ready()` checks them. The drop-off/pickup choice is `spx_collect`. Each product's weight is stored in `products.weight_g`.

- **Signing:** every call is a POST of JSON with the headers `app-id`, `timestamp` (seconds), `random-num` and `check-sign`, where check-sign = `hash_hmac('sha256', "{app_id}_{ts}_{rand}_{body}", app_secret)`. `spx_request()` encodes the body once and signs that exact string, so keep it that way. Hosts: `test-stable.spx.com.my` (sandbox) or `spx.com.my` (live). Success is `ret_code === 0`. Every exchange is logged to `spx_logs`, and `user_secret` is stripped before logging.
- **Booking:** `spx_book()` takes `paid` orders, stores the tracking number with `courier = 'spx'`, and goes through `order_mark_shipped()` like a manual entry would. SPX's `order_id` is `order_no` on the first booking and `order_no-N` after a cancel, because SPX rejects a reused order_id (ret_code 13103). That counter is `orders.spx_attempt`. `spx_unbook()` cancels at SPX and moves the order back from `shipped` to `paid`.
- **Status:** `spx-webhook.php` checks `check-sign` over the raw body using the same formula (401 if it fails) and then calls `spx_apply_status()`. That function ignores updates older than `spx_status_at` and moves `shipped` → `completed` on 4001. Webhooks never arrive on localhost, so the admin "Semak status SPX" button and the customer order page (at most once every 30 minutes) re-query `batch_search_order` through `spx_sync()`.
- **Rates:** `shipping_calc()` in helpers.php is the single source for the checkout fee and the `shipping-quote.php` display. Free shipping (`free_min`) wins first. If not free, the fee comes from the main courier (`rate_courier` setting: `''` flat, `spx` or `jnt`, resolved by `rate_courier()`) plus `rate_markup`, falling back to the flat SM/SS rates whenever that courier fails. The main courier only sets the price; the admin can still ship any order with either courier. Quotes are cached for 12h in `shipping_quotes` keyed by courier + postcode + grams, and uncached lookups are capped at 20 per IP per 10 minutes per courier.
- **AWB:** `admin/spx-awb.php` asks for a fresh label link on every click, because links expire after 30 minutes or 10 opens. It allows at most 30 orders and only redirects to `*.spx.com.my`.

## J&T Express notes

`aurasaraf_app/jnt.php` mirrors spx.php for the J&T Open Platform (Malaysia). A summary of the docs is in `aurasaraf_app/docs/jnt/README.md` and the extracted raw pages are in `raw/`. Config lives under `jnt` in `config.php` (`api_account`, `private_key`, `customer_code`, plain `password`). When `sandbox` is true and those fields are empty, `jnt_cfg()` fills in J&T's public sandbox account (`JNT_SANDBOX_CREDS`), so the block alone enables test bookings. Settings: `jnt_service` (`6` drop-off / `1` pickup) and `jnt_paytype` (`PP_PM` / `PP_CASH`).

- **Signing:** every call is a form POST of `bizContent=<json>` with the headers `apiAccount`, `timestamp` (ms) and `digest = base64(md5(json . private_key, true))`. The `password` inside bizContent is `strtoupper(md5(plain . 'jadada369t3'))`; one doc page says `jadada236t2`, and that is wrong. Success is `code == 1`. Everything is logged to `jnt_logs` with the password stripped and label PDFs truncated.
- **Booking:** `jnt_book()` books orders one at a time (there is no batch endpoint), then calls `order_mark_shipped($id, 'jnt', billCode)` and subscribes the parcel (`trace/subscribe`). Reusing a txlogisticId returns the *same* billCode, even after a cancel. That makes a retry after a timeout safe, but a rebook after cancelling must use `order_no-N` (`orders.jnt_attempt`). `jnt_unbook()` cancels at J&T and moves the order back to `paid`.
- **Status:** `jnt_status_code` holds the latest `scanTypeCode`. 100 (delivered) and 702 (collected at a pickup station) move `shipped` → `completed`, and the problem codes email the owner. `jnt-webhook.php` applies the push content only if its digest matches the API formula. The push signature is undocumented, so any other push is ignored and triggers a throttled `logistics/trace` re-query (at most once per 2 minutes per parcel). The admin "Semak status J&T" button and the customer order page re-query via `jnt_sync()`.
- **Labels:** `admin/jnt-awb.php?id=` streams the PDF from `order/printOrder` (base64, or `urlContent` fetched server-side from `*.jtexpress.my` only). J&T returns one label per call, so `?ids=` shows a list of per-parcel links.
- **Rates:** `jnt_quote()` uses `spmComCost/getComCost`. The public sandbox account has no permission for it (145003012), so it returns null and checkout falls back to flat rates until J&T enables it on the shop's account.

## Compliance constraint

The storefront deliberately avoids medical claims and "KKM/MAL/GMP" badges. Do not add such content — NPRA product registration and KKLIU advertising approval would be required first. Registration numbers only appear in the footer when set in settings.

**Testimonials are built but gated, not banned.** `admin/testimonials.php` manages uploaded images and YouTube embeds at any time; the storefront section in `index.php` renders only when `testimonials_enabled()` is true — the `testimonials_show` setting equals `'1'` — and hides again when the list is empty. The switch defaults off so showing testimonials is always a deliberate act by the shop owner.

What decides whether KKLIU approval is needed is the *content*, not the existence of a testimonial: a photo of the product, packaging or "parcel arrived fast" is not a medicine advertisement, while "eased my knee pain" is a therapeutic claim under the Medicines (Advertisement and Sale) Act 1956 and needs Medicine Advertisements Board approval. Admin pages carry that warning whenever `kkliu_no` is empty — keep it there. `kkliu_no` is now display-only: it is rendered beside the section when set. Never bypass `testimonials_enabled()` to force the section on.

## Other agent config

A `~/.codex` directory exists on this machine. To import anything importable from it (MCP servers, slash commands, subagents, skills, instructions), reply `/import` to scan and list what is available, then `/import --yes=<digest>` using the digest from the scan output to apply the user-level items. If `/import` is unavailable on this surface, run `claude import` from a terminal.
