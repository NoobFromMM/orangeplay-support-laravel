# Current State

## Active Locked Features (7)
- F1 — Telegram Greeting Flow
- F2 — DB FAQ Auto Reply
- F3 — Admin Reply from Dashboard to Telegram
- W1/W2 — Telegram Webhook Raw Logging
- F5 — Telegram Image Receive + Dashboard Preview
- F5A — Image + Admin Reply Regression Flow
- F7 — FAQ Admin Data Input Management

**smoke:locked**: 7 features, all pass.

## Payment Runtime — Fully Removed (R3)
Payment runtime removed from webhook (R2) and all payment code deleted from the codebase (R3). No payment services, models, controllers, routes, smoke commands, or UI remain. Legacy payment data in DB tables is inert — rendered as generic system text in the timeline.

## Current Runtime Behavior
- FAQ match → bot auto-reply + status=resolved
- FAQ miss → status=Needs Reply
- Image/file → saved with preview + status=Needs Reply
- Admin reply → reaches Telegram + status=in_chat

## Current Routes
- `POST /webhooks/telegram`
- `GET /dashboard`
- `GET /customers/{platform}/{platformUserId}`
- `POST /customers/{platform}/{platformUserId}/reply`
- `GET /dashboard/faqs` (CRUD + toggle)
- `GET /telegram/file/{fileId}` (image proxy)
- `GET /`

## Current Dashboard State
- Blade-based MVP dashboard with compact styled UI
- Customer list + per-customer conversation timeline (newest-first)
- Image preview with Telegram file proxy
- FAQ admin CRUD with active/inactive toggle
- No payment UI, no approve/reject buttons, no payment cards
- No React frontend yet
- No dashboard auth layer yet

## Current Backend Shape
- Laravel is the source of truth
- Telegram webhook → normalizer → FAQ match → reply or Needs Reply
- webhook_events raw payload logging before normalization
- FaqMatcher DB-backed with priority ordering
- Inline CSS on Blade views (no Tailwind build required)
- No payment OCR, no Cloudflare Worker, no payment_case creation

## Next Recommended Features
- Dashboard auth before production
- Viber channel MVP
- FAQ import / AI dataset builder
