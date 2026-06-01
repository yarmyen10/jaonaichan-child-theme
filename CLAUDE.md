# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
npm run build              # Compile Tailwind CSS (minified)
npm run watch              # Tailwind watch mode
npm run build:mo           # Convert .po → .mo (i18n)
npm run deploy             # FTP deploy (requires .env)
npm run deploy:dry         # Preview FTP deploy
npm run deploy:changed     # Deploy only changed files
npm run deploy:changed:dry # Preview changed-file deploy
```

## Architecture

WordPress child theme of Astra. Entry point is `functions.php` — loads `.env` (vlucas/phpdotenv) then uses `RecursiveIteratorIterator` to autoload all PHP files from these directories in order:

1. `src/api/` — REST API route registrations
2. `src/inc/i18n/`
3. `src/inc/helpers/`
4. `src/inc/enqueue/`
5. `src/inc/auth/`
6. `src/inc/woocommerce/`

**REST namespace**: `/wp-json/jaonaichan/v1/*`

**Auth**: JWT via `bb_jwt` cookie. All endpoints use a `check_permission()` callback that validates the JWT token from the cookie header.

**CORS**: `src/inc/cors.php` sets permissive headers for `bigboss.jaonaichan.com`.

## Key Files

| File | Purpose |
|------|---------|
| `src/inc/woocommerce/order-status.php` | Custom statuses: `waiting-transfer`, `pending-payment-1/2`, `paid-1/2`, `wait-verify-1/2` |
| `src/api/orders_api.php` | Orders list, detail, status update endpoints |
| `src/api/dashboard_api.php` | Revenue metrics, order counts |
| `src/api/barcode_pack_api.php` | Barcode packing workflow |
| `src/inc/auth/` | Login redirect, JWT integration, roles |

## Conventions

- New REST endpoints go in `src/api/` — they are autoloaded automatically
- New WooCommerce hooks go in `src/inc/woocommerce/`
- Environment credentials (FTP, API keys) live in `.env` — never hardcode
- Textdomain: `jaonaichan` — use `__('text', 'jaonaichan')` for all strings
