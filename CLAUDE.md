# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

WordPress **child theme of Astra** (`Template: astra` in [style.css](style.css)) for `jaonaichan.com`. It bolts onto Astra to provide:

- A custom REST API (`bigboss-auth/v1/*`, `jaonaichan/v1/orders*`) consumed by the `bigboss.jaonaichan.com` admin dashboard.
- Seven custom WooCommerce order statuses for a **two-bill deposit flow** (`bill1` + `bill2`).
- A two-bill PromptPay-QR thank-you page template.
- A `developer` role and a Tailwind 4 + Alpine.js front-end pipeline.

## Commands

```bash
# Tailwind build (entry: src/input.css → assets/css/tailwind.css)
npm run build      # minified one-shot
npm run watch      # dev watch
# or directly: npx @tailwindcss/cli -i ./src/input.css -o ./assets/css/tailwind.css --watch

# Recompile every .po → .mo (after editing translations)
for f in ./src/inc/i18n/languages/jaonaichan-*.po; do msgfmt "$f" -o "${f%.po}.mo"; done

# PHP deps
composer install
```

There is **no test suite, linter, or CI configured** in this repo. Node 24.14.1 is the version the readme pins.

## Bootstrap & file-load order — important

[functions.php](functions.php) is the single entry point and the load order is load-bearing:

1. Loads `vendor/autoload.php`, then `.env` via `Dotenv::createImmutable` (vars: `CHILD_THEME_NAME`, `TEXTDOMAIN_NAME`).
2. Explicitly requires `src/inc/cors.php` first.
3. Then walks each folder in `$inc_folders` in this order and `require_once`'s every `*.php` recursively:
   ```
   /src/api → /src/inc/i18n → /src/inc/helpers → /src/inc/enqueue → /src/inc/auth → /src/inc/woocommerce
   ```
4. Files inside a folder load in filesystem order — that's why helpers are numerically prefixed (`01-utils.php`, `02-redirect.php`, `03-page-templates.php`). **Keep that prefix convention when adding helpers.**
5. Most files self-bootstrap by calling `ClassName::init()` (or `new ClassName()`) at the bottom of the file. Don't add a separate registration step.

`TEXTDOMAIN_NAME` is read from `$_ENV` in many places (e.g. order-status labels, i18n loader). Hard-coding the textdomain string will break translations — always use `$_ENV['TEXTDOMAIN_NAME']`.

## REST API surface

Two namespaces, both registered on `rest_api_init`:

**`bigboss-auth/v1/`** — [src/api/auth_api.php](src/api/auth_api.php)
- `GET /ping`, `POST /signin` — public (whitelisted via `rest_authentication_errors` priority 9999, alongside `/jwt-auth/v1/token{,/validate}`).
- The frontend actually authenticates via the **JWT Authentication plugin** (`/jwt-auth/v1/token`); this file's `signin()` returns a `wp_generate_auth_cookie()` string labelled `token` (not a JWT) and looks like a legacy/fallback path. A `jwt_auth_token_before_dispatch` filter injects `roles` + `role` into the JWT response.
- Per-IP brute-force throttle: `login_attempts_{ip}` transient, max 5 attempts / 15 min.

**`jaonaichan/v1/orders*`** — [src/api/orders_api.php](src/api/orders_api.php)
- All routes gated by `is_user_logged_in()` (`Orders_API::check_permission`).
- **Route ordering matters**: specific routes (`/orders/products`, `/orders/products/bulk`) are registered *before* the wildcard `/orders/(?P<id>\d+)` so WP REST matches them. Preserve that order when adding routes.
- PATCH endpoints: `/orders/{id}/status`, `/note`, `/customer`, `/bill/{1|2}`.
- Two response shapes for `/orders/products`: `format=grouped` (default) or `format=flat` — see `build_grouped()` / `build_flat()`.

**CORS** — [src/inc/cors.php](src/inc/cors.php) replaces WP's default handler on `rest_api_init` priority 15. Allowed origins are hard-coded: `localhost:5173`, `localhost:3000`, `jaonaichan.com`, `bigboss.jaonaichan.com`. Add new origins there, not via filter.

## Two-bill data model

Stored as WooCommerce post meta on the order; **there is no custom table**. For each bill *N* ∈ {1, 2}:

- `_bill{N}_status`  — `pending` | `paid` | `cancelled` (default `pending`)
- `_bill{N}_amount`
- `_bill{N}_paid_at`

Custom WC statuses ([src/inc/woocommerce/order-status.php](src/inc/woocommerce/order-status.php), registered on `init` priority 5 *after* textdomain at priority 1):
`wc-waiting-transfer`, `wc-pending-payment-{1,2}`, `wc-waiting-verification-{1,2}`, `wc-paid-{1,2}`.

## Page templates

[src/inc/helpers/03-page-templates.php](src/inc/helpers/03-page-templates.php) (`Theme_Page_Templates`) auto-discovers any PHP file under `/src/templates/` that has a `Template Name:` doc-block header, registers it in the WP page-template dropdown, and routes it via `template_include`. Drop a new template into `src/templates/...` with a `Template Name:` header — no other registration needed. The relative path is what's stored in `_wp_page_template` meta.

`/src/example/` is **not** in the scanned paths, so anything there won't be registered (it's a scratch area).

## External dependencies referenced from this theme

The thank-you template ([src/templates/woocommerce/thank-you.php](src/templates/woocommerce/thank-you.php)) calls into code that lives **outside this repo** — likely a sibling plugin. Don't try to find these here:

- `PromptPay_QR_Generator::generate($phone, $amount)` — QR generation.
- `GET /wp-json/promptpay/v1/slip/{order_id}/{bill}` and `admin-ajax.php?action=promptpay_verify_slip` — slip upload/verify.
- The `promptpay_qr` payment gateway (`WC()->payment_gateways->payment_gateways()['promptpay_qr']`).
- The JWT Authentication for WP-API plugin (provides `/jwt-auth/v1/*`).

## Conventions worth knowing

- New API endpoints: create a class in `src/api/`, self-init at the bottom of the file, register routes inside a `register_routes()` method hooked from `init()`.
- New autoloaded code: drop into one of the `$inc_folders` subdirs; if order matters, prefix the filename with `NN-`.
- Keep Thai labels wrapped in `__('...', $_ENV['TEXTDOMAIN_NAME'])` and re-run the `msgfmt` loop after editing `.po` files.
- The codebase uses `do_action('qm/info', ...)` for Query Monitor debug logging; some calls in `functions.php` and `Auth_API::init()` are noisy/expensive at load time — be deliberate about adding more.
