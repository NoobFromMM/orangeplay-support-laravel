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

## F2 — DB FAQ Auto Replies

### Steps
1. Run `php artisan db:seed --class=FaqSeeder` to seed FAQ entries.
2. Send `hi` to the Telegram bot.
   - Expected: greeting reply, status Resolved.
3. Send `တစ်လဘယ်လောက်လဲ` to the bot.
   - Expected: pricing reply with "၁လ", "၅၀၀၀", status Resolved.
4. Send `သက်တမ်းတိုးချင်လို့` to the bot.
   - Expected: pricing reply (NOT payment account number), status Resolved.
5. Send `မင်ဘာဝင်ချင်တယ်` to the bot.
   - Expected: pricing reply, status Resolved.
6. Send `kpay နံပါတ်ပေးပါ` to the bot.
   - Expected: payment account reply with "Kpay", "09964349887", status Resolved.
7. Send `xyzzy123blah` to the bot.
   - Expected: NO bot reply, status "Needs Reply".
8. Open `/dashboard`.
   - Confirm customer appears.
   - Confirm correct status labels (Resolved / Needs Reply).

### Manual Pass Checklist

| Step | Input | Expected Category | Checklist |
|------|-------|-------------------|-----------|
| 1 | `hi` | greeting | [x] reply correct |
| 2 | `တစ်လဘယ်လောက်လဲ` | pricing | [x] contains "၁လ", "၅၀၀၀" |
| 3 | `သက်တမ်းတိုးချင်လို့` | pricing | [x] NO payment account number |
| 4 | `မင်ဘာဝင်ချင်တယ်` | pricing | [x] contains pricing info |
| 5 | `kpay နံပါတ်ပေးပါ` | payment | [x] contains "Kpay", "09964349887" |
| 6 | `xyzzy123blah` | unknown | [x] no reply, status "Needs Reply" |

### Pass Record

| Field | Value |
|-------|-------|
| Date | 2026-05-17 |
| Tester | Myat Thaw Maung |
| Telegram user | @NoobFromMM |
| All cases matched | Yes |
| Dashboard labels correct | Yes |
| Result | **PASS** |

### Result Template
```text
F2 Manual Test
Telegram user:
hi → greeting: yes/no
တစ်လဘယ်လောက်လဲ → pricing: yes/no
သက်တမ်းတိုးချင်လို့ → pricing (no payment number): yes/no
မင်ဘာဝင်ချင်တယ် → pricing: yes/no
kpay နံပါတ်ပေးပါ → payment account: yes/no
xyzzy123blah → no reply / Needs Reply: yes/no
Dashboard labels correct: yes/no
OK/Fail:
Issues:
```

## F3 — Admin Reply from Dashboard to Telegram

### Steps
1. Make sure a real Telegram customer exists by sending `hi` to the bot first.
2. Open the customer conversation page at `/customers/telegram/{user_id}`.
3. Type a reply (e.g., "This is a test reply from admin") in the textarea.
4. Click "Send Reply".
5. Confirm the customer receives the message in Telegram.
6. Check the dashboard timeline — admin reply appears with sender_type "admin".
7. Confirm conversation status changes to `in_chat`.

### Manual Pass Checklist

| Step | Checklist |
|------|-----------|
| Admin reply form visible | [ ] |
| Message sent via Telegram | [ ] |
| Admin message saved in timeline | [ ] |
| sender_type = admin | [ ] |
| Conversation status = in_chat | [ ] |

### Result Template
```text
F3 Manual Test
Telegram user:
Reply form visible: yes/no
Telegram message received: yes/no
Timeline shows admin reply: yes/no
Status changed to in_chat: yes/no
OK/Fail:
Issues:
```

## F4 — Image Preview
Pending.

## F5 — Payment Screenshot
Pending.
