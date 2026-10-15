# Shopify Grow Implementation Plan — Conditional School Selection and Donation Disclosure

**Version:** 1.0  
**Store plan:** Shopify Grow (no Plus required for theme-level product/cart UI)  
**Integration:** Existing Shopify theme + custom backend API  
**Business rule:** Exactly one school per entire order, even when customers buy multiple items.

## 1. Goal

Allow customers to select a school on product pages and/or cart page; show the backend-configured donation percentage and estimated amount; carry a single school ID with the Shopify order; preserve standard Shopify checkout/payment flow; support a mandatory-selection enforcement strategy compatible with Grow.

**Not in scope:** A custom dropdown inside the information/shipping/payment steps of Shopify checkout on Grow. Core checkout UI extensions are a Shopify Plus capability.

## 2. Intended customer experience

### 2.1 Product detail page (one product being viewed)
- Show a compact **Support a School** panel near Add to Cart.
- Fetch approved schools via `GET /api/storefront/schools`, with search, loading, empty and error states.
- Fetch current donation settings via `GET /api/storefront/donation-settings`.
- Display disclosure, e.g. **“10% of the eligible merchandise price supports your selected school at no additional cost.”** This assumes included-in-price donations.
- School selection here is a **cart-wide preference**, not per-product. On Add to Cart, persist chosen `school_id` in a Shopify cart attribute.
- If the cart already has a school, preselect it on all product pages.
- If buyer chooses a different school while adding another product, replace the cart-wide school; make this visible in confirmation/toast.

### 2.2 Cart page / cart drawer (authoritative confirmation)
- Always render a **single** school selector regardless of cart size.
- Show products, eligible merchandise subtotal, dynamic configured percentage, donation estimate, and selected school name.
- For a single item, prefill the product-page choice if present; allow change.
- For multiple items, retain one selected school; clearly state that the donation from **all eligible products in this order** will go to it.
- If no choice exists, block *theme* Checkout button and show inline validation. If saving fails, keep checkout blocked in the theme and allow retry.
- Disable/hide theme express checkout buttons when possible to reduce bypass, but **theme JavaScript alone cannot guarantee mandatory selection**.
- Keep the selector above primary checkout CTA and in mobile cart drawer if the active theme uses one.

### 2.3 Product quantity vs distinct products
- `cart.item_count` is the sum of quantities, not distinct products.
- `cart.items.length` is the count of cart line items (variants/properties can make several lines from one product).
- Determine **distinct product IDs** only if UI wording actually needs “different products.” This does **not** change the core school rule: one school per cart/order in every case.
- Avoid needlessly switching selector behavior when quantities change. Product-page selection is a convenience; cart is the final confirmation.

### 2.4 Price and donation disclosure

**Initial assumed business model: donation included in price.**

| Cart example | Amount |
|---|---:|
| T-shirt | $50.00 |
| Shoes | $100.00 |
| Backpack | $50.00 |
| Eligible subtotal | $200.00 |
| Donation percentage | 10% |
| School allocation | $20.00 |
| Merchandise total before shipping/tax | $200.00 |

Disclosure: **“10% of eligible merchandise purchases is allocated to the school you select. This is included in product prices; no additional donation fee is added.”**

Rate display must specify base (discounted eligible merchandise subtotal excluding tax/shipping). When discounts/products change, recompute the **estimate** on cart. The backend is authoritative for the final paid-order calculation. Never describe the school allocation as an extra charge unless actual checkout pricing includes that charge.

If the business instead wants an **additional mandatory charge**, this is a separate change request: create a valid Shopify priced line item/pricing implementation, synchronize amount after cart edits/discounts, prevent its removal, and confirm tax treatment. Cart attributes alone do not change price.

## 3. Theme implementation plan

### 3.1 Integration method

Preferred: **Shopify theme app extension / app embed or theme blocks** when supported by the storefront and existing app deployment arrangement. Alternative: direct edits to the current theme's product and cart Liquid sections plus JavaScript/CSS assets.

- Do not expose privileged API keys in browser code.
- For cross-origin public API access, configure CORS correctly; a Shopify **app proxy** may simplify same-origin requests, subject to the installed app setup.
- Paginate/search approved schools; avoid downloading huge school lists per page.
- Cache public configuration briefly, handle backend downtime and stale approved school state.

### 3.2 API interaction

```javascript
const [schoolsResponse, settingsResponse] = await Promise.all([
  fetch('https://api.example.com/api/storefront/schools?search='),
  fetch('https://api.example.com/api/storefront/donation-settings')
]);
if (!schoolsResponse.ok || !settingsResponse.ok) {
  throw new Error('School selection temporarily unavailable');
}
const schools = await schoolsResponse.json();
const settings = await settingsResponse.json();
```

Render using safe DOM APIs (`textContent`, created elements). Never interpolate backend school names into unsafe `innerHTML`.

### 3.3 Read and save cart-wide school selection

```javascript
async function loadCart() {
  const response = await fetch(window.Shopify.routes.root + 'cart.js');
  if (!response.ok) throw new Error('Could not load cart');
  return response.json();
}

async function saveCartSchool(schoolId, schoolName) {
  const response = await fetch(window.Shopify.routes.root + 'cart/update.js', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      attributes: { school_id: String(schoolId), school_name: schoolName }
    })
  });
  if (!response.ok) throw new Error('Could not save school selection');
  return response.json();
}
```

Persist stable ID for authority; `school_name` is display-only and not trusted for accounting. For cartwide logic **do not** use product line-item properties; those are for an intentionally different design (a school per product).

### 3.4 Pseudocode for conditional interface

```text
On product page:
  load cart + settings + approved schools
  selected = cart.attributes.school_id ?? null
  show selector; show product donation estimate
  on product page selection -> save cart-wide school attribute
  on Add to Cart -> keep school selection; never replace silently

On cart page or drawer:
  load cart + settings + approved schools
  count distinct products only for optional wording
  render one school selector for whole order
  if chosen school exists and is still approved: preselect
  else: ask customer to select valid school
  display eligible product subtotal and estimated donation
  save school selection before enabling primary checkout
  if quantity or products change -> refresh totals; keep same school
```

### 3.5 SPA-like theme events and accessibility
- Refresh selector UI on Ajax cart updates, cart drawer opens, quantity changes, variant changes and partial theme section renders.
- Avoid attaching duplicate event listeners and overwriting customer selection on rerender.
- Provide associated label, searchable input, keyboard control, ARIA error description, loading state and understandable error messaging.
- Mobile layout first; confirm layout in product page, cart drawer and dedicated cart page.

## 4. Mandatory school selection — Shopify Grow caveat

**Front-end disabling is not secure enforcement.** Direct checkout links, Buy Now, Shop Pay and other express methods can bypass product/cart theme JavaScript. A hard “mandatory” promise requires server-side purchase validation.

**Shopify plan limitation:** As documented for Shopify Functions, eligible **public Shopify App Store apps** with validation Functions can be used on non-Plus plans, whereas a merchant-specific **custom app containing Shopify Functions** generally requires Plus. Shopify's Cart and Checkout Validation API covers express checkout as well, but a Grow store cannot simply deploy a private custom validation Function. Validate availability of an appropriate existing public app (and that it can check the cart attribute) before committing to strict enforcement on Grow.

**Also important:** Shopify validation Functions do not normally call your school backend live on Grow. Approved IDs must be validated using Function-accessible Shopify data or a suitable supported mechanism; do not assume real-time HTTP checks from checkout.

**Implementation gates:**
1. Audit store's installed apps and checkout pathways.
2. Find/test a compatible public validation app that blocks missing/invalid school choice including express paths; verify it supports your attribute and approval logic.
3. If no such app can satisfy strict mandatory validation, escalate to Shopify Plus / another supported deployment approach, or document the residual limitation. Do not label a front-end-only solution “guaranteed mandatory.”
4. Ensure blocked express checkout presents a clear actionable message and route to selection.

On **Shopify Plus**, custom Checkout UI Extensions can show the selector *inside* the information/shipping/payment stages, and custom apps with Shopify Functions can implement checkout validation. Plus is **not required** merely to fetch schools and show donation disclosures on product/cart pages.

## 5. Donation-settings synchronization

- Admin changes a versioned rate in custom backend.
- Storefront fetches current percentage and disclaimer; product and cart UIs refresh.
- In browser use a displayed estimate only; the backend recalculates from actual order records and recorded rate policy.
- Define **rate locking** explicitly: checkout/order time versus cart creation. Store `donation_settings_version` as cart attribute for display/audit if needed, but **never trust client-supplied percentage** for the money ledger.
- If cart becomes stale while shopper browses, refresh rate before checkout and show changed amounts clearly.
- Decide exclusions, discounts, gift cards, taxes, international currencies, refunds, and product bundles before launch.

## 6. Shopify event flow

```text
Backend schools approved -> public API
Backend donation settings -> public API
         |
Product page selector -> Shopify cart.attributes.school_id
         |
Cart page single-school confirmation + disclosure
         |
Shopify checkout -> existing Stripe payment gateway
         |
Shopify paid order webhook -> backend
         |
Backend verifies order + school + rates -> donation ledger
         |
Backend Connect transfer + Stripe payout workflows
```

The Shopify theme is NOT responsible for Stripe Connect operations. Its job is to collect school selection, show accurate disclosures, preserve cart state, and hand off to ordinary checkout.

## 7. File/change map (illustrative; adapt to actual theme)

```text
shopify-theme/
  sections/main-product.liquid       # insert school selector block
  sections/main-cart-footer.liquid   # single selector + totals near checkout
  snippets/school-selector.liquid    # reusable markup
  assets/school-selector.js          # fetch schools, save/read cart attributes
  assets/school-selector.css         # accessible responsive design
  config/settings_schema.json        # optional theme settings/fallback copy
```

If using Theme App Extensions, the resulting structure and deployment differ; inspect the theme architecture first and use extension blocks where practical to reduce upgrade conflicts.

## 8. Testing matrix

| Case | Expected behavior |
|---|---|
| Single product, no school | Selector prompts; checkout not enabled via theme |
| Single product, selected school | Cart prefilled, disclosure shown |
| Two different products | One school chosen for whole order |
| 3 units of one product | One school; eligible subtotal reflects quantity |
| Select school A on product 1 then school B on product 2 | B is cart-wide choice; visible confirmation |
| Increase/decrease quantity | Recalculate estimated amount; keep school |
| Delete product / add another | Keep school while cart nonempty |
| Empty cart | Clear selection according to design; no stale next-order school |
| School suspended while cart open | Require new approved choice before checkout where supported |
| Donation rate changes | Refresh disclosure; final rate policy stays consistent |
| Discount or product exclusions | Expected eligible subtotal, no shipping/tax included |
| Backend API unavailable | Show retry/error; no silent default school |
| Express Buy Now/Shop Pay/Apple Pay | Test bypass and Function/app validation thoroughly |
| Webhook after successful payment | School ID in order attributes; backend allocates once |
| Refund, cancel, chargeback | Backend adjusts allocation, no extra storefront work |
| Mobile + cart drawer | One selector remains visible and functional |

## 9. Implementation phases

- **Phase 0:** Inspect existing Shopify theme, Grow plan capabilities, express methods, installed validation apps, Stripe gateway arrangement, and pricing/donation policy.
- **Phase 1:** Backend public APIs ready; build reusable product-page selector and dynamic percentage disclosure.
- **Phase 2:** Build cart-wide selector/confirmation, estimate, attribute persistence, cart drawer support and UI validation.
- **Phase 3:** Establish an enforceable Grow-compatible validation strategy, including accelerated checkout routes.
- **Phase 4:** Verify paid order attributes/webhooks with backend; test rate updates, multiple products, discounts, mobile and errors.
- **Phase 5:** Release with observability and regression tests against future theme updates.

## 10. Acceptance criteria

1. Shopify Grow supports selector on product + cart pages without Plus.
2. Public API only returns approved schools and current donation configuration.
3. Single school ID persists across multiple products and checkout.
4. Changing school anywhere updates the single cart-wide selection.
5. Customer sees dynamic percentage and accurate **estimated** allocation text.
6. Stripe gateway remains Shopify checkout processor.
7. Order webhook contains the selected school ID; backend records donation once.
8. Checkout cannot be completed without a valid school **only if** a compatible server-side validation solution has passed direct and express checkout tests; otherwise this is an unresolved release blocker.
9. School list errors, stale states, cart drawers, mobile, discount changes and empty carts work reliably.

## 11. Open decisions

- Is school selection required on product page, or only ultimately required for checkout? Recommended: product convenience; cart confirmation.
- Donation percentage global vs category/product-specific?
- Included in price (assumed) or extra charge?
- Which public validation app (or other supported solution) can enforce the rule on Grow?
- How to handle suspended schools in carts already in progress?
- If Shopify Plus later purchased, is a dropdown inside checkout essential?

## 12. Official references

- Shopify Ajax Cart API: https://shopify.dev/docs/api/ajax/reference/cart
- Shopify checkout UI extensions: https://shopify.dev/docs/api/checkout-ui-extensions/latest
- Shopify Cart and Checkout Validation Function: https://shopify.dev/docs/api/functions/latest/cart-and-checkout-validation
- Shopify Functions plan availability: https://shopify.dev/docs/apps/build/functions/test-debug-functions
- Shopify Functions network access: https://shopify.dev/docs/apps/build/functions/network-access
- Shopify webhooks: https://shopify.dev/docs/api/webhooks/latest
