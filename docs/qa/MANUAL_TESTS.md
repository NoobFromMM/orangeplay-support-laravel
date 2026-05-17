# Manual Tests

## F1 — Telegram Greeting Flow

### Steps
1. Set `TELEGRAM_BOT_TOKEN` in `.env`.
2. Start `php artisan serve` and `ngrok http 8000`.
3. Set Telegram webhook to `{ngrok_url}/webhooks/telegram`.
4. Send `hi` to the Telegram bot.
5. Send `hello` to the Telegram bot.
6. Confirm bot replies with exact text:
   `မင်္ဂလာပါရှင့် Orange Play Customer Service မှကြိုဆိုပါတယ်။ဘာများကူညီပေးရမလဲရှင့်`
7. Open `/dashboard`.
8. Confirm customer appears.
9. Confirm status label is `Resolved`.
10. Click customer to open conversation page.
11. Confirm timeline shows inbound `hi`/`hello` and outbound bot reply.

### Manual Pass Checklist

| Step | Check |
|------|-------|
| send `hi` | [x] Bot replied |
| send `hello` | [x] Bot replied |
| reply text | [x] Exact match |
| dashboard shows customer | [x] Yes — Myat Thaw Maung |
| conversation timeline | [x] Inbound + bot reply visible |
| status | [x] Resolved |

### Pass Record

| Field | Value |
|-------|-------|
| Date | 2026-05-17 |
| Tester | Myat Thaw Maung |
| Telegram user | @NoobFromMM |
| Bot replied | Yes |
| Reply text correct | Yes |
| Dashboard visible | Yes |
| Status | Resolved |
| Result | **PASS** |

## F2 — FAQ Pricing/Payment Text
Pending.

## F3 — Admin Reply
Pending.

## F4 — Image Preview
Pending.

## F5 — Payment Screenshot
Pending.
