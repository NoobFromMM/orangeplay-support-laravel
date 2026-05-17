# Current State

## Locked Features
- F1 Telegram Greeting Flow
- F2 DB FAQ Auto Reply
- F3 Admin Reply from Dashboard to Telegram

## Current Routes
- `POST /webhooks/telegram`
- `GET /dashboard`
- `GET /customers/{platform}/{platformUserId}`
- `POST /customers/{platform}/{platformUserId}/reply`
- `GET /`

## Current Dashboard State
- Blade-based MVP dashboard
- Customer list view plus per-customer conversation timeline
- Admin reply form for Telegram only
- No React frontend yet
- No dashboard auth layer yet

## Current Backend Shape
- Laravel is the source of truth
- Telegram webhook normalization happens in app services
- Message timeline is stored in `customers`, `conversations`, and `messages`
- FAQ auto reply is DB-backed

## Next Recommended Step
- Add a raw inbound event archive such as `webhook_events` before normalization
- Keep it additive and separate from message storage
- Use it as the safest base for future channel work
