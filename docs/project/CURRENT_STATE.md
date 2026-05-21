# Current State

## Active Locked Features (8)
- F1 — Telegram Greeting Flow
- F2 — DB FAQ Auto Reply
- F3 — Admin Reply from Dashboard to Telegram
- W1/W2 — Telegram Webhook Raw Logging
- F5 — Telegram Image Receive + Dashboard Preview
- F5A — Image + Admin Reply Regression Flow
- F7 — FAQ Admin Data Input Management
- F8 — Human Takeover Bot Pause

**smoke:locked**: 8 features, all pass.

## Payment Runtime — Fully Removed (R3)
Payment runtime removed from webhook (R2) and all payment code deleted from the codebase (R3). No payment services, models, controllers, routes, smoke commands, or UI remain. Legacy payment data in DB tables is inert — rendered as generic system text in the timeline.

## Current Runtime Behavior
- FAQ match → bot auto-reply + status=resolved
- FAQ miss → status=Needs Reply
- Image/file → saved with preview + status=Needs Reply
- Admin reply → reaches Telegram + status=Needs Reply + bot paused
- Human takeover active → inbound text skips FAQ auto-reply until manual resolve clears pause

## Current Routes
- `POST /webhooks/telegram`
- `GET /dashboard`
- `GET /customers/{platform}/{platformUserId}`
- `POST /customers/{platform}/{platformUserId}/reply`
- `GET /dashboard/faqs` (CRUD + toggle)
- `GET /customers/{platform}/{platformUserId}/cases/create`
- `POST /customers/{platform}/{platformUserId}/cases`
- `GET /cases`
- `GET /cases/{supportCase}`
- `POST /cases/{supportCase}/resolve`
- `POST /cases/{supportCase}/reject`
- `GET /telegram/file/{fileId}` (image proxy)
- `GET /`

## Current Dashboard State
- Blade-based MVP dashboard with compact styled UI
- Customer list + per-customer conversation timeline (chronological)
- Manual Resolve/Reopen controls on the conversation view
- Small "Bot paused" indicator when human takeover is active
- Support case MVP: create a case from a conversation page against an inbound customer message or image/file message, browse cases in `/cases`, and show pinned case cards in the conversation view
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
- Support case creation from conversation-scoped source messages, stored in `support_cases`
- Inline CSS on Blade views (no Tailwind build required)
- No payment OCR, no Cloudflare Worker, no payment_case creation

## Next Recommended Features
- Dashboard auth before production
- Case workflow polish and filters
- Viber channel MVP
- FAQ import / AI dataset builder
