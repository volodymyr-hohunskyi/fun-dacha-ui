# Deterministic pack option pricing

This project uses **static** OpenCart option price modifiers (absolute `+` amounts), not runtime unit math. Helpers live under `system/library/pack/`.

## Rules

1. **Base price** is the catalog `product.price` (e.g. `12.00`). It is fixed for the SKU.
2. **Pack sizes** are separate **option values** (e.g. 1, 2, 5) on one option (e.g. “Pack quantity”).
3. For each pack size you choose a **target unit price** \(u_s\) (currency per unit in that pack).
4. **Total pack price** (before store-wide sale):  
   `total_s = u_s × pack_size`
5. **Option modifier** stored in `oc_product_option_value`:  
   `modifier_s = total_s − base_price`  
   with `price_prefix = '+'` and `price = modifier_s`.
6. **Runtime (cart)** — OpenCart computes:  
   `price_after_option = product.price + modifier`  
   (see `system/library/cart/cart.php`).
7. **Store sale** — a **percentage** row in `product_discount` with `type = 'P'` is applied to **that full line** (base + modifiers), not to base alone:  
   `final = price_after_option × (1 − percent/100)`  
   Same order as **OPTION → SPECIAL** in the cart: options first, then discount.

## What not to do

- Do **not** apply a percentage to `product.price` only and expect it to match cart lines with options.
- Do **not** recompute modifiers in theme/JS; keep them in the DB / import sheet only.
- Do **not** mix “unit price in template” with “total in cart” unless you use the formulas above.

## Catalog vs cart

- **Listing / PDP** show `product.price` and **special** from `product_discount` subqueries on **base only** (see `catalog/model/catalog/product.php`). That can differ from a **line** that includes pack options. For **one** canonical display, use the **base** as the 1-pack total and document that multi-pack totals add modifiers + sale as in the cart.
- **Cart** uses **raw** `product.price` + option deltas + one `product_discount` row — that is the **source of truth** for what customers pay.

## Compute modifiers (CLI)

From repo root:

```bash
php extension/export_import/install/compute_pack_modifiers.php --base=12 --units="1:12,2:11,5:10" --discount=10
```

- `--units` is `pack_size:target_unit_price` pairs.
- Output lists **modifier** to put in **ProductOptionValues** (`price` with `+`).
- Exit code `2` if **monotonic** check fails (larger pack must not be more expensive per unit than a smaller pack).

## PHP API

```php
$computed = \Opencart\System\Library\Pack\PackPricing::computeModifiers(12.0, [
    1 => 12.0,
    2 => 11.0,
    5 => 10.0,
]);
$check = \Opencart\System\Library\Pack\PackPricingValidator::validate($computed, 12.0, 10.0);
```

## Validation

`PackPricingValidator::validate()` recomputes:

- `price_after_option = base + modifier`
- `final_price` after sale percent
- `effective_unit_price = final_price / pack_size`

and asserts **effective unit price** does not **rise** when pack size increases (volume discount monotonicity).
