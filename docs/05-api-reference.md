# 05 — API Reference

Base URL: `/api/v1`. JSON in and out. Auth: `Authorization: Bearer <token>`. Money fields are `*_minor` (integer cents) plus `currency`. Timestamps are ISO 8601 UTC.

Legend: 🔓 public · 🔑 any authenticated user · 🏫 school (approved unless noted) · 🛡 admin.

Error shape: `{ "message", "code", "errors"? }`. Common codes: `validation_failed` (422), `unauthenticated` (401), `forbidden` (403), `school_not_approved` (403), `not_found` (404), `conflict` (409), `rate_limited` (429).

## 1. Auth

| | Method | Route | Body | Response |
|---|---|---|---|---|
| 🔓 | POST | `/auth/register` | `email, password, password_confirmation, accepted_terms` | 201 `{ email, otp_expires_in }`. Sends OTP |
| 🔓 | POST | `/auth/verify-otp` | `email, otp` | 200 `{ token, user }` |
| 🔓 | POST | `/auth/resend-otp` | `email, purpose` | 204. 60 s cooldown (429 otherwise) |
| 🔓 | POST | `/auth/login` | `email, password, remember` | 200 `{ token, user, school? }`. 403 if deactivated |
| 🔓 | POST | `/auth/social/{google\|apple}` | `id_token` | Optional (D6) |
| 🔓 | POST | `/auth/forgot-password` | `email` | 204 always (no account enumeration). Sends OTP |
| 🔓 | POST | `/auth/verify-reset-otp` | `email, otp` | 200 `{ reset_token }` |
| 🔓 | POST | `/auth/reset-password` | `reset_token, password, password_confirmation` | 204 |
| 🔑 | POST | `/auth/logout` | | 204 |
| 🔑 | GET | `/auth/me` | | `{ user, school: { id, name, status, rejection_reason } }` |
| 🔑 | PUT | `/me/password` | `current_password, password, password_confirmation` | 204. Revokes other tokens |
| 🔑 | PUT | `/me/settings` | `notifications_enabled` | 200 |

## 2. Public content and storefront

| | Method | Route | Notes |
|---|---|---|---|
| 🔓 | GET | `/content/{slug}` | `about-us`, `terms`, `privacy`. `{ title, body, updated_at }` |
| 🔓 | GET | `/storefront/schools?search=&page=&per_page=` | Approved schools only. Returns `{ id (ulid), name, city, state, avatar_url }`. Cached 60 s. Throttled |
| 🔓 | GET | `/storefront/schools/{id}` | 200 if approved, 404 otherwise. Lets the theme re-validate a stored cart school |
| 🔓 | GET | `/storefront/donation-settings` | See below. Cached 60 s |

```json
{
  "donation_enabled": true,
  "percentage_basis_points": 3000,
  "calculation_basis": "discounted_eligible_merchandise_subtotal_excluding_tax_shipping",
  "included_in_price": true,
  "settings_version": "donation-v12",
  "disclaimer": "30% of your eligible merchandise purchase supports your selected school at no additional cost."
}
```

CORS for these routes: the store domain(s) only. No credentials.

## 3. School portal (🏫)

### Profile

| Method | Route | Body / notes |
|---|---|---|
| POST | `/school/profile` | Create Profile. Allowed once, after OTP verify. `name, phone, address, city, state, avatar?` (multipart). Sets status `pending`. Not subject to the approval check |
| GET | `/school/profile` | |
| PUT | `/school/profile` | Edit Personal Info: `name, email?, phone, address, city, state`. Changing email re-triggers OTP. Name change on an approved school notifies admin |
| POST | `/school/profile/avatar` | multipart `avatar` (image ≤ 2 MB) |
| DELETE | `/school/account` | Delete Account. 409 `balance_outstanding` if pending, wallet or an in-flight withdrawal is non-zero. Soft delete, token revoked |

### Dashboard and funds (needs approved)

| Method | Route | Response |
|---|---|---|
| GET | `/school/dashboard` | `{ total_earning_minor, pending_minor, completed_minor (transferred to Connect), total_orders, currency }` |
| GET | `/school/dashboard/revenue?month=2026-10` | `{ points: [{ day: 1, amount_minor }, …] }` |
| GET | `/school/donations?status=all\|pending\|completed&search=&page=` | Rows: `{ ref: "TXN-584721", order_amount_minor, donation_minor, status, placed_at }`. Search is by ref. Customer names stay hidden from schools (privacy) |
| GET | `/school/donations/export` | CSV stream, same filters |

> The Figma search placeholder says "Search by name & email" on Fund collection. Customer PII shouldn't reach schools, so the API searches by reference and date only. Flag this to design.

### Payout account, wallet and withdrawals (not in Figma yet, design needed)

| Method | Route | Notes |
|---|---|---|
| POST | `/school/stripe/onboarding-link` | Approved schools only. Returns a short-lived Stripe-hosted URL |
| GET | `/school/stripe/status` | `{ status, transfers_enabled, payouts_enabled, requirements_due[], bank: { name, last4 } }` |
| GET | `/school/wallet` | `{ total_earning_minor, pending_minor, payable_minor, in_wallet_minor (fresh Stripe lookup), withdrawal_in_flight_minor, withdrawn_minor, currency, can_withdraw, blocked_reason? }` |
| POST | `/school/withdrawals` | `{ amount_minor \| withdraw_all: true, currency, client_request_id }`. 201 with status `processing` |
| GET | `/school/withdrawals?page=` | `{ ref, amount_minor, status, requested_at, paid_at, failed_reason? }` |
| GET | `/school/transfers?page=` | Transfers that moved donations into the wallet |

`POST /school/withdrawals` rules (all server-side, school resolved from the token):

- School `approved`, Stripe account `payouts_enabled`, no other withdrawal `requested`/`processing` (409 `withdrawal_in_progress`).
- `0 < amount ≤ in_wallet` using a fresh Stripe balance lookup by currency (422 `exceeds_balance`) and above the payout minimum.
- `client_request_id` is the idempotency key, so a double-click returns the same withdrawal.
- Reserve in the DB (`withdrawal_debit`), call the Stripe Payouts API in the connected-account context, store the payout id, return `processing`.
- Marked `paid` only on `payout.paid`. `payout.failed` writes `withdrawal_reversal` and notifies the school.
- If the connected account's payouts can't be API-created, return `can_withdraw=false` with a reason. Don't promise instant withdrawals (see 06 §3.1).

### Support

| Method | Route | Notes |
|---|---|---|
| GET | `/school/tickets?status=&search=&page=` | `{ id, subject, message, attachments[], status, created_at }` |
| POST | `/school/tickets` | multipart `subject, message, attachments[]` (≤ 3 images, ≤ 5 MB each) |
| GET | `/school/tickets/{id}` | Includes the admin response if any. Signed attachment URLs |

## 4. Admin portal (🛡)

### Dashboard

| Method | Route | Response |
|---|---|---|
| GET | `/admin/dashboard` | `{ total_orders, fundraising_total_minor, total_amount_minor, total_schools, recent_orders[10] }` |
| GET | `/admin/dashboard/revenue?month=` | daily points |

### Orders

| Method | Route | Notes |
|---|---|---|
| GET | `/admin/orders?search=&status=&date_from=&date_to=&ingestion=quarantined&page=` | Row: `{ id, order_ref: "ORD-1001", customer_name, address, placed_at, school: {id,name}, amount_minor, fundraising_minor, status }` |
| GET | `/admin/orders/{id}` | Items, subtotal, shipping, total, school card, status timeline with timestamps |
| POST | `/admin/orders/{id}/assign-school` | Resolve a quarantined order: `{ school_id }`. Creates the allocation. Audited |

### Schools

| Method | Route | Notes |
|---|---|---|
| GET | `/admin/schools?status=all\|pending\|approved\|rejected&search=&page=` | |
| GET | `/admin/schools/{id}` | Profile. For approved: earning cards |
| GET | `/admin/schools/{id}/donations?page=` | Table at the bottom of the approved details screen |
| POST | `/admin/schools/{id}/approve` | Only from `pending`. Sends mail and notification |
| POST | `/admin/schools/{id}/reject` | `{ reason }` (reason required, shown to the school) |
| POST | `/admin/schools/{id}/deactivate` | Confirm popup is client-side. Removes from public list |
| POST | `/admin/schools/{id}/reactivate` | Not in Figma. Needed so deactivation is reversible |

### Payout Management (transfers and withdrawals log, plus override)

Schools withdraw on their own. Donations move to each school's Connect wallet automatically after the hold period. The admin screens are a monitoring log plus an override.

| Method | Route | Notes |
|---|---|---|
| GET | `/admin/payouts?type=transfer\|withdrawal&search=&page=` | Unified log. `{ ref: "PAY-1001" or "WDR-1001", type, school, date, total_revenue_minor, amount_minor, method: "stripe_connect", status }` |
| GET | `/admin/payouts/balance?school_id=` | `{ pending_minor, payable_minor, in_wallet_minor, currency }`. Feeds the "Pending Amount" label |
| POST | `/admin/payouts` | **Override: release transfer now.** `{ school_id, amount_minor, client_request_id }`. Transfers payable (and optionally in-hold) donations to the school's Connect account ahead of schedule. `initiated_by=admin`, reason required, audited |
| GET | `/admin/payouts/export` | CSV |
| GET | `/admin/settlements` | Totals: allocated vs transferred vs withdrawn |
| GET | `/admin/reconciliation` | Latest `reconciliation_runs` with mismatches |

`POST /admin/payouts` rules: the school must be `approved` with transfers enabled on its Stripe account. `0 < amount ≤ pending` (422 `exceeds_balance`). Idempotent by `client_request_id`. It runs in one DB transaction with a row lock on the school, writes `transfer_to_connect` entries, and records FIFO `transfer_allocations`. Stripe is called through the queued `ExecuteTransfer` job. Status stays `pending` until Stripe confirms. The admin cannot withdraw to the bank on behalf of a school.

### Fundraising rate

| Method | Route | Notes |
|---|---|---|
| GET | `/admin/fundraising-rates` | History: `{ business_percent, school_percent, effective_at, status }` |
| POST | `/admin/fundraising-rates` | `{ business_percent, school_percent }`, must total 100. Closes the active row, creates a new one, busts the storefront cache |

### Support

| Method | Route | Notes |
|---|---|---|
| GET | `/admin/tickets?status=&search=&page=` | `{ id, name, email, user_type, subject, message, created_at, status }` |
| GET | `/admin/tickets/{id}` | Full ticket, attachments, requester info |
| POST | `/admin/tickets/{id}/reply` | `{ body }`. Pending → Opened. Notifies the requester |
| POST | `/admin/tickets/{id}/close` | Opened or Pending → Closed. Notifies the requester |

### Content

| Method | Route | Notes |
|---|---|---|
| PUT | `/admin/content/{slug}` | `{ body }`. HTML sanitised server-side |

## 5. Notifications (🔑, both portals)

| Method | Route | Notes |
|---|---|---|
| GET | `/notifications?page=` | `{ id, title, body, created_at, read_at }`. Meta includes `unread_count` for the bell |
| POST | `/notifications/read-all` | "Mark all as Read" |
| POST | `/notifications/{id}/read` | |
| DELETE | `/notifications/{id}` | The × button (sets `dismissed_at`) |

Notification types: `school.registered` (admin), `school.approved` / `school.rejected` (school), `order.allocated` (school), `order.quarantined` (admin), `transfer.completed` (school), `withdrawal.paid` / `withdrawal.failed` (school, admin on failure), `stripe.requirements_due` (school), `ticket.created` (admin), `ticket.replied` / `ticket.closed` (requester).

Delivery: database always. Mail when `users.notifications_enabled`. Real-time push (Reverb/Pusher) is a later add-on. The bell polls every 60 s in v1.

## 6. Webhooks (signature-verified, queue-only)

| Method | Route | Verification | Handled events |
|---|---|---|---|
| POST | `/webhooks/shopify` | `X-Shopify-Hmac-Sha256` over the raw body | `orders/create`, `orders/paid`, `orders/updated`, `orders/cancelled`, `refunds/create`, `fulfillments/create`, `fulfillments/update`, `fulfillment_events/create` |
| POST | `/webhooks/stripe/platform` | `Stripe-Signature`, platform secret | `transfer.*`, `charge.dispute.*`, `charge.refunded` |
| POST | `/webhooks/stripe/connect` | `Stripe-Signature`, Connect secret | `account.updated`, `payout.paid`, `payout.failed` |

Each handler: verify, insert into `webhook_inbox` (dedupe), return 200 immediately, process in a queued job.

## 7. Rate limits and pagination summary

| Group | Limit |
|---|---|
| `/auth/*` | 5 per minute per IP+email |
| `/storefront/*` | 60 per minute per IP |
| Authenticated | 120 per minute per user |
| Exports | 6 per minute per user |
| `POST /school/withdrawals` | 5 per minute per school |

List endpoints default to `per_page=15`, max 100.
