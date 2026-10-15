# Backend Implementation Plan — Shopify School Donation Platform

**Version:** 1.0  
**Target storefront:** Shopify Grow  
**Components:** Existing custom backend, React admin portal, React school portal, Stripe gateway account, Stripe Connect  
**Primary business rule:** One selected school per Shopify order, regardless of number of products.

## 1. Purpose and scope

Build the backend services that synchronize approved schools and donation settings to Shopify, receive paid orders, allocate donations to one school per order, manage Stripe Connect school onboarding, transfer eligible donations, and support school-initiated bank withdrawals. Shopify remains responsible for its product catalogue, order creation, checkout, and Stripe gateway checkout integration.

**Not in initial scope:** Splitting a Shopify checkout directly during payment, separate school selections per line item, or replacing Shopify checkout. These are potential future changes.

## 2. Assumptions and decisions

- Customers must choose exactly **one approved school per order**. A product-page selection is cart-wide; changing it later replaces the prior selection.
- Donation percentage is administered in the custom backend and exposed to Shopify through a read-only public API.
- **Working example:** 10% of discounted eligible merchandise subtotal, excluding shipping and taxes, **included in the selling price**. Confirm this business rule before launch. If donations are charged on top, Shopify needs a priced cart-line solution and additional validation.
- Donation amounts are calculated in integer minor currency units using a documented rounding rule (e.g., round half up to the nearest cent).
- School balances in the database are **allocations/liabilities**, not proof that funds are sitting in Stripe or eligible for payout.
- Payment transfers require verification that the actual Shopify-created Stripe charges belong to and are accessible by the Connect platform account; use of the same Stripe API keys is a necessary check, not proof of eligibility.
- The platform must obtain appropriate approval for the jurisdiction, Connect account types, charitable-funds model, connected-account capabilities, and settlement schedule.

## 3. Architecture and ownership

```text
React Admin ----> Custom Backend <---- React School Portal
                       ^  |
                       |  | approved schools + donation settings API
          Shopify webhooks | 
                       |  v
                Shopify Grow storefront
                     + checkout
                       |
              Existing Stripe gateway
                       |
             Main Stripe account
                       |
        Backend-managed Connect transfers
                       |
             School connected account
                       |
              Connected-account payout
                       |
                 School bank account
```

**Sources of truth:** Shopify for products/orders/payment state as represented in Shopify; Stripe for charge, transfer, and payout state; backend for school approval, configured rates, order donation allocations and the internal ledger.

## 4. Modules

### 4.1 School registration and approval
- School completes registration in React: name, contact, location, status, optional accreditation/documentation.
- Backend creates school with `pending_review` status.
- Admin can approve, reject or suspend.
- Only `approved` schools appear in public search/list APIs.
- Suspended schools are excluded from new selections; prior donation records remain intact.
- Connect onboarding is permitted only after admin approval.

### 4.2 Public storefront APIs

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/storefront/schools?search=&page=` | Paginated approved school IDs, names, location/short description; no sensitive data |
| GET | `/api/storefront/donation-settings` | Enabled status, rate, version, display rules, disclaimer, calculation basis |
| GET | `/api/storefront/schools/{id}` | Optional verify school still approved |

Use TLS, CORS restricted to store origins where practical, rate limiting, response caching and an app proxy if useful. Never expose Stripe secrets, bank details, or privileged admin APIs. Search must handle hundreds or thousands of schools.

Example settings response:

```json
{
  "donation_enabled": true,
  "percentage_basis_points": 1000,
  "calculation_basis": "discounted_eligible_merchandise_subtotal_excluding_tax_shipping",
  "included_in_price": true,
  "settings_version": "donation-v12",
  "disclaimer": "10% of your eligible merchandise purchase supports your selected school at no additional cost."
}
```

### 4.3 Admin donation configuration
- Configure percentage, inclusion/add-on mode (initially locked to included), effective date, calculation base, product exclusions, minimum/maximum if applicable, and disclaimer text.
- Maintain immutable rate history (`donation_rate_versions`) and audit log.
- Avoid mismatched displayed/recorded rates: record order-time rate and version; determine the authoritative rate at order placement according to the approved policy.
- Synchronize any approval/validation data needed by Shopify-supported enforcement systems. Backend API cannot be called live by a normal Shopify validation Function on Grow.

### 4.4 Shopify order ingestion
- Register Shopify app/webhook subscriptions for `orders/create`, `orders/paid`, `orders/cancelled`, `refunds/create` (and relevant update/payment reconciliation events according to the Shopify API version).
- Verify Shopify HMAC using raw request body and app secret; enqueue immediately; respond quickly; process asynchronously.
- Persist webhook delivery/event identity; retries and duplicates must never create duplicate allocations.
- Re-fetch via Shopify Admin API when payment state, line-item discounts, school attributes or refund breakdown requires clarification.
- Parse the cart-wide school selection from order note/cart attributes; reject/quarantine paid orders that lack a recognized approved school, and alert admin rather than guessing.
- Calculate donation from **actual paid order financial values** using a consistent rule for discounts, quantity, eligible products, tax, shipping, gift cards, currency and refunds.
- A new order is **not** proof of paid status; allocate confirmed donations only after verified paid order/payment state.

### 4.5 Stripe payment reconciliation
- Configure Stripe platform webhooks as needed (e.g., PaymentIntent/charge/dispute, transfer events) **only where the Shopify payment gateway creates accessible objects in your Stripe account**.
- Verify Stripe signature using the raw body and endpoint signing secret.
- Resolve actual Stripe charge ↔ Shopify order mapping through provider metadata, payment references or a verified reconciliation process; never rely solely on amount or email.
- Prove with a sandbox/live-small-value purchase that the Stripe charge is retrievable through the intended Connect platform credentials and suitable for transfers.
- Keep stripe charge IDs, payment intent IDs, balance transaction IDs, transfer IDs and all currencies.

### 4.6 Donation ledger and states
- One order can have many line items but exactly one school allocation in the initial design.
- Store `pending_payment`, `confirmed`, `eligible_for_transfer`, `transferred`, `partially_refunded`, `reversed`, `cancelled`, `disputed` as appropriate. Separate **donation accounting** from **transfer and payout accounting**.
- Use append-only ledger entries for credits, adjustments, transfer reservations, releases and reversals. Do not overwrite historical values after a rate change.
- School dashboard totals: lifetime attributed, pending, confirmed, eligible for transfer, moved to Connect, available Stripe balance (fresh API lookup), payout pending and paid.
- Refunds/disputes after transfer may require transfer reversals or an offset against future allocations, subject to Stripe and legal rules. Never subtract a bank-paid amount without accounting for a resulting debt or recovery process.

### 4.7 Stripe Connect onboarding
- Platform enables Connect and selects account configuration/capabilities approved for the schools' countries.
- After school approval, create or link a school connected account as supported; launch Stripe-hosted onboarding with a short-lived onboarding link.
- Save `stripe_account_id`, verification/onboarding status, relevant capabilities, transfers/payout eligibility, requirements due and display-safe external account metadata only.
- Subscribe to `account.updated` or the appropriate connected account event model. Restrict sensitive bank information to Stripe-hosted UI.
- Account dashboard may offer onboarding refresh and support actions.

### 4.8 Donation transfer policy
Choose and document ONE of:

**Policy A — transfer on confirmation/after a hold period:** Backend transfers allocated donation to school Connect balance once eligible. School withdraw button subsequently triggers payout from available connected balance.

**Policy B — transfer when school requests withdrawal:** Backend reserves internal eligible balance, initiates transfer to school Connect account, then initiates bank payout only when the connected balance becomes available. This may be multi-stage and delayed.

Initial recommendation: **Policy A** simplifies user-facing withdrawal semantics, if the chosen Connect configuration and compliance permit it.

In either case:
- Confirm platform balance, charge ownership, currency and school eligibility.
- Consider `source_transaction` only if Stripe charge belongs to platform and transfer is eligible; otherwise use a separately permitted transfer mechanism and available balance.
- Track platform payout schedule; funds automatically paid to the merchant's bank might no longer be available for Connect transfer.
- Use deterministic idempotency keys per donation transfer and atomic DB reservations.
- Stripe processing fees, Connect fees, tax and refunds require documented allocation rules.

### 4.9 School withdrawal / connected-account payouts
- Endpoint accepts requested amount or **withdraw all**; authenticate school and resolve account ID server-side.
- Verify transfer state, payout capability, connected-account available balance by currency, payout minimums and any withdrawal restrictions.
- Manual/instant payouts require a supported Connect account and payout schedule/control; not all account configurations permit API-created payouts.
- Reserve withdrawal in DB, call Stripe Payouts API **in connected-account context** with idempotency key; store payout ID and state.
- Do not mark paid on request success; mark paid when confirmed by payout event (`payout.paid`), and handle `payout.failed`/cancellations.
- Connected-account events require Connect webhook destination and `event.account` based routing where provided.
- If payouts cannot be API-controlled, display **Request Withdrawal** or let Stripe-managed schedule control bank payouts rather than falsely promising instant withdrawals.

## 5. Proposed database tables

| Table | Key fields |
|---|---|
| `schools` | id, name, approval_status, stripe_account_id, created_at |
| `school_audit_events` | id, school_id, action, actor_id, metadata, occurred_at |
| `donation_rate_versions` | id, rate_bps, calculation_basis, included_in_price, effective_at, version |
| `shopify_orders` | id, shopify_order_id, financial_status, currency, subtotal_minor, selected_school_id, rate_version_id, processed_at |
| `shopify_order_items` | order_id, line_item_id, product_id, quantity, net_eligible_minor |
| `donation_allocations` | id, shopify_order_id, school_id, rate_bps, amount_minor, currency, status |
| `donation_ledger_entries` | id, allocation_id, school_id, type, amount_minor, reference_type, reference_id, created_at |
| `stripe_payments` | shopify_order_id, payment_intent_id, charge_id, payment_status, currency, amount_minor |
| `stripe_transfers` | id, allocation_id, school_id, transfer_id, amount_minor, currency, status, idempotency_key |
| `school_withdrawals` | id, school_id, amount_minor, currency, status, payout_id, idempotency_key, created_at |
| `webhook_inbox` | provider, event_id, topic, payload_hash, processed_at, error |
| `reconciliation_runs` | id, provider, started_at, completed_at, result |

Use unique constraints on `(provider, event_id)`, Shopify order ID, and deterministic transfer/withdrawal request identity. Protect ledger updates with transactions/row locks.

## 6. API endpoints (authenticated unless public)

### Admin
- `GET /api/admin/schools?status=`
- `POST /api/admin/schools/{id}/approve`
- `POST /api/admin/schools/{id}/reject`
- `POST /api/admin/schools/{id}/suspend`
- `GET /api/admin/donations?school_id=&date_from=&date_to=`
- `GET /api/admin/settlements`
- `GET /api/admin/reconciliation`
- `PUT /api/admin/donation-settings`

### School
- `POST /api/school/stripe/onboarding-link`
- `GET /api/school/stripe/status`
- `GET /api/school/donations`
- `GET /api/school/wallet`
- `POST /api/school/withdrawals` (`amount_minor`, `currency`, client request id)
- `GET /api/school/withdrawals`

### Integrations
- `POST /webhooks/shopify`
- `POST /webhooks/stripe/platform`
- `POST /webhooks/stripe/connect`

## 7. Processing pseudocode

```text
Shopify orders/paid webhook
  -> verify signature -> deduplicate -> enqueue
  -> fetch/normalize order as needed
  -> find cart attribute school_id; validate school and policy
  -> calculate eligible discounted merchandise subtotal
  -> resolve rate version and donation amount
  -> in DB transaction: upsert order + one donation allocation + ledger credit
  -> schedule reconciliation and, when eligible, Connect transfer

School withdrawal request
  -> authorize school and connected account
  -> validate amount, currency, bank/payout eligibility and available balance
  -> atomically reserve request with idempotency key
  -> create connected account payout only if supported/funded
  -> store payout ID and pending state
  -> Stripe webhook: payout.paid / payout.failed -> finalize / retry policy
```

## 8. Controls and failure scenarios

- Duplicate/out-of-order Shopify and Stripe webhooks; replay protection.
- Partial refund, full refund, chargeback, cancellation, currency mismatch, fee mismatch.
- Selected school suspended between selection and purchase; clear policy for handling and administrative resolution.
- API unavailable at storefront selection time; Shopify must not silently proceed with an invalid school.
- Stripe account not Connect-capable, insufficient platform balance, unavailable connected bank, verification requirements outstanding.
- Transfer succeeds but payout fails; record distinct statuses and do not issue duplicate transfer.
- Multiple browser clicks, concurrent school withdrawals, and delayed Stripe events.
- Signed payment webhooks; authorization/RBAC; encrypted API secrets; structured error logging; daily reconciliation; audit trail.

## 9. Acceptance tests

1. Approving/suspending schools changes public list.
2. One product and ten products both generate one school allocation per order.
3. Changing school on another product page affects entire cart.
4. 10% displayed in storefront matches backend order allocation at approved calculation base.
5. Missing/invalid school order is quarantined and never silently credited.
6. Repeat `orders/paid` webhook does not duplicate donations.
7. Refund/dispute correctly adjusts ledger and transfer/recovery status.
8. Test Shopify-generated Stripe charge can be retrieved/associated with an order and used for transfer **if eligible**.
9. Connected account onboarding/verification updates portal correctly.
10. Payout pending/paid/failed states remain accurate, including duplicate withdrawal click.
11. Admin can reconcile sum of allocations vs transfers vs payouts and download report.

## 10. Delivery sequence

- **Phase 0 — feasibility:** Verify Shopify payment object ownership, Stripe Connect eligibility, payout control, currencies, regulatory treatment, and donation base.
- **Phase 1 — foundation:** Tables, school approval, public API, donation settings history, auth.
- **Phase 2 — Shopify integration:** HMAC webhook ingestion, paid order allocation, refunds, reconciliation.
- **Phase 3 — Connect:** School onboarding, transfer engine, connected account status.
- **Phase 4 — withdrawals:** Wallet, payout requests, status webhooks, admin settlement views.
- **Phase 5 — QA/release:** End-to-end test purchases, multi-item carts, missed webhooks, refund/withdrawal failures, production monitoring.

## 11. Open decisions

1. Is donation **included** in product price or added on top?
2. Rate fixed globally or by product/category; rate-lock moment and rounding?
3. One school per order (assumed) confirmed?
4. Transfer instantly, on hold expiration, or upon withdrawal?
5. Which countries/currencies are platform and schools in? Are schools registered charities?
6. Which Stripe Connect account configuration permits school-requested bank payouts?
7. Which Shopify payment transactions are actually retrievable using the platform Stripe API keys?
8. How to handle refunds after school bank payout?

## 12. Official references

- Shopify webhooks: https://shopify.dev/docs/api/webhooks/latest
- Shopify cart API: https://shopify.dev/docs/api/ajax/reference/cart
- Stripe separate charges and transfers: https://docs.stripe.com/connect/separate-charges-and-transfers
- Stripe Connect payouts: https://docs.stripe.com/connect/payouts
- Stripe Connect webhooks: https://docs.stripe.com/connect/webhooks
