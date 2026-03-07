# FreshGo OpenCart Theme — Design Analysis

**Source URLs analyzed:**
1. Homepage: https://opencart.dostguru.com/FD03/freshgo_01/
2. Category: https://opencart.dostguru.com/FD03/freshgo_01/index.php?route=product/category&path=18
3. Product: https://opencart.dostguru.com/FD03/freshgo_01/index.php?route=product/product&path=18&product_id=47

**Theme:** FreshGo — Organic & Supermarket OpenCart Food Store (eptheme / ThemeForest #34158000)

---

## 1. Header Structure

### Top Bar (#top)
- **Background:** Light gray (`#f7f7f7` area) with border-bottom
- **Left:** Currency selector, Language selector
- **Right:** Phone/Contact, Shopping Cart link, Checkout link
- **Typography:** Font size ~1.1em, gray text (`var(--bs-gray-600)`)

### Main Header
- **Layout:** 3-column grid
  - **Col 1 (logo):** Logo area, max-width ~200px, centered on mobile, left-aligned on desktop
  - **Col 2 (search):** Search bar with:
    - Input: height 45px, no border, font-size 14px, border-radius 0
    - Button: height 45px, border-radius `0 10px 10px 0`
  - **Col 3 (cart):** Cart dropdown
    - **Cart button:** Green background `#4b8106`, white text, border-radius 10px, min-height 35px
    - **Hover:** Background changes to black `#000`
    - **Dropdown:** min-width 295px, white/light background when open

### Navigation Menu (#menu)
- **Background:** Primary green `#4b8106` with gradient (`linear-gradient(to bottom, lighter green, #4b8106)`
- **Border:** 1px solid darker green, border-radius 6px
- **Height:** min-height 40px, padding 0 1rem
- **Nav items:** White text, text-shadow, padding 10px 15px
- **Hover:** Darker green background
- **Dropdown:** Megamenu support, multi-column layout (dropdown-inner flex/table)
- **Category label:** "Category" or similar, white, semibold, letter-spacing 0.5px

---

## 2. Color Scheme

| Role | Hex | Usage |
|------|-----|-------|
| **Primary** | `#4b8106` | Buttons, cart, menu, links hover, focus states |
| **Primary Hover** | `#000` | Button hover, cart hover, link hover |
| **Link Hover** | `#4b8106` | Breadcrumb, list items, interactive elements |
| **Background** | `#fff` | Page background |
| **Borders** | `#eee`, `#ddd`, `#e5e5e5` | Form controls, cards, dividers |
| **Form Focus** | `#4b8106` | Input border on focus |
| **Breadcrumb BG** | `#f7f7f7` | Breadcrumb container |
| **Rating Stars** | `#ffc600`, `#E69500` | Star ratings |
| **Old Price** | `#dc512c` (or similar) | Strikethrough price |
| **Footer** | `#303030` | Footer background (dark) |
| **Footer Text** | `#e2e2e2`, `#ccc` | Footer links/text |

**Organic/Food Theme:** Green primary conveys freshness and natural products.

---

## 3. Typography

| Element | Font | Size | Weight | Notes |
|---------|------|------|--------|-------|
| **Body** | Cairo | 14px | 400 | Line-height 20px, letter-spacing 0.6px |
| **Headings** | Cairo | 33px / 27px / 21px / 15px / 12px / 10.2px | 600 (b, strong) | h1–h6 |
| **Accent/Display** | Great Vibes | — | — | Decorative/script |
| **Labels** | Cairo | 13px | normal | Form labels |
| **Legend** | Cairo | 18px | — | Form section titles |
| **Buttons** | Cairo | 14px (default), 15px (lg) | — | Text-transform: capitalize |
| **Dropdown titles** | — | 15px | 600 | Uppercase, black |
| **Cart/Buttons** | — | 14–15px | — | — |

**Font loading:**
```html
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
```

---

## 4. Product Card Layout

### Structure
- **Container:** `.product-thumb` — border 1px solid `#ddd`, position relative, full height
- **Image:** Centered, hover opacity 0.8
- **Description:** Padding 15px, margin-bottom 45px
- **Title (h4):** Bold, primary-dark color
- **Price:** `.price` — color `#444`; `.price-new` font-weight 600; `.price-old` strikethrough, red
- **Rating:** FontAwesome stars, color `#ffc600`, ~13px

### Action Buttons (3-column)
- **Layout:** Flex, position absolute bottom, full width
- **Style:** No border except top, background `var(--bs-tertiary-bg)`, gray text
- **Hover:** Background `#ddd`
- **Dividers:** 1px border between buttons
- **Common actions:** Wishlist, Compare, Add to Cart (or similar)

### List View (≥960px)
- Horizontal flex layout
- Image on left, content ~75%, buttons on right with left border

### Distinctive Elements
- **Quick View:** Webi QuickView modal
- **Badges:** Sale/new/organic badges (theme-dependent)
- **Hover effects:** Image opacity, button background change

---

## 5. Category Page Layout

- **Breadcrumb:** Gray background `#f7f7f7`, padding 8px 15px, border-radius 10px, font-weight 600
- **Breadcrumb separator:** `|` with padding 0 15px
- **Sidebar:** Category list (`.list-group`)
  - Border 1px solid `#eee`, padding 12px
  - Active: white background, green text `#4b8106`, green border
  - Hover: white background, green text
- **Product grid:** Responsive columns (Bootstrap grid)
- **View toggle:** Grid (`#cgrid`) vs List (`#clist`) SVG icons
- **Pagination:** Standard Bootstrap-style

---

## 6. Product Page Layout

### Image Gallery
- **Main image:** Product zoom (elevatezoom / eptheme product-slider-zoom)
- **Thumbnails:** `.image-additional` — max-width 78px, border 1px solid `#ddd`, padding 5px, margin-bottom 20px
- **Lightbox:** Magnific Popup for full-size view

### Product Info
- **Title:** h1/h2 styling
- **Price:** Prominent, with old price strikethrough if sale
- **Add to Cart:** Primary green button, border-radius 10px
- **Quantity:** Input with standard form styling
- **Options:** Dropdowns/inputs with 10px border-radius

### Description
- Tabbed or accordion layout (nav-tabs)
- Full product description, specs, etc.

### Related/Cross-sell
- Carousel/slider (Owl Carousel, Slick, Swiper)

---

## 7. Footer Structure

- **Background:** `#303030`
- **Text:** `#e2e2e2`, links `#ccc`
- **Link hover:** `#fff`
- **Border:** Top 1px solid `#ddd`
- **Section headings (h5):** Open Sans (or theme default), 13px, bold, white
- **Sections:** Multiple columns (About, Contact, Links, etc.)
- **Dividers:** `hr` with border-bottom 1px solid `#666`
- **Position:** Absolute bottom, full width, padding-top 30px

---

## 8. Distinctive UI Elements

### Icons
- **Font Awesome** for general UI
- **Custom SVG symbols** for:
  - Arrows: `#arleft`, `#arright`
  - Quote: `#quote`
  - Header: `#hcall`, `#hsearch`, `#huser`, `#hcart`
  - Product: `#bquick` (quick view), `#heart`, `#compare`, `#pcart`, `#gift`
  - Payment: `#pay`, `#ae`, `#mc`, `#visa`, `#dis`
  - Layout: `#cgrid`, `#clist`
  - Account: `#acwishlist`, `#acpoder`, `#acppass`, `#acppay`, etc.

### Border Radius
- **10px:** Buttons, form controls, breadcrumb, cart, alerts, list-group items
- **6px:** Menu bar
- **0:** Search input (left side), some inputs

### Spacing
- **Content padding:** 15px typical
- **Section margins:** 20px (e.g. `margin-bottom: 20px`)
- **Container widths:** 963px (992–1199px), 1170px (1200–1409px), 1380px (≥1410px)

### Animations
- **Transitions:** 0.3s ease on links, buttons, cart
- **Animate.css:** For entrance/scroll effects
- **Hover:** Opacity, background, color changes

### Other
- **Cookie banner:** Fixed bottom, `#343a40` background, ~150px height, z-index 9999
- **Alerts:** Border-radius 10px, fixed positioning
- **Form controls:** Box-shadow none, border 1px solid `#eee`, focus border green

---

## 9. Responsive Breakpoints

| Breakpoint | Container Width |
|------------|-----------------|
| &lt; 992px | 100% |
| 992–1199px | 963px |
| 1200–1409px | 1170px |
| ≥ 1410px | 1380px |

---

## 10. Technology Stack

- **Framework:** Bootstrap
- **Sliders:** Owl Carousel, Slick, Swiper
- **Lightbox:** Magnific Popup, Lightbox 2.6 (blog)
- **Product zoom:** elevatezoom (eptheme)
- **Quick view:** Webi QuickView
- **Newsletter:** Webi Newsletter
- **Countdown:** jQuery Countdown (deals)
- **Animations:** Animate.css, eptheme animate.js

---

*Document generated from live site HTML/CSS analysis and ThemeForest theme documentation.*
