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

## Payment Runtime — Deprecated (R2)
Payment runtime removed from Telegram webhook (`bd072da`). Payment services, models, tables, UI still on disk but inactive. Cleanup plan exists (`docs/project/PAYMENT_CODE_CLEANUP_PLAN_R3.md`) but not implemented.

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
- ~~`POST /payments/{paymentCase}/approve`~~ (inactive)
- ~~`POST /payments/{paymentCase}/reject`~~ (inactive)

## Current Dashboard State
- Blade-based MVP dashboard with compact styled UI
- Customer list + per-customer conversation timeline (newest-first)
- Image preview with Telegram file proxy
- FAQ admin CRUD with active/inactive toggle
- Payment review cards with approve/reject buttons (INACTIVE, renders legacy data as generic system cards)
- No React frontend yet
- No dashboard auth layer yet

## Current Backend Shape
- Laravel is the source of truth
- Telegram webhook → normalizer → FAQ match → reply or Needs Reply
- webhook_events raw payload logging before normalization
- FaqMatcher DB-backed with priority ordering
- Inline CSS on Blade views (no Tailwind build required)
- No payment OCR, no Cloudflare Worker calls, no payment_case creation

## Next Recommended Features
- R3: Payment code cleanup (delete inactive files, replace UI fallback)
- Dashboard auth before production
- Viber channel MVP
- FAQ import / AI dataset builder
