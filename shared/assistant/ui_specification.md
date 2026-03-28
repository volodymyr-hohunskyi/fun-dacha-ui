# Fun Dacha AI Assistant — UI Specification (OpenCart)

**Version**: 2.0
**Date**: 2026-03-28
**Platform**: OpenCart 3.x / 4.x
**Purpose**: Full specification for a chat-based AI assistant implemented as an OpenCart module.

---

## 1. Overview

The assistant is a **conversational product advisor** implemented as a native OpenCart extension (module). It uses:
- A pre-built `db.json` database (loaded server-side, cached)
- **Heuristic semantic search** in PHP — keyword matching, filter index lookups, and rule-based intent detection. No external AI/LLM API calls.
- Vanilla JS + jQuery (already present in OpenCart) for the chat UI
- OpenCart's standard MVC structure (Controller / Model / View / Language)

The widget renders as a floating chat bubble on every storefront page, injected via a layout position. An optional dedicated page is available at `/index.php?route=extension/module/ai_assistant`.

### Three top-level intents

Every conversation begins with a **welcome message and three intent buttons**. The user's first choice routes the entire session:

| Button | Intent key | Data source |
|---|---|---|
| Консультація | `consult` | `db.json` — products, categories, filters, blog |
| Допомога з оплатою | `payment` | `db.json` `info.payment` — content from the store's Payment information page |
| Допомога з доставкою | `delivery` | `db.json` `info.delivery` — content from the store's Delivery information page |

A **"Замовити дзвінок"** (Request a call) button is always visible at the bottom of the widget.

`info.payment` and `info.delivery` are plain-text extracts of the corresponding OpenCart information pages, cached into `db.json` at module save time in the admin panel (configured by page ID).

---

## 2. Tech Stack

| Layer | Technology |
|---|---|
| Backend language | PHP 7.4+ (OC3) / PHP 8.1+ (OC4) |
| Templates | Twig (OC4) / `.tpl` (OC3) |
| Frontend JS | Vanilla JS + jQuery (bundled with OpenCart) |
| CSS | Plain CSS, loaded as module asset |
| Search engine | PHP heuristic search (keyword matching + filter/category index lookups) |
| DB cache | PHP `file_get_contents` + `json_decode` cached with APCu or file cache |
| Session | PHP `$_SESSION` for conversation state (active category, active filters) |
| AJAX | jQuery `$.ajax` → OpenCart controller endpoint |

---

## 3. OpenCart Module Structure

```
extension/
  ai_assistant/
    admin/
      controller/
        module/
          ai_assistant.php        — Module settings page (admin)
      language/
        en-gb/module/
          ai_assistant.php
        uk-ua/module/
          ai_assistant.php
      view/
        template/module/
          ai_assistant.twig       — Admin settings form
    catalog/
      controller/
        module/
          ai_assistant.php        — Widget injection controller
        api/
          ai_assistant.php        — AJAX endpoint: receives chat message, runs heuristic search, returns JSON
      model/
        module/
          ai_assistant.php        — DB helper: loads db.json, runs queries
      language/
        en-gb/module/
          ai_assistant.php        — Frontend UI strings (EN)
        uk-ua/module/
          ai_assistant.php        — Frontend UI strings (UK)
      view/
        template/module/
          ai_assistant_widget.twig   — Widget HTML shell (floating button + panel)
          ai_assistant_page.twig     — Full-page mode template
    system/
      db.json                     — Indexed product/category/filter/blog + info pages (payment, delivery)
install.json                      — OC4 extension manifest
install.xml                       — OC3 extension manifest (ocmod)
```

---

## 4. Layout & Visual Design

```
┌─────────────────────────────────────────────────────┐
│  HEADER: "🌱 Помічник садівника"           [×] close │
├─────────────────────────────────────────────────────┤
│                                                     │
│  WELCOME SCREEN (shown until first message sent)    │
│  ┌───────────────────────────────────────────────┐  │
│  │  🌱                                           │  │
│  │  Доброго дня! Вам потрібна консультація?      │  │
│  │  З радістю відповім на ваші запитання.        │  │
│  │                                               │  │
│  │  [ Консультація          ]                    │  │
│  │  [ Допомога з оплатою    ]                    │  │
│  │  [ Допомога з доставкою  ]                    │  │
│  └───────────────────────────────────────────────┘  │
│                                                     │
│  CHAT AREA (scrollable, max-height: 420px)          │
│  ┌───────────────────────────────────────────────┐  │
│  │ [AI] bubble: text + rich components           │  │
│  └───────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────┐  │
│  │ [User] bubble: text message                   │  │
│  └───────────────────────────────────────────────┘  │
│                                                     │
│  QUICK REPLIES (context-sensitive chips)            │
│  [ Томати ] [ Огірки ] [ Зелень ] [ Квіти ]        │
│                                                     │
├─────────────────────────────────────────────────────┤
│  ┌──────────────────────────────┐ [Надіслати]       │
│  │  Напишіть ваш запит...       │                   │
│  └──────────────────────────────┘                   │
│  [ 📞 Замовити дзвінок ]                            │
└─────────────────────────────────────────────────────┘
```

**Welcome screen state machine:**
1. Widget opens → show welcome screen with greeting + 3 intent buttons
2. User taps an intent button (or types a message) → hide welcome screen, show chat area
3. **Консультація** → show category grid quick-replies, begin product search flow
4. **Допомога з оплатою** → show payment info text from `db.json info.payment`, emit `showInfo` action
5. **Допомога з доставкою** → show delivery info text from `db.json info.delivery`, emit `showInfo` action
6. **Замовити дзвінок** → always visible; triggers `callbackRequest` action (opens phone form or redirects)

**Widget trigger button** (always visible, bottom-right):
```
╭──────────────────╮
│  🌱 Помічник      │
╰──────────────────╯
```

**Layout modes:**
- **Widget**: floating panel 420×600px, bottom-right, `position: fixed`
- **Page**: full-page at `/index.php?route=extension/module/ai_assistant`, max-width 720px, centered
- **Mobile** (< 768px): widget becomes full-screen overlay

---

## 5. HTML Template — Widget Shell

**File**: `catalog/view/template/module/ai_assistant_widget.twig`

```twig
{# Floating trigger button #}
<button id="ai-assistant-toggle" class="ai-assistant-trigger" aria-label="{{ text_open }}">
  <span class="ai-assistant-trigger__icon">🌱</span>
  <span class="ai-assistant-trigger__label">{{ text_assistant }}</span>
</button>

{# Chat panel #}
<div id="ai-assistant-panel" class="ai-assistant-panel" aria-hidden="true" role="dialog" aria-label="{{ text_assistant }}">

  <div class="ai-assistant-panel__header">
    <span>🌱 {{ text_assistant }}</span>
    <button class="ai-assistant-panel__close" id="ai-assistant-close" aria-label="{{ text_close }}">✕</button>
  </div>

  <div class="ai-assistant-panel__body" id="ai-chat-messages" role="log" aria-live="polite">

    {# Welcome screen — visible until first message #}
    <div class="ai-welcome" id="ai-welcome-screen">
      <div class="ai-welcome__icon">🌱</div>
      <p class="ai-welcome__text">{{ text_welcome }}</p>
      <p class="ai-welcome__sub">{{ text_welcome_sub }}</p>
      <div class="ai-intent-list">
        <button class="ai-intent-btn" data-intent="consult" data-message="{{ text_intent_consult }}">
          {{ text_intent_consult }}
        </button>
        <button class="ai-intent-btn" data-intent="payment" data-message="{{ text_intent_payment }}">
          {{ text_intent_payment }}
        </button>
        <button class="ai-intent-btn" data-intent="delivery" data-message="{{ text_intent_delivery }}">
          {{ text_intent_delivery }}
        </button>
      </div>
    </div>

    {# Messages injected here by JS #}

  </div>

  <div class="ai-assistant-panel__quick-replies" id="ai-quick-replies" aria-label="{{ text_suggestions }}">
    {# Populated dynamically by JS #}
  </div>

  <div class="ai-assistant-panel__footer">
    <textarea
      id="ai-chat-input"
      class="ai-assistant-panel__input"
      placeholder="{{ text_placeholder }}"
      rows="1"
      aria-label="{{ text_placeholder }}"
    ></textarea>
    <button id="ai-chat-send" class="ai-assistant-panel__send" aria-label="{{ text_send }}">
      {{ text_send }}
    </button>
  </div>

  <div class="ai-assistant-panel__callback">
    <a href="{{ callback_url }}" class="ai-callback-btn" id="ai-callback-btn">
      📞 {{ text_callback }}
    </a>
  </div>

</div>

{# Pass config to JS #}
<script>
window.aiAssistantConfig = {
  ajaxUrl:     '{{ ajax_url }}',
  imageBase:   '{{ image_base }}',
  productUrl:  '{{ product_url_base }}',
  blogUrl:     '{{ blog_url_base }}',
  callbackUrl: '{{ callback_url }}',
  lang:        '{{ lang_code }}'
};
</script>
```

---

## 6. PHP Controller — Widget Injection

**File**: `catalog/controller/module/ai_assistant.php`

```php
<?php
class ControllerModuleAiAssistant extends Controller {

    public function index(): string {
        $this->load->language('module/ai_assistant');

        // Pass URLs to template
        $data['ajax_url']         = $this->url->link('api/ai_assistant/chat', '', true);
        $data['image_base']       = HTTP_CATALOG . 'image/';
        $data['product_url_base'] = $this->url->link('product/product', 'product_id=', true);
        $data['blog_url_base']    = $this->url->link('extension/module/blog/article', 'slug=', true);
        $data['callback_url']     = $this->config->get('module_ai_assistant_callback_url') ?: '#';
        $data['lang_code']        = $this->config->get('config_language');

        // Language strings
        $data['text_assistant']       = $this->language->get('text_assistant');
        $data['text_open']            = $this->language->get('text_open');
        $data['text_close']           = $this->language->get('text_close');
        $data['text_welcome']         = $this->language->get('text_welcome');
        $data['text_welcome_sub']     = $this->language->get('text_welcome_sub');
        $data['text_intent_consult']  = $this->language->get('text_intent_consult');
        $data['text_intent_payment']  = $this->language->get('text_intent_payment');
        $data['text_intent_delivery'] = $this->language->get('text_intent_delivery');
        $data['text_placeholder']     = $this->language->get('text_placeholder');
        $data['text_send']            = $this->language->get('text_send');
        $data['text_suggestions']     = $this->language->get('text_suggestions');
        $data['text_callback']        = $this->language->get('text_callback');

        return $this->load->view('module/ai_assistant_widget', $data);
    }
}
```

---

## 7. PHP Controller — AJAX Endpoint

**File**: `catalog/controller/api/ai_assistant.php`

Receives a chat message, maintains conversation state in PHP session (active category, active filters), runs heuristic search, and returns JSON. No external API calls.

```php
<?php
class ControllerApiAiAssistant extends Controller {

    public function chat(): void {
        header('Content-Type: application/json');

        // Only POST
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $userMessage = trim($input['message'] ?? '');

        if (empty($userMessage)) {
            echo json_encode(['error' => 'Empty message']);
            return;
        }

        // Load model
        $this->load->model('module/ai_assistant');

        // Conversation state (active category, active filters, history)
        $stateKey = 'ai_assistant_state';
        if (!isset($this->session->data[$stateKey])) {
            $this->session->data[$stateKey] = [
                'categoryId' => null,
                'filterIds'  => [],
                'history'    => [],
            ];
        }
        $state = &$this->session->data[$stateKey];

        // Keep last 20 turns for context
        $state['history'][] = ['role' => 'user', 'content' => $userMessage];
        if (count($state['history']) > 20) {
            $state['history'] = array_slice($state['history'], -20);
        }

        // Run heuristic search engine
        $result = $this->model_module_ai_assistant->heuristicSearch(
            $userMessage,
            $state['categoryId'],
            $state['filterIds']
        );

        // Persist updated state from search result
        if (isset($result['categoryId'])) {
            $state['categoryId'] = $result['categoryId'];
        }
        if (!empty($result['filterIds'])) {
            $state['filterIds'] = array_unique(
                array_merge($state['filterIds'], $result['filterIds'])
            );
        }

        $state['history'][] = ['role' => 'assistant', 'content' => $result['text']];

        // Enrich actions with product/filter/article data
        $actions = $result['actions'] ?? [];
        foreach ($actions as &$action) {
            if ($action['type'] === 'showProducts' && !empty($action['productIds'])) {
                $action['products'] = $this->model_module_ai_assistant
                    ->getProductCards($action['productIds']);
            }
            if ($action['type'] === 'showArticle' && isset($action['articleIndex'])) {
                $action['article'] = $this->model_module_ai_assistant
                    ->getArticle($action['articleIndex']);
            }
            if ($action['type'] === 'askFilter' && isset($action['groupId'])) {
                $action['filters'] = $this->model_module_ai_assistant
                    ->getFilterOptions($action['groupId']);
            }
        }

        echo json_encode([
            'text'    => $result['text'],
            'actions' => $actions,
        ]);
    }

    // Clear conversation state (called on "Почати спочатку")
    public function reset(): void {
        header('Content-Type: application/json');
        $this->session->data['ai_assistant_state'] = [
            'categoryId' => null,
            'filterIds'  => [],
            'history'    => [],
        ];
        echo json_encode(['ok' => true]);
    }
}
```

---

## 8. PHP Model — DB Helper

**File**: `catalog/model/module/ai_assistant.php`

Loads `db.json` once per request (cached in APCu or static property), provides all query methods, and builds the system prompt.

```php
<?php
class ModelModuleAiAssistant extends Model {

    private static ?array $db = null;

    private function getDb(): array {
        if (self::$db !== null) {
            return self::$db;
        }

        $cacheKey = 'ai_assistant_db';

        // Try APCu first (fastest)
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey, $success);
            if ($success) {
                self::$db = $cached;
                return self::$db;
            }
        }

        $path = DIR_SYSTEM . '../extension/ai_assistant/system/db.json';
        $json = file_get_contents($path);
        self::$db = json_decode($json, true);

        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, self::$db, 3600); // Cache 1 hour
        }

        return self::$db;
    }

    // ---------------------------------------------------------------
    // System prompt with current context
    // ---------------------------------------------------------------

    public function buildSystemPrompt(): string {
        $promptPath = DIR_SYSTEM . '../extension/ai_assistant/system/agent_prompt.txt';
        $base       = file_get_contents($promptPath);

        $context = sprintf(
            "\nCurrent date: %s\nStore language: %s\n",
            date('Y-m-d'),
            $this->config->get('config_language')
        );

        return $context . $base;
    }

    // ---------------------------------------------------------------
    // Category helpers
    // ---------------------------------------------------------------

    public function getTopCategories(int $limit = 8): array {
        $db    = $this->getDb();
        $emojis = $this->getCategoryEmojis();
        $result = [];

        foreach ($db['categoryTree'] as $cat) {
            $result[] = [
                'id'    => $cat['id'],
                'name'  => $cat['name']['uk'] ?? $cat['name']['en'],
                'slug'  => $cat['slug'],
                'emoji' => $emojis[$cat['id']] ?? '🌱',
            ];
            if (count($result) >= $limit) break;
        }

        return $result;
    }

    private function getCategoryEmojis(): array {
        return [
            100 => '🍅', 110 => '🥒', 120 => '🥬', 130 => '🌱',
            140 => '🌶', 150 => '🫆', 160 => '🧅', 170 => '🥕',
            180 => '🟣', 190 => '🍉', 200 => '🍈', 210 => '🎃',
            220 => '🥦', 230 => '🟡', 240 => '🌿', 250 => '🌾',
            270 => '🫘', 280 => '🫘', 290 => '🌽', 330 => '🌸',
            400 => '🧪', 500 => '🛒',
        ];
    }

    // ---------------------------------------------------------------
    // Product query methods
    // ---------------------------------------------------------------

    public function getProductsByFilters(array $filterIds, ?int $categoryId = null): array {
        $db  = $this->getDb();
        $ids = null;

        foreach ($filterIds as $fid) {
            $set = array_flip($db['indexes']['productsByFilter'][(string)$fid] ?? []);
            $ids = $ids === null ? $set : array_intersect_key($ids, $set);
        }

        if ($categoryId !== null) {
            $catSet = array_flip($db['indexes']['productsByCategory'][(string)$categoryId] ?? []);
            $ids    = $ids === null ? $catSet : array_intersect_key($ids, $catSet);
        }

        if ($ids === null) return [];

        return array_map(
            fn($id) => $db['products'][(string)$id] ?? null,
            array_keys($ids)
        );
    }

    public function searchProducts(string $query): array {
        $db     = $this->getDb();
        $tokens = preg_split('/[\s,\-]+/', mb_strtolower($query));
        $tokens = array_filter($tokens, fn($t) => mb_strlen($t) > 2);

        if (empty($tokens)) return [];

        $unionIds = [];
        foreach ($tokens as $token) {
            $found = $db['indexes']['productSearch'][$token] ?? [];
            $unionIds = array_merge($unionIds, $found);
        }

        $uniqueIds = array_unique($unionIds);
        return array_map(fn($id) => $db['products'][(string)$id] ?? null, $uniqueIds);
    }

    public function rankProducts(array $products): array {
        $products = array_filter($products);
        usort($products, function ($a, $b) {
            $aStock = ($a['stockStatus'] === 7) ? 0 : 1;
            $bStock = ($b['stockStatus'] === 7) ? 0 : 1;
            if ($aStock !== $bStock) return $aStock - $bStock;
            return ($a['price'] ?: 999) <=> ($b['price'] ?: 999);
        });
        return $products;
    }

    // ---------------------------------------------------------------
    // Data for UI rendering
    // ---------------------------------------------------------------

    public function getProductCards(array $productIds): array {
        $db    = $this->getDb();
        $cards = [];

        foreach (array_slice($productIds, 0, 5) as $id) {
            $p = $db['products'][(string)$id] ?? null;
            if (!$p) continue;

            // Resolve category name
            $catId    = $p['categoryIds'][0] ?? null;
            $catName  = $catId ? ($db['categories'][(string)$catId]['name']['uk'] ?? '') : '';

            // Top 3 attributes
            $attrs = array_slice($p['attributes'] ?? [], 0, 3);

            $cards[] = [
                'id'         => $p['id'],
                'name'       => $p['name']['uk'] ?? $p['name']['en'],
                'slug'       => $p['slug'],
                'category'   => $catName,
                'price'      => $p['price'],
                'image'      => $p['image'],
                'inStock'    => $p['stockStatus'] === 7,
                'attributes' => $attrs,
            ];
        }

        return $cards;
    }

    public function getFilterOptions(int $groupId): array {
        $db      = $this->getDb();
        $group   = $db['filterGroups'][(string)$groupId] ?? null;
        if (!$group) return [];

        $result = [];
        foreach ($group['filterIds'] as $fid) {
            $f = $db['filters'][(string)$fid] ?? null;
            if ($f) {
                $result[] = [
                    'id'   => $f['id'],
                    'name' => $f['name']['uk'] ?? $f['name']['en'],
                ];
            }
        }
        return $result;
    }

    public function getArticle(int $index): ?array {
        $db = $this->getDb();
        return $db['blog']['articles'][$index] ?? null;
    }

    // ---------------------------------------------------------------
    // Heuristic search engine — primary entry point
    // ---------------------------------------------------------------

    /**
     * Routes user query to the correct handler based on intent.
     * Returns: ['text', 'actions', 'categoryId'?, 'filterIds'?]
     */
    public function heuristicSearch(string $query, ?int $activeCategoryId, array $activeFilterIds): array {
        $q = mb_strtolower(trim($query));

        // 1. Info intents (payment / delivery)
        if ($this->matchesIntent($q, ['оплат', 'заплатити', 'оплатити', 'способи оплати', 'payment', 'оплату'])) {
            return $this->getInfoResponse('payment');
        }
        if ($this->matchesIntent($q, ['достав', 'кур\'єр', 'пошта', 'нова пошта', 'укрпошта', 'delivery', 'доставку'])) {
            return $this->getInfoResponse('delivery');
        }

        // 2. Navigation intents (catalog, sales, specific sections)
        $navResult = $this->matchNavigationIntent($q);
        if ($navResult !== null) {
            return $navResult;
        }

        // 3. Consultation — product search
        return $this->searchFlow($query, $q, $activeCategoryId, $activeFilterIds);
    }

    // ---------------------------------------------------------------
    // Info page responses (payment / delivery)
    // ---------------------------------------------------------------

    private function getInfoResponse(string $key): array {
        $db   = $this->getDb();
        $text = $db['info'][$key]['text'] ?? 'Інформація тимчасово недоступна.';
        $url  = $db['info'][$key]['url']  ?? '';

        $actions = [];
        if ($url) {
            $actions[] = ['type' => 'navigateTo', 'url' => $url, 'label' => 'Детальніше →'];
        }

        return ['text' => $text, 'actions' => $actions];
    }

    // ---------------------------------------------------------------
    // Navigation intent matching
    // ---------------------------------------------------------------

    private function matchNavigationIntent(string $q): ?array {
        $db  = $this->getDb();
        $nav = $db['navigation'] ?? [];

        // Sales / discounts
        if ($this->matchesIntent($q, ['акці', 'знижк', 'розпродаж', 'дешевш', 'sale', 'спеціальн'])) {
            return [
                'text'    => 'Переглядайте поточні акції та знижки в нашому магазині!',
                'actions' => [['type' => 'navigateTo', 'url' => $nav['sales'] ?? '/index.php?route=product/special', 'label' => 'Переглянути акції →']],
            ];
        }

        // New arrivals
        if ($this->matchesIntent($q, ['новинк', 'нові товар', 'нове надходження', 'new'])) {
            return [
                'text'    => 'Ось наші нові надходження:',
                'actions' => [['type' => 'navigateTo', 'url' => $nav['new'] ?? '/index.php?route=product/latest', 'label' => 'Переглянути новинки →']],
            ];
        }

        // All catalog
        if ($this->matchesIntent($q, ['весь каталог', 'всі товари', 'всі категорії', 'показати все', 'catalog'])) {
            return [
                'text'    => 'Переглядайте повний каталог насіння та товарів для саду:',
                'actions' => [['type' => 'navigateTo', 'url' => $nav['catalog'] ?? '/index.php?route=product/category', 'label' => 'Відкрити каталог →']],
            ];
        }

        // Specific category by slug/name in navigation index
        foreach ($nav['categories'] ?? [] as $navCat) {
            foreach ($navCat['keywords'] as $kw) {
                if (mb_strpos($q, $kw) !== false) {
                    return [
                        'text'    => 'Переходьте до розділу ' . $navCat['name'] . ':',
                        'actions' => [
                            ['type' => 'navigateTo', 'url' => $navCat['url'], 'label' => 'Переглянути ' . $navCat['name'] . ' →'],
                        ],
                        'categoryId' => $navCat['categoryId'],
                    ];
                }
            }
        }

        return null;
    }

    // ---------------------------------------------------------------
    // Consultation — product search flow
    // ---------------------------------------------------------------

    private function searchFlow(string $rawQuery, string $q, ?int $activeCategoryId, array $activeFilterIds): array {
        $db = $this->getDb();

        // Detect category and filters from message
        $categoryId = $activeCategoryId ?? $this->detectCategory($q);
        $newFilters = $this->detectFilters($q);
        $filterIds  = array_values(array_unique(array_merge($activeFilterIds, $newFilters)));

        // Attempt filtered search
        $products = $this->getProductsByFilters($filterIds, $categoryId);

        // Fallback: text search
        if (empty($products)) {
            $products = $this->searchProducts($rawQuery);
        }

        // Fallback: category only (relax filters)
        if (empty($products) && $categoryId) {
            $products = $this->getProductsByFilters([], $categoryId);
        }

        $products = $this->rankProducts($products);
        $top      = array_slice($products, 0, 5);
        $ids      = array_column($top, 'id');

        if (empty($ids)) {
            return [
                'text'       => 'На жаль, нічого не знайшлось. Уточніть, будь ласка, запит.',
                'actions'    => [['type' => 'askFilter', 'groupId' => 1, 'question' => 'Яка культура вас цікавить?']],
                'categoryId' => $categoryId,
                'filterIds'  => $filterIds,
            ];
        }

        $actions = [['type' => 'showProducts', 'productIds' => $ids]];

        // CTA: link to full category page
        if ($categoryId) {
            $cat = $db['categories'][(string)$categoryId] ?? null;
            if ($cat) {
                $catName = $cat['name']['uk'] ?? $cat['name']['en'];
                $catUrl  = $db['navigation']['categoriesById'][(string)$categoryId] ?? '/index.php?route=product/category&path=' . $categoryId;
                $actions[] = ['type' => 'navigateTo', 'url' => $catUrl, 'label' => 'Переглянути весь розділ «' . $catName . '» →'];
            }
        }

        // Ask refining filter if many results
        if (count($top) >= 5 && $categoryId) {
            $nextGroup = $this->getNextFilterGroup($categoryId, $filterIds);
            if ($nextGroup) {
                $actions[] = ['type' => 'askFilter', 'groupId' => $nextGroup['id'], 'question' => $nextGroup['question']];
            }
        }

        // Relevant blog article
        $articleIdx = $this->findRelevantArticle($categoryId);
        if ($articleIdx !== null) {
            $actions[] = ['type' => 'showArticle', 'articleIndex' => $articleIdx];
        }

        return [
            'text'       => 'Ось що знайшов для вас:',
            'actions'    => $actions,
            'categoryId' => $categoryId,
            'filterIds'  => $filterIds,
        ];
    }

    // ---------------------------------------------------------------
    // Intent detection helpers
    // ---------------------------------------------------------------

    private function matchesIntent(string $q, array $keywords): bool {
        foreach ($keywords as $kw) {
            if (mb_strpos($q, $kw) !== false) return true;
        }
        return false;
    }

    private function detectCategory(string $q): ?int {
        // Keyword → root category ID map (extend as needed)
        $map = [
            'томат' => 100, 'помідор' => 100,
            'огірок' => 110, 'огірки' => 110,
            'капуст' => 120,
            'перец' => 130, 'перець' => 130,
            'баклажан' => 140,
            'цибул' => 160,
            'морков' => 170, 'морква' => 170,
            'буряк' => 180,
            'кавун' => 190,
            'диня'  => 200,
            'гарбуз' => 210,
            'кабачок' => 220, 'кабачки' => 220,
            'зелен' => 240,
            'базил' => 250, 'петрушк' => 250, 'кріп' => 250,
            'квіт' => 330, 'квіти' => 330,
            'добрив' => 400,
        ];
        foreach ($map as $kw => $catId) {
            if (mb_strpos($q, $kw) !== false) return $catId;
        }
        return null;
    }

    private function detectFilters(string $q): array {
        // Keyword → filter ID map (IDs from db.json filters)
        $map = [
            'теплиц' => [/* filter IDs for greenhouse */],
            'балкон' => [/* filter IDs for balcony */],
            'контейнер' => [/* filter IDs for container */],
            'рання' => [/* early maturity IDs */],
            'ранній' => [/* early maturity IDs */],
            'висока врожайність' => [/* high yield IDs */],
            'консервування' => [/* preserving IDs */],
            'засолювання' => [/* pickling IDs */],
            'стійкий до хвороб' => [/* disease resistant IDs */],
            'детермінантний' => [/* determinate IDs */],
            'без підв\'язки' => [/* no staking IDs */],
            'червоний' => [/* red color IDs */],
            'жовтий' => [/* yellow color IDs */],
            'дитяч' => [/* children nutrition IDs */],
            'посухостійк' => [/* drought resistant IDs */],
        ];
        $result = [];
        foreach ($map as $kw => $ids) {
            if (mb_strpos($q, $kw) !== false) {
                $result = array_merge($result, $ids);
            }
        }
        return $result;
    }

    private function getNextFilterGroup(int $categoryId, array $usedFilterIds): ?array {
        $db           = $this->getDb();
        $groupsByCategory = $db['indexes']['filtersByCategory'][(string)$categoryId] ?? [];
        // Priority order of filter groups to ask next
        $priority = [4 => 'Яка стиглість вам підходить?', 1 => 'Який колір плодів?', 2 => 'Де будете вирощувати?'];
        foreach ($priority as $groupId => $question) {
            if (!isset($groupsByCategory[(string)$groupId])) continue;
            $groupFilterIds = $groupsByCategory[(string)$groupId];
            // Skip if user already used a filter from this group
            if (empty(array_intersect($usedFilterIds, $groupFilterIds))) {
                return ['id' => $groupId, 'question' => $question];
            }
        }
        return null;
    }

    private function findRelevantArticle(?int $categoryId): ?int {
        if (!$categoryId) return null;
        $db = $this->getDb();
        // Tomatoes (100), Cucumbers (110) → growing guides topic
        $topicMap = [100 => '2', 110 => '2', 130 => '2', 140 => '2'];
        $topic    = $topicMap[$categoryId] ?? null;
        if (!$topic) return null;
        $indices = $db['blog']['articlesByTopic'][$topic] ?? [];
        return $indices[0] ?? null;
    }
}
```

---

## 9. JavaScript — Chat UI

**File**: `catalog/view/javascript/ai_assistant.js`

Vanilla JS (no build step, no TypeScript). Loaded as a module asset by the controller.

```javascript
(function ($) {
  'use strict';

  const cfg = window.aiAssistantConfig || {};

  // ── State ──────────────────────────────────────────────────────────
  const state = {
    isOpen:          false,
    activeCategoryId: null,
    activeFilterIds:  [],
    lastProductIds:   [],
  };

  // ── DOM refs ───────────────────────────────────────────────────────
  const $panel    = $('#ai-assistant-panel');
  const $toggle   = $('#ai-assistant-toggle');
  const $close    = $('#ai-assistant-close');
  const $messages = $('#ai-chat-messages');
  const $input    = $('#ai-chat-input');
  const $send     = $('#ai-chat-send');
  const $replies  = $('#ai-quick-replies');
  const $welcome  = $('#ai-welcome-screen');

  // ── Open / Close ───────────────────────────────────────────────────
  $toggle.on('click', function () {
    state.isOpen = !state.isOpen;
    $panel.toggleClass('ai-assistant-panel--open', state.isOpen)
          .attr('aria-hidden', !state.isOpen);
    if (state.isOpen) $input.focus();
  });

  $close.on('click', function () {
    state.isOpen = false;
    $panel.removeClass('ai-assistant-panel--open').attr('aria-hidden', true);
  });

  // ── Intent buttons (welcome screen) ───────────────────────────────
  $(document).on('click', '.ai-intent-btn', function () {
    sendMessage($(this).data('message'));
  });

  // ── Send message ───────────────────────────────────────────────────
  $send.on('click', doSend);
  $input.on('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doSend(); }
  });

  // Auto-resize textarea
  $input.on('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });

  function doSend() {
    const text = $input.val().trim();
    if (!text) return;
    $input.val('').css('height', 'auto');
    sendMessage(text);
  }

  function sendMessage(text) {
    $welcome.hide();
    appendUserMessage(text);
    setLoading(true);
    $replies.empty();

    // Reset conversation
    if (text === 'Почати спочатку') {
      $.post(cfg.ajaxUrl.replace('/chat', '/reset'), function () {
        $welcome.show();
        appendAiMessage('Починаємо спочатку! Чим можу допомогти?', []);
        setLoading(false);
      });
      return;
    }

    $.ajax({
      url:         cfg.ajaxUrl,
      method:      'POST',
      contentType: 'application/json',
      data:        JSON.stringify({ message: text }),
      success:     function (resp) {
        setLoading(false);
        appendAiMessage(resp.text, resp.actions || []);
        handleActions(resp.actions || []);
      },
      error: function () {
        setLoading(false);
        appendAiMessage('Вибачте, сталася помилка. Спробуйте ще раз.', []);
      }
    });
  }

  // ── Message rendering ──────────────────────────────────────────────
  function appendUserMessage(text) {
    const $msg = $('<div class="ai-message ai-message--user"></div>').text(text);
    $messages.append($msg);
    scrollBottom();
  }

  function appendAiMessage(text, actions) {
    const $bubble = $('<div class="ai-message ai-message--ai"></div>');
    const $avatar = $('<span class="ai-message__avatar">🌱</span>');
    const $body   = $('<div class="ai-message__body"></div>');

    // Render markdown (bold, lists only — no external lib needed)
    $body.html(renderMarkdown(text));

    // Render rich components from actions
    actions.forEach(function (action) {
      if (action.type === 'showProducts' && action.products) {
        $body.append(renderProductCarousel(action.products));
      }
      if (action.type === 'askFilter' && action.filters) {
        $body.append(renderFilterChips(action.question, action.filters, action.groupId));
      }
      if (action.type === 'showArticle' && action.article) {
        $body.append(renderArticleCard(action.article));
      }
      if (action.type === 'navigateTo') {
        $body.append(renderNavigateCta(action.url, action.label));
      }
      if (action.type === 'showInfo' && action.content) {
        $body.append(renderInfoBlock(action.content));
      }
    });

    $bubble.append($avatar, $body);
    $messages.append($bubble);
    scrollBottom();
  }

  function setLoading(on) {
    $('.ai-typing-indicator').remove();
    if (on) {
      const $typing = $('<div class="ai-message ai-message--ai ai-typing-indicator">' +
        '<span class="ai-message__avatar">🌱</span>' +
        '<div class="ai-message__body"><span></span><span></span><span></span></div>' +
        '</div>');
      $messages.append($typing);
      scrollBottom();
    }
  }

  // ── Rich components ────────────────────────────────────────────────
  function renderProductCarousel(products) {
    const $wrap = $('<div class="ai-product-carousel"></div>');
    products.forEach(function (p) {
      const price     = p.price > 0 ? p.price + ' грн' : 'Ціна уточнюється';
      const stock     = p.inStock ? '<span class="ai-badge ai-badge--in">В наявності</span>'
                                  : '<span class="ai-badge ai-badge--out">Тимчасово відсутній</span>';
      const attrLines = (p.attributes || []).map(function (a) {
        return '<span class="ai-attr"><b>' + escHtml(a.name) + ':</b> ' + escHtml(a.value) + '</span>';
      }).join('');
      const imgSrc    = p.image ? cfg.imageBase + p.image : '';
      const productUrl = cfg.productUrl + p.id;

      const $card = $('<div class="ai-product-card"></div>').html(
        '<div class="ai-product-card__img">' +
          (imgSrc ? '<img src="' + escHtml(imgSrc) + '" alt="' + escHtml(p.name) + '" loading="lazy">' : '') +
        '</div>' +
        '<div class="ai-product-card__info">' +
          '<div class="ai-product-card__name">' + escHtml(p.name) + '</div>' +
          '<div class="ai-product-card__cat">' + escHtml(p.category) + '</div>' +
          '<div class="ai-product-card__attrs">' + attrLines + '</div>' +
          stock +
          '<div class="ai-product-card__price">' + escHtml(price) + '</div>' +
          '<a href="' + escHtml(productUrl) + '" class="ai-product-card__cta" target="_blank">Детальніше →</a>' +
        '</div>'
      );

      // Tap card → follow-up message
      $card.find('.ai-product-card__name').on('click', function () {
        sendMessage('Розкажіть більше про ' + p.name);
      });

      $wrap.append($card);
    });

    // Add scroll arrows if > 2 cards
    if (products.length > 2) {
      const $prev = $('<button class="ai-carousel-arrow ai-carousel-arrow--prev">‹</button>');
      const $next = $('<button class="ai-carousel-arrow ai-carousel-arrow--next">›</button>');
      $prev.on('click', function () { $wrap[0].scrollLeft -= 220; });
      $next.on('click', function () { $wrap[0].scrollLeft += 220; });
      return $('<div class="ai-carousel-wrap"></div>').append($prev, $wrap, $next);
    }

    return $wrap;
  }

  function renderFilterChips(question, filters, groupId) {
    const $wrap  = $('<div class="ai-filter-group"></div>');
    const $label = $('<div class="ai-filter-group__label"></div>').text(question || 'Оберіть:');
    const $chips = $('<div class="ai-filter-group__chips"></div>');

    filters.forEach(function (f) {
      const $chip = $('<button class="ai-chip"></button>')
        .text(f.name)
        .attr('data-filter-id', f.id)
        .on('click', function () {
          $(this).toggleClass('ai-chip--active');
          // Auto-send after short delay if single-select
          const active = $chips.find('.ai-chip--active');
          state.activeFilterIds.push(f.id);
          sendMessage(f.name);
        });
      $chips.append($chip);
    });

    // "Не важливо" skip option
    $('<button class="ai-chip ai-chip--skip">Не важливо</button>')
      .on('click', function () { sendMessage('Не важливо'); })
      .appendTo($chips);

    return $wrap.append($label, $chips);
  }

  function renderArticleCard(article) {
    const url = cfg.blogUrl + (article.slug || '');
    return $('<div class="ai-article-card"></div>').html(
      '<div class="ai-article-card__icon">📖 Стаття</div>' +
      '<div class="ai-article-card__title">' + escHtml(article.title) + '</div>' +
      '<div class="ai-article-card__meta">' + escHtml(article.dateAdded || '') + '</div>' +
      '<div class="ai-article-card__summary">' + escHtml(article.summary || '') + '</div>' +
      '<a href="' + escHtml(url) + '" class="ai-article-card__cta" target="_blank">Читати статтю →</a>'
    );
  }

  // Renders a prominent CTA button that navigates to a catalog/category/sales URL
  function renderNavigateCta(url, label) {
    return $('<div class="ai-navigate-cta"></div>').html(
      '<a href="' + escHtml(url) + '" class="ai-navigate-cta__btn" target="_blank">' +
        escHtml(label || 'Перейти →') +
      '</a>'
    );
  }

  // Renders a formatted info block (payment / delivery page content)
  function renderInfoBlock(content) {
    return $('<div class="ai-info-block"></div>').html(renderMarkdown(escHtml(content)));
  }

  // ── Quick replies ──────────────────────────────────────────────────
  function renderQuickReplies(context) {
    $replies.empty();
    const suggestions = getContextSuggestions(context);
    suggestions.forEach(function (s) {
      $('<button class="ai-quick-reply"></button>')
        .text(s)
        .on('click', function () { sendMessage(s); })
        .appendTo($replies);
    });
  }

  function getContextSuggestions(context) {
    if (context === 'start') return ['Консультація', 'Допомога з оплатою', 'Допомога з доставкою'];
    if (context === 'category') return [
      'За стиглістю', 'За кольором', 'Для консервування', 'Хочу порівняти сорти'
    ];
    if (context === 'products') return [
      'Є щось дешевше?', 'Що краще для консервування?', 'Показати статтю по вирощуванню', 'Почати спочатку'
    ];
    if (context === 'info') return [
      'Консультація', 'Показати каталог', 'Переглянути акції', 'Почати спочатку'
    ];
    return ['Показати каталог', 'Переглянути акції', 'Порада по вирощуванню', 'Почати спочатку'];
  }

  function handleActions(actions) {
    const types = actions.map(function (a) { return a.type; });
    if (types.includes('showProducts'))   renderQuickReplies('products');
    else if (types.includes('showInfo') || types.includes('navigateTo')) renderQuickReplies('info');
    else if (types.includes('showCategory')) renderQuickReplies('category');
    else renderQuickReplies('general');
  }

  // ── Utilities ──────────────────────────────────────────────────────
  function scrollBottom() {
    $messages[0].scrollTop = $messages[0].scrollHeight;
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function renderMarkdown(text) {
    return text
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g, '<em>$1</em>')
      .replace(/\n/g, '<br>');
  }

})(jQuery);
```

---

## 10. CSS

**File**: `catalog/view/stylesheet/ai_assistant.css`

```css
/* ── Trigger button ─────────────────────────────────── */
.ai-assistant-trigger {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9998;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: #2d6a4f;
  color: #fff;
  border: none;
  border-radius: 50px;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0,0,0,.2);
  font-size: 15px;
  font-weight: 600;
  transition: transform .15s, box-shadow .15s;
}
.ai-assistant-trigger:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.25); }

/* ── Panel ──────────────────────────────────────────── */
.ai-assistant-panel {
  position: fixed;
  bottom: 88px;
  right: 24px;
  z-index: 9999;
  width: 420px;
  max-width: calc(100vw - 32px);
  height: 600px;
  max-height: calc(100vh - 100px);
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 8px 40px rgba(0,0,0,.18);
  display: none;
  flex-direction: column;
  overflow: hidden;
  font-family: inherit;
}
.ai-assistant-panel--open { display: flex; }

.ai-assistant-panel__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: #2d6a4f;
  color: #fff;
  font-weight: 600;
  font-size: 15px;
  flex-shrink: 0;
}
.ai-assistant-panel__close {
  background: none; border: none; color: #fff; cursor: pointer; font-size: 18px; line-height: 1;
}

.ai-assistant-panel__body {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ai-assistant-panel__quick-replies {
  display: flex;
  flex-wrap: nowrap;
  overflow-x: auto;
  gap: 6px;
  padding: 8px 12px;
  border-top: 1px solid #eee;
  flex-shrink: 0;
}

.ai-assistant-panel__footer {
  display: flex;
  gap: 8px;
  padding: 10px 12px;
  border-top: 1px solid #eee;
  flex-shrink: 0;
}
.ai-assistant-panel__input {
  flex: 1;
  border: 1px solid #ccc;
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 14px;
  resize: none;
  font-family: inherit;
  line-height: 1.4;
}
.ai-assistant-panel__send {
  padding: 8px 16px;
  background: #2d6a4f;
  color: #fff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
}

/* ── Messages ───────────────────────────────────────── */
.ai-message { display: flex; gap: 8px; max-width: 90%; }
.ai-message--ai  { align-self: flex-start; }
.ai-message--user {
  align-self: flex-end;
  flex-direction: row-reverse;
}
.ai-message--user .ai-message__body {
  background: #2d6a4f;
  color: #fff;
  border-radius: 16px 16px 4px 16px;
}
.ai-message__avatar { font-size: 20px; flex-shrink: 0; padding-top: 4px; }
.ai-message__body {
  background: #f0f7f0;
  border-radius: 4px 16px 16px 16px;
  padding: 10px 14px;
  font-size: 14px;
  line-height: 1.5;
}

/* ── Typing indicator ───────────────────────────────── */
.ai-typing-indicator .ai-message__body { padding: 14px; }
.ai-typing-indicator span {
  display: inline-block; width: 6px; height: 6px;
  background: #2d6a4f; border-radius: 50%; margin: 0 2px;
  animation: ai-bounce .8s infinite;
}
.ai-typing-indicator span:nth-child(2) { animation-delay: .15s; }
.ai-typing-indicator span:nth-child(3) { animation-delay: .3s; }
@keyframes ai-bounce { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-6px)} }

/* ── Product carousel ───────────────────────────────── */
.ai-carousel-wrap { position: relative; }
.ai-product-carousel {
  display: flex;
  gap: 10px;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  padding-bottom: 4px;
  scrollbar-width: none;
}
.ai-product-carousel::-webkit-scrollbar { display: none; }
.ai-carousel-arrow {
  position: absolute; top: 50%; transform: translateY(-50%);
  background: rgba(255,255,255,.9); border: 1px solid #ddd;
  border-radius: 50%; width: 28px; height: 28px;
  cursor: pointer; font-size: 18px; line-height: 1; z-index: 1;
}
.ai-carousel-arrow--prev { left: -14px; }
.ai-carousel-arrow--next { right: -14px; }

.ai-product-card {
  display: flex;
  flex-direction: column;
  min-width: 190px;
  max-width: 190px;
  border: 1px solid #e0ede6;
  border-radius: 10px;
  overflow: hidden;
  scroll-snap-align: start;
  font-size: 13px;
}
.ai-product-card__img img { width: 100%; height: 110px; object-fit: cover; }
.ai-product-card__info { padding: 8px 10px; display: flex; flex-direction: column; gap: 4px; }
.ai-product-card__name { font-weight: 600; cursor: pointer; color: #1a3a2a; }
.ai-product-card__name:hover { text-decoration: underline; }
.ai-product-card__cat  { color: #666; font-size: 12px; }
.ai-product-card__attrs { display: flex; flex-direction: column; gap: 2px; }
.ai-attr { font-size: 12px; color: #444; }
.ai-product-card__price { font-weight: 700; color: #2d6a4f; margin-top: 4px; }
.ai-product-card__cta {
  margin-top: 6px; padding: 5px 10px; background: #2d6a4f; color: #fff;
  border-radius: 6px; text-decoration: none; font-size: 12px; text-align: center;
}
.ai-badge { font-size: 11px; padding: 2px 6px; border-radius: 4px; }
.ai-badge--in  { background: #d4edda; color: #155724; }
.ai-badge--out { background: #f8d7da; color: #721c24; }

/* ── Filter chips ───────────────────────────────────── */
.ai-filter-group { margin-top: 6px; }
.ai-filter-group__label { font-size: 13px; font-weight: 600; margin-bottom: 6px; }
.ai-filter-group__chips { display: flex; flex-wrap: wrap; gap: 6px; }
.ai-chip {
  padding: 5px 12px; border: 1px solid #b7d9c5; border-radius: 20px;
  background: #fff; cursor: pointer; font-size: 13px; color: #2d6a4f;
  transition: background .15s, color .15s;
}
.ai-chip:hover, .ai-chip--active { background: #2d6a4f; color: #fff; border-color: #2d6a4f; }
.ai-chip--skip { color: #999; border-color: #ddd; }

/* ── Article card ───────────────────────────────────── */
.ai-article-card {
  background: #fef9e7;
  border: 1px solid #f0d060;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 13px;
  margin-top: 6px;
}
.ai-article-card__icon { color: #b8860b; font-size: 12px; margin-bottom: 4px; }
.ai-article-card__title { font-weight: 600; margin-bottom: 4px; }
.ai-article-card__meta  { color: #888; font-size: 12px; margin-bottom: 6px; }
.ai-article-card__cta {
  display: inline-block; padding: 4px 10px; background: #b8860b;
  color: #fff; border-radius: 6px; text-decoration: none; font-size: 12px; margin-top: 6px;
}

/* ── Quick replies ──────────────────────────────────── */
.ai-quick-reply {
  white-space: nowrap; padding: 5px 12px; border: 1px solid #b7d9c5;
  border-radius: 20px; background: #fff; cursor: pointer; font-size: 13px; color: #2d6a4f;
}
.ai-quick-reply:hover { background: #f0f7f0; }

/* ── Welcome screen ─────────────────────────────────── */
.ai-welcome { text-align: center; padding: 20px 10px; }
.ai-welcome__icon { font-size: 48px; margin-bottom: 12px; }
.ai-welcome__text { font-size: 15px; font-weight: 600; margin-bottom: 6px; }
.ai-welcome__sub  { font-size: 13px; color: #666; margin-bottom: 16px; }
.ai-category-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-top: 10px;
}
.ai-category-btn {
  padding: 10px; border: 1px solid #b7d9c5; border-radius: 10px;
  background: #fff; cursor: pointer; font-size: 14px; text-align: left;
  transition: background .15s;
}
.ai-category-btn:hover { background: #f0f7f0; }
.ai-category-btn--more { color: #2d6a4f; font-style: italic; }

/* ── Mobile ─────────────────────────────────────────── */
@media (max-width: 767px) {
  .ai-assistant-panel {
    width: 100vw; max-width: 100vw; height: 100dvh; max-height: 100dvh;
    bottom: 0; right: 0; border-radius: 0;
  }
  .ai-assistant-trigger { bottom: 16px; right: 16px; }
}
```

---

## 11. Admin Settings Page

**File**: `admin/controller/module/ai_assistant.php`

Provides a settings form at **Admin → Extensions → Modules → AI Assistant**:

| Field | Description |
|---|---|
| Status | Enable / Disable |
| Widget position | Layout position selector (header_bottom, footer, content_bottom, etc.) |
| Payment info page ID | OpenCart Information page ID containing payment methods text |
| Delivery info page ID | OpenCart Information page ID containing delivery terms text |
| Callback URL | URL or phone link for "Замовити дзвінок" button (e.g. `tel:+380XXXXXXXXX` or a contact page URL) |
| Rebuild DB cache | Button — re-reads `db.json`, re-fetches payment/delivery info page texts and stores them into `db.json info.*` |

On **save**, the admin controller:
1. Reads the specified Information pages from the OpenCart `information_description` table
2. Strips HTML tags to plain text
3. Writes the result into `db.json` under `info.payment.text` / `info.delivery.text` and stores the page URL under `info.payment.url` / `info.delivery.url`
4. Clears the APCu cache so the next request loads the updated `db.json`

---

## 12. Language Files

**File**: `catalog/language/uk-ua/module/ai_assistant.php`

```php
<?php
$_['text_assistant']        = 'Помічник садівника';
$_['text_open']             = 'Відкрити помічника';
$_['text_close']            = 'Закрити';
$_['text_welcome']          = 'Доброго дня! Вам потрібна консультація? З радістю відповім на ваші запитання.';
$_['text_welcome_sub']      = 'Оберіть, з чим вам допомогти:';
$_['text_intent_consult']   = 'Консультація';
$_['text_intent_payment']   = 'Допомога з оплатою';
$_['text_intent_delivery']  = 'Допомога з доставкою';
$_['text_placeholder']      = 'Напишіть ваш запит...';
$_['text_send']             = 'Надіслати';
$_['text_suggestions']      = 'Підказки';
$_['text_callback']         = 'Замовити дзвінок';
```

---

## 13. Extension Manifest (OC4)

**File**: `install.json`

```json
{
  "name": "AI Garden Assistant",
  "version": "1.0.0",
  "author": "Fun Dacha",
  "description": "Conversational AI assistant for seed selection",
  "link": "",
  "type": "module",
  "codename": "ai_assistant",
  "xml": ""
}
```

---

## 14. Files to Create (Complete List)

```
extension/ai_assistant/
  admin/
    controller/module/ai_assistant.php
    language/en-gb/module/ai_assistant.php
    language/uk-ua/module/ai_assistant.php
    view/template/module/ai_assistant.twig
  catalog/
    controller/
      module/ai_assistant.php          ← widget injection
      api/ai_assistant.php             ← AJAX: chat + reset
    model/module/ai_assistant.php      ← DB helper + Claude API call
    language/
      en-gb/module/ai_assistant.php
      uk-ua/module/ai_assistant.php
    view/
      template/module/
        ai_assistant_widget.twig       ← widget HTML
        ai_assistant_page.twig         ← full-page template
      javascript/
        ai_assistant.js                ← chat UI logic
      stylesheet/
        ai_assistant.css               ← all styles
  system/
    db.json                            ← indexed database (pre-built)
    agent_prompt.txt                   ← AI system prompt
  install.json                         ← OC4 manifest
```

---

## 15. OpenCart Integration Notes

- **Inject widget**: in `catalog/controller/module/ai_assistant.php::index()`, output JS/CSS includes + widget HTML. The admin layout editor assigns this module to a position (e.g. `content_bottom`).
- **AJAX route**: the endpoint is at `index.php?route=api/ai_assistant/chat`. Enable in `config.php` if using OC4's new routing.
- **Session**: use OpenCart's `$this->session->data` (already available in controllers). No extra session_start() needed.
- **API key storage**: use `$this->config->get('module_ai_assistant_api_key')` — stored via the admin settings form in `oc_setting` table.
- **db.json path**: `DIR_EXTENSION . 'ai_assistant/system/db.json'` — place the pre-built file here during install.
- **Cart integration (Phase 2)**: product CTA button can call `cart/add` route using OpenCart's existing AJAX cart endpoint.

---

## 16. Phase 2 Enhancements

- **"Додати до кошика"** button using OpenCart's `cart/add` AJAX endpoint
- **Comparison table** for 2–3 selected products
- **Seasonal banner** — detect current month, suggest what to plant now
- **localStorage preferences** — remember growing conditions between visits
- **OpenCart customer integration** — if logged in, greet by name and recall past orders for context
