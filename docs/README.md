# School Fundraiser Store — Build Docs

Backend: **Laravel**. Storefront: **Shopify Grow** (+ Stripe gateway). Portals: **React** admin portal and React school portal.

These docs turn two plan files and the Figma export into a build plan.

| Source | What it covers |
|---|---|
| [backend_school_donation_plan.md](../backend_school_donation_plan.md) | Backend logic: donations, ledger, Stripe Connect, webhooks |
| [shopify_grow_school_selector_plan.md](../shopify_grow_school_selector_plan.md) | Shopify theme: school selector, disclosure, enforcement caveats |
| Figma export (`C:\Users\Abdullah\Downloads\School Fundraiser Store`, 64 PNGs) | Admin portal + School portal screens |

## Reading order

1. [01 — Overview & decisions](01-overview-and-decisions.md): what we're building, where the Figma and the plans disagree, decisions we need.
2. [02 — Laravel architecture](02-laravel-architecture.md): stack, packages, module layout, auth, queues, conventions.
3. [03 — Figma flows & screen map](03-figma-flows-and-screen-map.md): every screen mapped to an API and a flow diagram.
4. [04 — Data model](04-data-model.md): tables, ERD, ledger rules, state machines.
5. [05 — API reference](05-api-reference.md): every endpoint the three clients need.
6. [06 — Shopify & Stripe integration](06-shopify-and-stripe-integration.md): webhooks, donation calculation, transfers, failure handling.
7. [07 — Build plan](07-build-plan.md): phases, deliverables, exit criteria, testing, risks.

## One-paragraph summary

Schools sign up in the school portal, verify their email by OTP, fill in a profile, and wait for admin approval. Approved schools appear in a public API that the Shopify theme uses for a cart-wide "Support a School" selector. The chosen `school_id` rides on the Shopify cart and order as an attribute. When Shopify sends a paid-order webhook, Laravel validates it and calculates the donation from the admin-configured school percentage. It records the donation in an append-only ledger and shows it to the school as "Pending". After a hold period a scheduled job transfers the payable donations to the school's Stripe Connect account, and they flip to "Completed". The school then withdraws its Connect balance to its bank from a Wallet screen. The admin sets the donation rate, can see every transfer and withdrawal, and has an override to release a transfer early.
