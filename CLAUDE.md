# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Fun Dacha is an e-commerce site for seeds and gardening supplies, built on **OpenCart 4.1.0.3** (PHP 8.0+). It's customized for the Ukrainian market with a conversational product advisor (Garden Assistant), blog integration, and guest-only checkout mode. Live at https://fun-dacha.com.ua/.

## Tech Stack

- **Backend:** PHP 8.0+ with Twig templating
- **Frontend:** Bootstrap 5 (customized SCSS), jQuery
- **Database:** MySQL (mysqli driver, `oc_` table prefix)
- **SCSS Compilation:** PHP ScssPhp (no Node.js/npm in this project)

## Build & Deploy

```bash
./build.sh                    # Compile SCSS → CSS via PHP ScssPhp
./deploy.sh --branch develop  # SSH deploy: stash → pull → build on server
```

**No test framework or linter is configured.** Testing is manual/browser-based.

**Important:** Compiled CSS is tracked in Git. Always run `./build.sh` before committing SCSS changes.

## Architecture

OpenCart MVC with Registry-based dependency injection. All services accessed via `$this->property` in controllers/models.

### Request Flow

`index.php` → `config.php` → `system/startup.php` → `system/framework.php` → Router parses `route` GET param → Controller → Twig template

### Directory Layout

```
catalog/
  controller/          # Request handlers (route=controller/action)
  model/               # Data access & business logic
  view/template/       # Twig templates
  view/stylesheet/     # SCSS/CSS (bootstrap.scss, stylesheet.scss, _brand-variables.scss)
  view/javascript/     # Frontend JS
  language/{en-gb,uk-ua,fr-fr}/  # i18n PHP files

system/
  engine/              # Core: Autoloader, Controller, Model, Router, Loader
  library/             # Services: Cart, Session, Mail, Cache, Template, DB
  config/              # Default configs (min_order_amount.php, etc.)

extension/             # OpenCart modules (shipping, export/import)

shared/
  assistant/           # Garden Assistant KB (assistant_db.json, agent_prompt.md)
  blog/                # Blog articles (blog_articles.json + images)
  import/              # Excel import scripts & workbooks
```

### Namespace → File Mapping (PSR-0, kebab-case files)

`Opencart\Catalog\Controller\Product\Product` → `catalog/controller/product/product.php`

### Registry Services (available as `$this->*` in controllers/models)

`db`, `config`, `session`, `cart`, `customer`, `load`, `url`, `response`, `language`, `log`, `event`

## Custom Features

### Garden Assistant

Heuristic semantic search (no LLM). Knowledge base in `shared/assistant/assistant_db.json` (981 products, 22 categories, 102 filters, 31 blog articles). Spec in `shared/assistant/agent_prompt.md`.

- **Endpoint:** `POST /index.php?route=assistant/chat` (JSON in/out)
- **State:** `$this->session->data['ai_assistant_state']` (categoryId, filterIds, history)
- **Code:** `catalog/controller/assistant/`, `catalog/model/assistant/`

### Guest-Only Mode

Account registration/login disabled by default via `config_account_enabled` setting in `oc_setting`. Guest checkout works. Controlled in `catalog/controller/common/header.php`, `catalog/controller/account/login.php`, `catalog/controller/account/register.php`.

### Pricing Logic

`oc_product_discount` table uses a `special` flag: `special=0` for regular discounts, `special=1` for sale prices (higher priority). Effective price calculation in `catalog/model/catalog/product.php` via `sqlEffectiveDiscountPriceExpression()`.

### Minimum Order Amount

Defined in `system/config/min_order_amount.php` (300 UAH). Enforced in `catalog/controller/checkout/checkout.php` — users can add items freely but get redirected at checkout if below minimum.

### Blog Integration

Articles in `shared/blog/blog_articles.json` (~31 articles, Ukrainian). Imported via Python scripts in `shared/import/`. Linked to products for SEO.

## Styling

Brand variables centralized in `catalog/view/stylesheet/_brand-variables.scss` — CSS custom properties on `:root`. Primary green: `#207d43`.

After editing `.scss` files, run `./build.sh` to recompile `bootstrap.css` and `stylesheet.css`.

## i18n

Language files in `catalog/language/{lang}/`. Load with `$this->load->language('module/action')`, access with `$this->language->get('key')`. In Twig: `{{ variable_name }}`.

## Security Standards

This is a production e-commerce site handling customer data and payments. All code changes must follow these practices:

### Database Queries

Always use `$this->db->escape()` for string values and `(int)` casting for integers. Never interpolate raw user input into SQL:

```php
// CORRECT
$this->db->query("SELECT * FROM `" . DB_PREFIX . "product` WHERE `name` LIKE '%" . $this->db->escape($input) . "%'");

// WRONG — raw input in query
$this->db->query("SELECT * FROM `" . DB_PREFIX . "product` WHERE `name` LIKE '%" . $input . "%'");
```

### Output Escaping

Always escape user-generated content in Twig templates to prevent XSS:

```twig
{{ variable }}           {# Auto-escaped by Twig — safe #}
{{ variable|raw }}       {# DANGEROUS — only for trusted HTML #}
```

Never use `|raw` on user-supplied data. When outputting into HTML attributes or JavaScript contexts, use appropriate Twig escaping: `{{ var|e('html_attr') }}`, `{{ var|e('js') }}`.

### Sensitive Data

- Never hardcode credentials, API keys, or secrets in PHP files
- `config.php` and `adminpage/config.php` contain DB credentials — these are in `.gitignore` and must stay excluded from commits
- Use environment variables or server-level config for secrets where possible

### Security Headers

When modifying response handling or `.htaccess`, ensure these headers are present:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Strict-Transport-Security` (site uses HTTPS)
- `Content-Security-Policy` (restrict inline scripts/styles where feasible)

## Code Quality Standards

### PHP

- Follow PSR-12 coding style (OpenCart 4 convention): braces on same line for control structures, 1 tab indentation
- Type-hint method parameters and return types where possible (`string`, `int`, `array`, `void`)
- Use strict comparisons (`===`, `!==`) instead of loose (`==`, `!=`)
- Validate and sanitize all external input (GET, POST, cookie, header values) at the controller level before passing to models

### Twig Templates

- Use semantic HTML5 elements (`<nav>`, `<main>`, `<article>`, `<section>`)
- Include ARIA attributes for interactive elements (the project already uses `aria-label`, `role="dialog"`, `aria-live="polite"` — maintain this standard)
- Images must have `alt` attributes; use `loading="lazy"` for below-fold images
- Keep logic minimal in templates — complex conditionals belong in controllers

### JavaScript

- Use `const`/`let`, never `var`
- Attach event listeners with `addEventListener`, avoid inline `onclick` handlers
- Namespace custom code under a project object to avoid global pollution

### CSS/SCSS

- All color values must use CSS custom properties from `_brand-variables.scss` — never hardcode hex colors
- Mobile-first responsive design: use Bootstrap breakpoints, test at 320px, 768px, 1024px, 1440px
- New components should follow BEM naming convention (`.block__element--modifier`)

## Accessibility (a11y)

The site must meet WCAG 2.1 AA compliance:

- All interactive elements must be keyboard-accessible (tab order, focus indicators)
- Color contrast ratio minimum 4.5:1 for normal text, 3:1 for large text
- Form inputs must have associated `<label>` elements or `aria-label`
- Dynamic content updates must use `aria-live` regions
- Modals must trap focus and return focus on close

## Performance

- Optimize images before adding to repo (WebP preferred, fallback to compressed JPEG/PNG)
- Use `loading="lazy"` on images below the fold
- Minimize inline `<script>` and `<style>` blocks — prefer external files for caching
- Cache infrastructure exists (`system/library/cache/`) with Redis, Memcached, and APCu adapters

## Key Gotchas

1. **No npm/Node.js** — SCSS compiles via PHP. Don't try to install node dependencies.
2. **CSS tracked in Git** — always `./build.sh` before committing SCSS changes.
3. **DB prefix** — use `DB_PREFIX` constant, never hardcode `oc_`.
4. **Registry pattern** — use `$this->load->model()` / `$this->load->controller()`, never `new`.
5. **Twig cache** — clear `DIR_STORAGE/cache/` if template changes don't appear.
6. **Guest carts** — identified by `session_id`, merged to `customer_id` on login.
7. **Min order** — enforced at checkout only, not in cart.
8. **No automated tests** — manually verify changes in browser. Test the golden path and edge cases.
9. **No CI/CD** — deployment is manual via `./deploy.sh`. Double-check changes before deploying.

## Key Documentation

- `shared/assistant/agent_prompt.md` — Assistant heuristic search logic
- `SEO_IMPLEMENTATION_BLUEPRINT_FUN_DACHA.md` — SEO keyword mapping and strategy
- `GUEST_ONLY_MODE.md` — Account toggle documentation
- `docs/PRODUCT_ATTRIBUTE_NORMALIZATION.md` — Product attribute handling
