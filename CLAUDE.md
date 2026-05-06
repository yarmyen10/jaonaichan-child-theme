# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

WordPress **child theme of Astra** (`Template: astra` in [style.css](style.css)) for `jaonaichan.com`. It bolts onto Astra to provide:

- A custom REST API (`bigboss-auth/v1/*`, `jaonaichan/v1/orders*`) consumed by the `bigboss.jaonaichan.com` admin dashboard.
- Seven custom WooCommerce order statuses for a **two-bill deposit flow** (`bill1` + `bill2`).
- Three page templates: Shop Login, custom Checkout, and a two-bill PromptPay-QR Thank-You page.
- An admin Dashboard template (multi-page: overview, orders, profile) using TailAdmin.
- A `developer` role and a Tailwind 4 + Alpine.js front-end pipeline.

## Commands

```bash
# Tailwind build (entry: src/input.css → assets/css/tailwind.css)
npm run build      # minified one-shot
npm run watch      # dev watch

# Compile .po → .mo translation files (uses gettext-parser via Node)
npm run build:mo   # runs scripts/build-mo.js

# FTP deployment
npm run deploy               # full tree upload
npm run deploy:dry           # full tree dry-run (no FTP)
npm run deploy:changed       # only git-modified files
npm run deploy:changed:dry   # modified files dry-run

# PHP deps
composer install
```

There is **no test suite, linter, or CI configured** in this repo. Node 24.14.1 is the version the readme pins.

## Environment variables

Loaded from `.env` (copy `.env.example`). PHP reads via `$_ENV[]`; Node reads via `dotenv`.

| Variable | Used by | Notes |
|---|---|---|
| `CHILD_THEME_NAME` | PHP | Theme slug |
| `TEXTDOMAIN_NAME` | PHP everywhere | **Never hard-code** — always `$_ENV['TEXTDOMAIN_NAME']` |
| `CHILD_THEME_VERSION` | PHP | Enqueued asset version |
| `FTP_HOST / USER / PASSWORD` | deploy.js | FTP credentials |
| `FTP_PORT` | deploy.js | Default `21` |
| `FTP_SECURE` | deploy.js | Default `false` |
| `FTP_REMOTE_PATH` | deploy.js | Remote root (required) |

deploy.js skips: `node_modules`, `.git`, `.env`, `deploy.js`, `package*.json`, `.gitignore`, `readme.md`, `CLAUDE.md`, `composer.lock`.

## Bootstrap & file-load order — important

[functions.php](functions.php) is the single entry point and the load order is load-bearing:

1. Loads `vendor/autoload.php`, then `.env` via `Dotenv::createImmutable`.
2. Explicitly requires `src/inc/cors.php` first.
3. Then walks each folder in `$inc_folders` in this order and `require_once`'s every `*.php` recursively:
   ```
   /src/api → /src/inc/i18n → /src/inc/helpers → /src/inc/enqueue → /src/inc/auth → /src/inc/woocommerce
   ```
4. Files inside a folder load in filesystem order — that's why helpers are numerically prefixed (`01-utils.php`, `02-redirect.php`, `03-page-templates.php`, `04-no-cache.php`). **Keep that prefix convention when adding helpers.**
5. Most files self-bootstrap by calling `ClassName::init()` (or `new ClassName()`) at the bottom of the file. Don't add a separate registration step.

## REST API surface

Two namespaces, both registered on `rest_api_init`:

**`bigboss-auth/v1/`** — [src/api/auth_api.php](src/api/auth_api.php)
- `GET /ping` — health check (returns `{ok: true}`), public.
- `POST /signin` — returns `{success, token, user: {id, username, email, display_name, roles}}`. `token` is a `wp_generate_auth_cookie()` string (not a JWT) — this is a legacy/fallback path. The frontend actually authenticates via the **JWT Authentication plugin** (`/jwt-auth/v1/token`).
- Both routes whitelisted via `rest_authentication_errors` priority 9999, alongside `/jwt-auth/v1/token{,/validate}`.
- A `jwt_auth_token_before_dispatch` filter injects `roles` + `role` into the JWT response.
- Per-IP brute-force throttle: `login_attempts_{ip}` transient, max 5 attempts / 15 min.

**`jaonaichan/v1/orders*`** — [src/api/orders_api.php](src/api/orders_api.php)
- All routes gated by `is_user_logged_in()` (`Orders_API::check_permission`).
- **Route ordering matters**: specific routes are registered *before* the wildcard `/orders/(?P<id>\d+)` so WP REST matches them correctly. Preserve that order when adding routes.

| Method | Route | Notes |
|---|---|---|
| GET | `/orders` | `?page`, `?per_page`, `?status`, `?create_date=dd/mm/yyyy`, `?create_date_m`, `?create_date_y` |
| GET | `/orders/products` | `?status` (required), `?format=grouped\|flat`, `?page`, `?per_page` (max 50) |
| GET | `/orders/products/bulk` | `?statuses=processing,completed` or `"all"`. Also POST with `{order_ids[], statuses?, page?, per_page?}` |
| GET | `/orders/{id}` | Full order detail |
| GET | `/orders/{id}/products` | Items + bill meta + items summary |
| PATCH | `/orders/{id}/status` | Body: `{status}` |
| PATCH | `/orders/{id}/note` | Body: `{note, is_customer_note?}` |
| PATCH | `/orders/{id}/customer` | Body: billing fields |
| PATCH | `/orders/{id}/bill/{1\|2}` | Body: `{status?, amount?, paid_at?}` → updates `_bill{N}_*` meta |

`format_order()` response shape: `{id, number, status, total, currency, date, payment_method, customer: {id, name, email, phone}, billing: {address}, bill1: {status, amount, paid_at}, bill2: {...}, items?: [...]}`.

`format_order_item()` shape: `{item_id, name, quantity, unit_price, subtotal, total, discount, variation[], product: {id, type, sku, price, stock, categories[], image: {thumbnail, medium, full}}}`.

**CORS** — [src/inc/cors.php](src/inc/cors.php) replaces WP's default handler on `rest_api_init` priority 15. Allowed origins are hard-coded: `localhost:5173`, `localhost:3000`, `jaonaichan.com`, `bigboss.jaonaichan.com`. Add new origins there, not via filter.

## Two-bill data model

Stored as WooCommerce post meta on the order; **there is no custom table**. For each bill *N* ∈ {1, 2}:

- `_bill{N}_status`  — `pending` | `paid` | `cancelled` (default `pending`)
- `_bill{N}_amount`
- `_bill{N}_paid_at`

On `woocommerce_new_order`, bill1 is initialised with `status=pending` and `amount=order_total` ([src/inc/woocommerce/bill-meta.php](src/inc/woocommerce/bill-meta.php)). All `_bill*` meta is deleted on permanent order delete.

Custom WC statuses ([src/inc/woocommerce/order-status.php](src/inc/woocommerce/order-status.php), registered on `init` priority 5 *after* textdomain at priority 1):
`wc-waiting-transfer`, `wc-pending-payment-{1,2}`, `wc-wait-verify-{1,2}`, `wc-paid-{1,2}`. Slug length matters — WP's `post_status` column is `varchar(20)`, so `wc-waiting-verification-{N}` (25 chars) was truncated and silently broke status matching; the renamed `wc-wait-verify-{N}` (16 chars) fits. Status badge colours are injected via `admin_head` inline CSS. HPOS compatibility is declared via `woocommerce_feature_status` filter.

**WooCommerce helpers:**
- [cart-clear.php](src/inc/woocommerce/cart-clear.php) — empties cart on `woocommerce_checkout_order_processed` (PromptPay is async, so WC doesn't auto-clear the cart).
- [checkout-fields.php](src/inc/woocommerce/checkout-fields.php) — makes all billing fields optional (priority 9999, very late).

## Page templates

[src/inc/helpers/03-page-templates.php](src/inc/helpers/03-page-templates.php) (`Theme_Page_Templates`) auto-discovers any PHP file under `/src/templates/` that has a `Template Name:` doc-block header, registers it in the WP page-template dropdown, and routes it via `template_include`. Drop a new template into `src/templates/...` with a `Template Name:` header — no other registration needed. The relative path is what's stored in `_wp_page_template` meta.

`/src/example/` is **not** in the scanned paths, so anything there won't be registered (it's a scratch area).

Registered templates:

| Template Name | File | Notes |
|---|---|---|
| Shop Login | [src/templates/auth/shop-login.php](src/templates/auth/shop-login.php) | `wp_signon()` form, Alpine.js loading state, nonce-verified POST |
| Jaonaichan Checkout | [src/templates/woocommerce/checkout.php](src/templates/woocommerce/checkout.php) | Requires login; empty cart → redirect to shop; Alpine.js form; sends `wc-ajax=checkout` |
| Thank You | [src/templates/woocommerce/thank-you.php](src/templates/woocommerce/thank-you.php) | Two-bill tab UI; Bill2 tab locked until Bill1 paid; calls external PromptPay plugin |
| Dashboard | [src/templates/dashboard/page-dashboard.php](src/templates/dashboard/page-dashboard.php) | Requires login; multi-page via `?page=` (overview, orders, profile); partials in `dashboard/partials/`; uses TailAdmin CSS/JS |

A reusable spinner is available at [src/templates/spinner.php](src/templates/spinner.php) (customisable colour).

## Auth & login flow

[src/inc/auth/shop-login-redirect.php](src/inc/auth/shop-login-redirect.php):
- Blocks direct `/wp-login.php` access (except logout, password-reset actions) → redirects to `/shop-login/`.
- Filters `login_url()` → `/shop-login/`.
- `logout_url()` keeps its WP-login.php target (nonce required); redirects to `/shop-login/` after logout.
- Filters `login_redirect` → `/shop/` fallback, avoids `/wp-admin`.

[src/inc/auth/roles.php](src/inc/auth/roles.php) — `developer` role: full WooCommerce + theme + plugin + file access; publishing/editing pages and posts; Query Monitor view. Explicitly **no** user-management caps (`create_users`, `edit_users`, `delete_users`, `promote_users` all `false`).

## Asset enqueueing

[src/inc/enqueue/scripts-styles.php](src/inc/enqueue/scripts-styles.php) — hooked at `wp_enqueue_scripts` priority 15:
- `style.css` versioned by `CHILD_THEME_VERSION`.
- `assets/css/tailwind.css` versioned by `filemtime` (cache-busts on every rebuild).
- Alpine.js 3 loaded from CDN (`jsdelivr`), `defer` injected via `script_loader_tag` filter.

## Internationalization

[src/inc/i18n/languages.php](src/inc/i18n/languages.php) — hooked at `init` priority 1 (must be first):
- Loads textdomain from `src/inc/i18n/languages/jaonaichan-{locale}.mo`.
- Filters `locale` to return `'th'` when the WP site language is Thai.
- `.po` → `.mo`: edit the `.po` file then run `npm run build:mo`.

## External dependencies referenced from this theme

Code called by templates that lives **outside this repo** (likely a sibling plugin). Don't try to find these here:

- `PromptPay_QR_Generator::generate($phone, $amount)` — QR generation.
- `GET /wp-json/promptpay/v1/slip/{order_id}/{bill}` and `admin-ajax.php?action=promptpay_verify_slip` — slip fetch/upload/verify.
- The `promptpay_qr` payment gateway (`WC()->payment_gateways->payment_gateways()['promptpay_qr']`).
- The JWT Authentication for WP-API plugin (provides `/jwt-auth/v1/*`).

## Conventions worth knowing

- New API endpoints: create a class in `src/api/`, self-init at the bottom, register routes inside `register_routes()` hooked from `init()`.
- New autoloaded code: drop into one of the `$inc_folders` subdirs; prefix with `NN-` if load order matters.
- Keep Thai labels wrapped in `__('...', $_ENV['TEXTDOMAIN_NAME'])` and run `npm run build:mo` after editing `.po` files.
- The codebase uses `do_action('qm/info', ...)` for Query Monitor debug logging; some calls in `functions.php` and `Auth_API::init()` are noisy at load time — be deliberate about adding more.
- `Utils::init()` registers the `custom-100` image size (100×100 px, hard-cropped).
- `Theme_No_Cache` sends `nocache_headers()` for `/shop`, `/shop-login`, `/cart`, `/checkout`, `/my-account` via `send_headers`.
