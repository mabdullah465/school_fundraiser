# 01 — Overview & Decisions

## 1. Product in brief

**Actors**

| Actor | Where they work | What they do |
|---|---|---|
| Shopper | Shopify storefront | Picks one school per order, pays through normal Shopify checkout |
| School | React school portal | Registers, sees fund collection, manages profile, files support tickets |
| Admin | React admin portal | Approves schools, sees orders, sets the fundraising %, pays out schools, answers support |
| System | Laravel backend | Syncs schools and settings to Shopify, ingests orders, keeps the ledger, runs transfers |

**Core rules (from the plan files)**

- Exactly **one school per order**, however many products.
- Shopify keeps products, checkout and the Stripe gateway. We do not replace checkout.
- Money is stored as **integer minor units**. Never floats.
- School balances are a **liability ledger**, not proof that cash sits in Stripe.
- The browser never decides money. Percentages and amounts shown in Shopify are estimates. Laravel recalculates from the paid order.

## 2. Where the Figma and the plan files disagree

The plan files were written before the designs existed. These conflicts need a decision before we build the affected modules. Each has a recommended default so work can start.

| # | Topic | Plan files say | Figma shows | Recommended default |
|---|---|---|---|---|
| D1 | **Who triggers a payout** | School presses "withdraw" (`POST /api/school/withdrawals`, wallet) | Admin-only: *Payout Management → Payout School → pick school, enter amount, Transfer*. The school portal has **no** withdraw button | **FINAL: plan files win. Schools withdraw themselves.** Donations move to the school's Stripe Connect balance automatically (Policy A, see 06). The school withdraws that balance to its bank. Figma gaps to design: a **Wallet / Withdraw** screen and a **Payout account** card in the school portal. The admin *Payout School* screen is repurposed as an override ("release transfer now"). Admin Payout Management becomes a read-only transfers and withdrawals log |
| D2 | **Fundraising rate** | One donation % (e.g. 10%) of discounted merchandise, administered in the backend | **Business % + School %** (70/30), with a history table (Active/Closed). Sample rows: $600 order → $180 (30%) | **FINAL: admin sets the rate** in Settings → Fundraising Percentage. School % **is** the donation rate. Business % = 100 − School %, validated to sum to 100. Store `school_bps` only and show business % derived |
| D3 | **Payout method** | Stripe Connect transfer then Stripe payout | Payout list column "Payout Method: Bank Transfer". No bank or Connect screen exists | **FINAL: Stripe Connect.** No manual driver. School onboards through Stripe-hosted onboarding after approval. The Phase 0 Stripe feasibility check (06 §3.1) is a go/no-go gate on the whole payout design, not a switch to a fallback |
| D4 | **Order status** | Only paid/refunded/cancelled matter | Admin Orders show *Placed → In Process → Shipped → Received* | Mirror Shopify fulfilment: `Placed` = paid, `In Process` = unfulfilled or partial, `Shipped` = fulfilled, `Received` = delivered (needs Shopify fulfilment events) |
| D5 | **"Pending / Completed"** on Fund Collection | Rich state set (`confirmed`, `eligible_for_transfer`, `transferred`…) | Two badges | Show **Pending** until fully covered by a payout, then **Completed**. Keep the rich states internally |
| D6 | **Login options** | None | Login variant 2 has Google and Apple buttons | Treat as optional. Build email + password + OTP first. Add Socialite later if the client confirms |
| D7 | **"User" tickets** | Not mentioned | Admin Support lists tickets from "User" and "School" | Customers have no portal in Figma. Confirm how a "User" files a ticket (storefront form? app?). Until then, build School tickets and keep `user_type` open |
| D8 | **Rejected / deactivated schools** | Approve, reject, suspend | Approve/Reject on pending. Payout/Deactivate on approved. Statuses shown: Pending/Approved/Rejected | Add `deactivated` (= suspended in the plan). A deactivated school disappears from the public list but keeps its ledger and payouts |
| D9 | **Quarantined orders** | Orders without a valid school are quarantined and the admin is alerted | No screen | Add a "Needs attention" filter to admin Orders and a notification. Small addition to the design |
| D10 | **Delete account (school)** | Not covered | Delete Account popup in school Settings | Soft delete. Block while pending balance > 0 or a payout is in flight. Keep ledger rows for audit |

## 3. Business decisions still open

D1–D3 are now final (plan files win: schools withdraw, admin sets the rate, Stripe Connect). The rest come from the plan files' open-decision lists plus what the designs raise.

1. **Included in price vs added on top?** Default is included in price. "On top" needs a Shopify priced line item and is a separate project.
2. **Donation base.** Default: discounted eligible merchandise subtotal, excluding tax and shipping. The Figma sample ($600 → $180) doesn't say whether $600 is the subtotal or the order total. Confirm.
3. **Rate lock moment.** Default: the rate active when the order is **paid** (we read it server-side). Store the rate version on every allocation.
4. **Rounding.** Default: round half up to the cent, per order, not per line.
5. **Refunds after payout.** Default: record a negative ledger entry. If the balance goes negative, offset against future donations. Admin sees it as a debt on the school page. No automatic clawback in v1.
6. **Hold period.** Do donations become payable immediately on payment, or after N days (refund window)? Default: 14-day hold before they count towards "payable" balance. Admin screen shows the payable amount as "Pending Amount".
7. **Countries and currencies.** Default: USD only for v1 (the Figma uses $ and US addresses). Store currency on every row anyway.
8. **Mandatory selection on Shopify Grow.** The theme can block the checkout button, but only a validation app or Plus can truly enforce it, including Shop Pay and Buy Now. Until a compatible validation app passes testing, treat strict enforcement as unresolved. The backend quarantines any paid order without a valid school, so no donation is silently lost.
9. **Charity or legal status.** Are schools registered charities? Does the jurisdiction restrict how funds are held and moved? This gates the Stripe Connect design.

## 4. Scope

**In scope for v1**

- Laravel API for the admin portal, school portal, Shopify theme (public), and webhooks.
- All screens in the Figma export (see 03).
- Shopify order ingestion, donation ledger, automatic transfers to school Connect accounts, school-initiated withdrawals, admin transfer override, notifications, support tickets, CMS pages (About, Terms, Privacy).
- Reports: CSV downloads for fund collection and for transfers/withdrawals.

**Out of v1**

- Replacing Shopify checkout, a school per line item, an additional-charge donation model.
- Policy B (transfer only when the school requests withdrawal). We use Policy A.
- Customer-facing portal or app.
- Multi-currency payouts.

## 5. Workstreams

| WS | Owner area | Depends on |
|---|---|---|
| A. Laravel backend (this repo) | API, ledger, integrations | Decisions D1–D3 |
| B. React portals (admin + school) | UI from Figma | A's API contract in 05 |
| C. Shopify theme | Selector, disclosure, cart attribute | A's public API (`/storefront/*`) |
| D. Stripe/Shopify feasibility | Phase 0 spikes | Store and Stripe access |
