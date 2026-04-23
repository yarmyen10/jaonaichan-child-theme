# CONTEXT.md — jaonaichan-child-theme

WordPress **child theme of Astra** for `jaonaichan.com`. Adds a custom REST API layer for the `bigboss.jaonaichan` admin dashboard, custom WooCommerce order statuses for a two-bill deposit flow, a PromptPay-QR thank-you page, a custom Developer role, a page-template auto-loader, and a Tailwind build pipeline.

---

## Tech Stack

| Layer | Library / Tool |
|---|---|
| Parent theme | Astra (`Template: astra` in [style.css](style.css)) |
| PHP deps | `vlucas/phpdotenv ^5.6` (via [composer.json](composer.json)) |
| Front-end | Tailwind CSS 4 + Alpine.js 3.14.1 (CDN, deferred) |
| Build | `@tailwindcss/cli` → `src/input.css` → `assets/css/tailwind.css` |
| i18n | `.po`/`.mo` files compiled with `msgfmt` (see [readme.md](readme.md)) |
| Auth (frontend) | `jwt-auth/v1/token` (JWT Authentication plugin) + custom `bigboss-auth/v1/signin` |

Env vars loaded from `.env` via Dotenv (see [.env](.env)):
- `CHILD_THEME_NAME=jaonaichan`
- `TEXTDOMAIN_NAME=jaonaichan`

---

## Directory Layout

```
jaonaichan-child-theme/
├── functions.php              # bootstrap — loads .env, autoloads /src/{api,inc/*}
├── style.css                  # theme header (Template: astra)
├── composer.json / composer.lock
├── package.json               # tailwind build scripts
├── readme.md                  # build commands cheatsheet
├── .env                       # TEXTDOMAIN_NAME, CHILD_THEME_NAME
│
├── assets/
│   ├── css/tailwind.css       # generated
│   └── imgs/prompt-pay-logo.jpg
│
├── src/
│   ├── input.css              # tailwind entry
│   │
│   ├── api/                   # REST API endpoints (loaded first)
│   │   ├── auth_api.php       # Auth_API class  → /bigboss-auth/v1/*
│   │   └── orders_api.php     # Orders_API class → /jaonaichan/v1/orders*
│   │
│   ├── inc/                   # autoloaded in this order (functions.php:24-31)
│   │   ├── i18n/
│   │   │   ├── languages.php                       # textdomain loader
│   │   │   └── languages/jaonaichan-{th,en_US}.{po,mo}
│   │   ├── helpers/
│   │   │   ├── 01-utils.php                        # add_image_size('custom-100')
│   │   │   ├── 02-redirect.php                     # Theme_Redirect URI rules
│   │   │   └── 03-page-templates.php               # auto-register /src/templates as Page Templates
│   │   ├── enqueue/scripts-styles.php              # style.css, tailwind.css, alpine (defer)
│   │   ├── auth/roles.php                          # Developer role add/remove
│   │   ├── cors.php                                # loaded explicitly at top of functions.php
│   │   └── woocommerce/order-status.php            # 7 custom wc-* statuses
│   │
│   ├── templates/             # discovered by Theme_Page_Templates
│   │   ├── spinner.php        # Alpine-driven loading overlay (partial)
│   │   ├── woocommerce/thank-you.php    # ✅ real two-bill PromptPay page (Template Name: Thank You)
│   │   └── dashboard/page-dashboard.php  # Alpine dashboard shell (⚠ partials dir missing)
│   │
│   └── example/page/thank-you.php  # earlier mock of thank-you, not registered as Template
│
└── vendor/                    # composer
```

File load order matters — `/src/api` is loaded before `/src/inc/*`, and each folder is walked with `RecursiveIteratorIterator` (sort order = filesystem). Helpers are numerically prefixed (`01-`, `02-`, `03-`) to pin sequence.

---

## Bootstrap Flow — [functions.php](functions.php)

1. Defines `CHILD_THEME_JAO_NAI_CHAN_VERSION`.
2. `require vendor/autoload.php` and loads `.env` via `Dotenv::createImmutable`.
3. Explicitly requires `src/inc/cors.php`.
4. Iterates `$inc_folders = [api, i18n, helpers, enqueue, auth, woocommerce]` and recursively `require_once`s every `*.php`.
5. On `init` (priority 1): re-registers the `custom-100` image size (also done in `Utils::init()`), emits a bunch of `qm/info` debug actions (Query Monitor).

---

## REST API

### Auth — [src/api/auth_api.php](src/api/auth_api.php) (`Auth_API` class)

| Route | Method | Auth |
|---|---|---|
| `/wp-json/bigboss-auth/v1/ping` | GET | public |
| `/wp-json/bigboss-auth/v1/signin` | POST | public |
| `/wp-json/jwt-auth/v1/token` | POST | public (bypassed by filter) |
| `/wp-json/jwt-auth/v1/token/validate` | POST | public (bypassed) |

- `signin()` — rate-limited per-IP (`login_attempts_{ip}` transient, max 5 / 15 min). On success returns `{ success, token, user{id,username,email,display_name,roles} }`.
  - **Note:** uses `wp_generate_auth_cookie()` as the "token" — this is a WP auth cookie string, not a JWT. The name `Auth_API` exists alongside the JWT Authentication plugin; the frontend (`bigboss.jaonaichan`) actually hits `/jwt-auth/v1/token`, so this custom endpoint may be legacy/fallback.
- `jwt_auth_token_before_dispatch` filter injects `roles` + first `role` into the JWT response payload.
- `rest_authentication_errors` filter (priority 9999) whitelists the 4 public routes so they bypass earlier auth errors.

### Orders — [src/api/orders_api.php](src/api/orders_api.php) (`Orders_API` class)

All routes require `is_user_logged_in()` (`check_permission`).

| Route | Method | Purpose |
|---|---|---|
| `GET /jaonaichan/v1/orders` | list (paginated, `status`/`page`/`per_page`) |
| `GET /jaonaichan/v1/orders/{id}` | detail (+items) |
| `GET /jaonaichan/v1/orders/{id}/products` | order items + bill1/bill2 summary |
| `GET /jaonaichan/v1/orders/products` | products grouped-or-flat by a single status |
| `GET /jaonaichan/v1/orders/products/bulk` | products across multiple statuses (`statuses=a,b,c` or `all`) |
| `PATCH /jaonaichan/v1/orders/{id}/status` | update WC status |
| `PATCH /jaonaichan/v1/orders/{id}/note` | add order note |
| `PATCH /jaonaichan/v1/orders/{id}/customer` | update billing fields (first_name, last_name, email, phone, address_1/2, city, state, postcode, country) |
| `PATCH /jaonaichan/v1/orders/{id}/bill/{bill_number}` | bill_number ∈ {1,2}; updates `_bill{N}_{status,amount,paid_at}` post meta |

Routing tip in the file: specific routes (`/orders/products`, `/orders/products/bulk`) are registered **before** wildcard routes (`/orders/(?P<id>\d+)`) so WP REST matches them correctly.

**Two-bill data model (post meta):**
- `_bill1_status` / `_bill1_amount` / `_bill1_paid_at`
- `_bill2_status` / `_bill2_amount` / `_bill2_paid_at`
- Valid bill statuses: `pending` | `paid` | `cancelled` (default `pending`)

**Formatters (private):**
- `format_order($order, $with_items=false)` — base order shape returned to API.
- `format_order_item($item)` — item + nested `product` block (sku, price, stock, categories, tags, attributes, image {thumbnail, medium, full}). Returns `null` if product missing.
- `build_grouped($orders)` / `build_flat($orders)` — two response shapes for `/orders/products`.
- `get_product_attributes($product)` — handles both taxonomy attributes (term_id → name) and plain-string attributes.

`get_products_bulk()` also returns a per-status `summary` (`order_count`, `item_count`, `total`) built from the flat list.

### CORS — [src/inc/cors.php](src/inc/cors.php)

On `rest_api_init` (priority 15), removes WP's default CORS handler and installs its own `rest_pre_serve_request` filter. Allowed origins:
- `http://localhost:5173` / `http://localhost:3000` (dev)
- `https://jaonaichan.com` / `https://bigboss.jaonaichan.com`

Allows methods `GET, POST, PUT, PATCH, DELETE, OPTIONS` and headers `Authorization, Content-Type, X-WP-Nonce`. Short-circuits `OPTIONS` preflights with `status_header(200) + exit`.

---

## Roles — [src/inc/auth/roles.php](src/inc/auth/roles.php)

Adds a `developer` role on `init` with near-admin caps (incl. `manage_options`, `edit_theme_options`, `edit_files`, `unfiltered_html`, WooCommerce `manage_woocommerce`, `edit_shop_orders`, Query Monitor `view_query_monitor`), but explicitly **forbids** user management (`create/edit/delete/promote_users = false`). Role is removed on `switch_theme` for cleanliness.

---

## WooCommerce Customisations — [src/inc/woocommerce/order-status.php](src/inc/woocommerce/order-status.php)

Registers 7 custom post statuses via `register_post_status` on `init` (priority 5, after textdomain):

| Slug | Label (th) |
|---|---|
| `wc-waiting-transfer` | รอโอนเงิน |
| `wc-pending-payment-1` | รอชำระบิลที่ 1 |
| `wc-pending-payment-2` | รอชำระบิลที่ 2 |
| `wc-waiting-verification-1` | รอตรวจสอบการชำระ (ครั้งที่ 1) |
| `wc-waiting-verification-2` | รอตรวจสอบการชำระ (ครั้งที่ 2) |
| `wc-paid-1` | ชำระแล้ว (ครั้งที่ 1) |
| `wc-paid-2` | ชำระแล้ว (ครั้งที่ 2) |

Also filtered into `wc_order_statuses` so they appear in the admin dropdown. Per-status colour CSS exists commented-out at the bottom.

---

## Page Templates — [src/inc/helpers/03-page-templates.php](src/inc/helpers/03-page-templates.php)

`Theme_Page_Templates` walks `$templates_paths` (currently `/src/templates`), reads each PHP file's `Template Name:` header with `get_file_data`, registers them via `theme_page_templates` filter, and routes them at `template_include`. Relative path (e.g. `src/templates/woocommerce/thank-you.php`) is stored in the post's `_wp_page_template` meta.

### [src/templates/woocommerce/thank-you.php](src/templates/woocommerce/thank-you.php) — "Thank You"

The real two-bill payment page. Reads `?wcf-order={id}` (Cartflows order ID), renders:
- Alpine component `billTabs()` with `activeTab ∈ {1,2}`, `bill1Paid`, `bill2Paid`, `preview{1,2}`, `viewBill{1,2}`, `slipModal`.
- Per-tab: items list, PromptPay QR (generated by `PromptPay_QR_Generator::generate($phone, $amount)` — external class, likely from a sibling plugin), PAID watermark when paid, slip upload/view.
- On init, fetches existing slip from `/wp-json/promptpay/v1/slip/{order_id}/{bill}` (also external) — if Bill 1 already has a slip, marks bill1Paid and jumps to tab 2.
- `payBill1()` POSTs to `admin-ajax.php` with action `promptpay_verify_slip` (handler lives outside this theme).
- `payBill2()` currently just sets `bill2Paid = true` — no backend call yet.
- Gateway phone pulled from `WC()->payment_gateways->payment_gateways()['promptpay_qr']->phone`, fallback `get_option('promptpay_phone')`.
- The spinner overlay at [src/templates/spinner.php](src/templates/spinner.php) is included and bound to Alpine's `loading` state.

### [src/templates/dashboard/page-dashboard.php](src/templates/dashboard/page-dashboard.php) — "Dashboard"

Alpine shell with `?page=overview|orders|profile` dispatch. ⚠ **Includes `__DIR__ . '/partials/{sidebar,header,<page>}.php'`, but the `partials/` directory does not exist** — this template will fatal until those partials are added. It also expects assets at `src/templates/dashboard/assets/{css/tailadmin.css, js/tailadmin.js}` which are not in the repo.

### [src/example/page/thank-you.php](src/example/page/thank-you.php)

An earlier mock of the thank-you page (no PHP data wiring, Blade-style `{{-- --}}` comments left in). Registered as "Example Thank You" Template Name but is under `/src/example/`, which is **not** in the `$templates_paths` list — so it won't actually be registered.

---

## Redirect Manager — [src/inc/helpers/02-redirect.php](src/inc/helpers/02-redirect.php)

`Theme_Redirect` hooks `template_redirect` and iterates `$rules`:
- Current rule: any URI containing `/step/thank-you` → `/thank-you-slave/`, 301, forwarding `?wcf-order`.

Add rules in-class; each rule = `{ match, target, pass_params[], status }`.

---

## i18n — [src/inc/i18n/languages.php](src/inc/i18n/languages.php)

- `theme_load_textdomain()` on `init` priority 1 — loads `src/inc/i18n/languages/{TEXTDOMAIN}-{locale}.mo`.
- `theme_set_locale` filter forces locale to `th` when `WPLANG === 'th'`.
- Build `.mo` files with: `for f in ./src/inc/i18n/languages/jaonaichan-*.po; do msgfmt "$f" -o "${f%.po}.mo"; done`.

---

## Assets / Build

- `npm run build` → minified tailwind build to `assets/css/tailwind.css`.
- `npm run watch` → watch mode.
- Alpine.js is CDN-loaded with `defer` via a `script_loader_tag` filter in [enqueue/scripts-styles.php](src/inc/enqueue/scripts-styles.php).

---

## Notes / Gotchas

- `functions.php` still has live debug via `do_action('qm/info', …)` inside its `init` hook — noisy in Query Monitor.
- `Auth_API::init()` calls `get_users(['number' => -1])` at load time and logs all users to Query Monitor — potentially expensive + PII-leaky. Consider moving inside `register_routes` or removing.
- `Auth_API::signin()` returns a `wp_generate_auth_cookie()` string labelled `token` — not a JWT. Frontend uses the real `jwt-auth/v1/token` endpoint; this custom `/signin` appears unused by `bigboss.jaonaichan`.
- `add_image_size('custom-100', 100, 100, true)` is registered in **two** places (`Utils::init()` + `functions.php` init hook). Redundant; existing images will need `regenerate-thumbnails`.
- Dashboard page template references `partials/` + `assets/` subdirs that don't exist yet.
- `PromptPay_QR_Generator` and the `/wp-json/promptpay/v1/slip/…` endpoint are consumed by the thank-you template but defined outside this theme (likely a sibling plugin).
- `sanitize_text_field` is used on bill `amount` in `update_order_bill()` — fine for storage, but the REST arg type is declared `number`; ensure callers pass strings or numeric-coercible values.
