# Post-payment MVP Checkpoint

Date: 2026-05-19

This is a report-only checkpoint document. It summarizes the current architecture, locked features, risks, and the next phase after the payment MVP. It does not change code, migrations, routes, or behavior.

## Current Locked Feature Map

- F1 Telegram Greeting Flow
- F2 DB FAQ Auto Reply
- F3 Admin Reply from Dashboard to Telegram
- W1/W2 Telegram Webhook Raw Logging
- F5 Telegram Image Receive + Dashboard Preview
- F5A Image + Admin Reply Regression Flow
- F6 P1 Payment Case Foundation
- F6 P2 Payment Screenshot Processing Service
- F6 P3 Payment Webhook Integration
- F6 P5 Payment Email Attachment Flow
- F6 P6A Payment Case Resolution Service
- F6 P6B Dashboard Payment Approve/Reject Actions

## Current Architecture Reality

- Laravel backend is the source of truth
- Blade dashboard MVP is still the user interface layer
- Telegram is integrated directly through Laravel webhooks
- Cloudflare Worker is used for `payment_check` only
- MariaDB/MySQL is the persistence layer
- No n8n
- No React dashboard yet
- No Queue or Reverb yet

## Data Model Summary

- `customers`
- `conversations`
- `messages`
- `webhook_events`
- `faq_entries`
- `payment_cases`

## Payment Lifecycle Summary

1. Customer sends a payment screenshot
2. Telegram webhook is received by Laravel
3. `webhook_events` is logged
4. Image message is saved
5. Cloudflare Worker checks the screenshot
6. `payment_cases` is created when payment is detected
7. `payment_review_card` appears in the timeline
8. Bot asks for email if it is missing
9. Email reply attaches to the open payment case
10. Case moves to `pending_review`
11. Admin approves or rejects the payment case

## Known Gaps

- No login/auth layer
- No roles/permissions
- No Viber support
- No Facebook Messenger support
- No React dashboard
- No realtime WebSocket layer
- No queue retry system
- No subscription extension after approval yet
- No reviewer identity/audit ownership beyond the current dashboard metadata
- No duplicate transaction enforcement yet

## Risks

- The dashboard is accessible without login
- Approve means review approval only, not subscription extension
- Duplicate payment screenshots or transaction IDs can still be a concern
- The payment Worker path is synchronous and can be slow or fail
- Blade UI is MVP-only and will not scale forever

## Recommended Next Phase Options

### A. Security first
Why:
- protects the dashboard before any wider channel expansion
- reduces exposure of payment and customer data

Risk:
- adds upfront work before shipping more channel features

First small task:
- add dashboard login/auth before more channel or payment workflow expansion

### B. Viber channel MVP
Why:
- extends omnichannel coverage while the Telegram foundation is stable

Risk:
- adds another inbound channel before dashboard access is locked down

First small task:
- add Viber webhook normalization behind the same Laravel-first pattern

### C. Subscription/payment backend integration
Why:
- completes the business value of payment approval

Risk:
- can blur review approval with actual subscription extension if done too early

First small task:
- define the subscription extension boundary after payment approval

## Recommendation

Recommended next step:
- A. Add dashboard login/auth first before Viber/Messenger or subscription actions

Why:
- it is the safest next phase after the payment MVP
- it reduces exposure of customer and payment data
- it creates a cleaner base for future approvals, channel expansion, and admin actions

## Smoke / Test Recommendation

- Keep `php artisan smoke:locked` mandatory
- Add manual evidence notes for every new channel or payment action
- Avoid broad refactors while the MVP is still consolidating

## Locked Gate Reminder

Before any future commit in this repo, run:

```bash
php artisan test
php artisan smoke:locked
```

Do not lock a new feature until manual verification is complete.
