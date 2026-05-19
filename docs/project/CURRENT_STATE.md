# Current State

## Locked Features (13)
- F1 — Telegram Greeting Flow
- F2 — DB FAQ Auto Reply
- F3 — Admin Reply from Dashboard to Telegram
- W1/W2 — Telegram Webhook Raw Logging
- F5 — Telegram Image Receive + Dashboard Preview
- F5A — Image + Admin Reply Regression Flow
- F6 P1 — Payment Case Foundation
- F6 P2 — Payment Screenshot Processing Service
- F6 P3 — Payment Webhook Integration
- F6 P3D — Payment Review Card UI + Conversation DESC Order
- F6 P4 — Ask Email After Payment Screenshot
- F6 P5 — Payment Email Attachment Flow
- F6 P6A — Payment Case Resolution Service
- F6 P6B — Dashboard Payment Approve/Reject Actions
- F7 — FAQ Admin Data Input Management
- F8 — Duplicate Payment Detection

**smoke:locked**: 13 features, all pass.

## Complete Payment Lifecycle
1. Screenshot received → payment_check via Cloudflare Worker
2. Payment detected → payment_case created + payment_review_card
3. Email missing → bot asks for email (needs_email)
4. Email received → attached to case (pending_review)
5. Admin approves/rejects → payment_status_update + resolution
6. Duplicate detection → matched by transaction_id, no new case, status-specific bot reply

## Current Routes
- `POST /webhooks/telegram`
- `GET /dashboard`
- `GET /customers/{platform}/{platformUserId}`
- `POST /customers/{platform}/{platformUserId}/reply`
- `POST /payments/{paymentCase}/approve`
- `POST /payments/{paymentCase}/reject`
- `GET /dashboard/faqs` (CRUD + toggle)
- `GET /telegram/file/{fileId}` (image proxy)
- `GET /`

## Current Dashboard State
- Blade-based MVP dashboard with compact styled UI
- Customer list + per-customer conversation timeline
- Image preview with Telegram file proxy
- Payment review cards with approve/reject buttons
- FAQ admin CRUD with active/inactive toggle
- Conversation newest-first display order
- No React frontend yet
- No dashboard auth layer yet

## Current Backend Shape
- Laravel is the source of truth
- Telegram webhook → normalizer → FAQ match / payment check
- DuplicatePaymentDetector matches by transaction_id
- PaymentCaseResolutionService with status guards
- webhook_events raw payload logging before normalization
- FaqMatcher DB-backed with priority ordering
- Inline CSS on Blade views (no Tailwind build required)

## Next Recommended Features
- A. FAQ import / AI dataset builder
- B. Dashboard auth before production
- C. Viber channel MVP
- D. Payment notification via Telegram bot after approve/reject
