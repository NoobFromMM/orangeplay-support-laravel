# Smoke Tests

## Required Before Commit

```bash
php artisan test
php artisan smoke:locked
```

Feature-specific smoke examples:

```bash
php artisan smoke:f1
php artisan smoke:f2
php artisan smoke:f3
php artisan smoke:webhook-events
php artisan smoke:telegram-image
php artisan smoke:image-admin-reply
php artisan smoke:faq-admin
php artisan smoke:human-takeover
php artisan smoke:case-create
```

If frontend assets exist:

```bash
npm run build
```

## Smoke Test Philosophy
Smoke tests should represent the manual behavior, but they do not replace manual testing.

Manual pass comes first. Smoke lock comes after.

## F1 Smoke Expected
- simulate Telegram `hi` payload
- create/update customer
- create/update conversation
- store inbound message
- create outbound bot reply
- label resolves to `Resolved`
- reply text does not contain `OrangePlayAI`
- reply text does not contain `Support Bot`

### Run

```bash
php artisan smoke:f1
```

Output (pass):
```
F1 Smoke Test — Telegram Greeting Flow
====================================
 OK Customer created: platform=telegram
 OK Customer platform_user_id correct
 OK Customer display_name='Test User'
 OK Conversation linked to customer
 OK 2 messages saved (inbound + outbound)
 OK Inbound message saved: text='hi'
 OK Outbound reply text matches expected
 OK Reply does NOT contain 'OrangePlayAI'
 OK Reply does NOT contain 'Support Bot'
 OK Message order is id ASC
 OK Conversation status is 'resolved'
ALL ASSERTIONS PASSED
```

### Locked Smoke

```bash
php artisan smoke:locked
```

Runs all locked feature smoke tests. Currently runs `smoke:f1`, `smoke:f2`, `smoke:f3`, `smoke:webhook-events`, `smoke:telegram-image`, `smoke:image-admin-reply`, `smoke:faq-admin`, and `smoke:human-takeover`.

---

## F2 Smoke Expected

Runs smoke tests without real Telegram network calls.

Test cases:
1. `hi` → greeting reply, status Resolved
2. `hello` → greeting reply, status Resolved
3. `တစ်လဘယ်လောက်လဲ` → pricing reply, status Resolved
4. `သက်တမ်းတိုးချင်လို့` → pricing reply, status Resolved
5. `သက်တန်းတိုးချင်လို့` → pricing reply, status Resolved
6. `မင်ဘာဝင်ချင်တယ်` → pricing reply, status Resolved
7. `member ဝင်ချင်တယ်` → pricing reply, status Resolved
8. `kpay နံပါတ်ပေးပါ` → payment account reply, status Resolved
9. `ငွေလွှဲမယ်` → payment account reply, status Resolved
10. `xyzzy123blah` → no outbound bot reply, status Needs Reply

Pricing reply assertions:
- Contains "၁လ" and "၅၀၀၀"
- Does NOT contain "09964349887"

Payment reply assertions:
- Contains "09964349887"
- Contains "Kpay"

All replies:
- Do NOT contain "OrangePlayAI" or "Support Bot"

### Run

```bash
php artisan smoke:f2
```

---

## F3 Smoke Expected

Smoke tests without real Telegram network calls. Uses Http::fake.

Test cases:
1. Successful admin reply with chat_id 555000 and text "Hello from admin"
2. Failed Telegram send (403) — no admin message saved

Assertions:
- Telegram send succeeds for valid chat_id
- Admin outbound message saved with direction=outbound, sender_type=admin
- metadata.source = dashboard
- Conversation status = Needs Reply
- bot_paused = true
- Timeline order = created_at ASC, id ASC
- Failed send: no admin message saved, status unchanged

### Run

```bash
php artisan smoke:f3
```

---

## Human Takeover Smoke Expected

Smoke tests without real Telegram network calls. Uses Http::fake.

Test cases:
1. FAQ match while bot active → bot replies, conversation resolves, bot_paused=false
2. Admin reply → bot_paused=true, status=Needs Reply
3. FAQ match while paused → no bot reply, status stays Needs Reply
4. Manual resolve → bot_paused=false
5. FAQ match after resolve → bot replies again, conversation resolves
6. Manual reopen → bot_paused=true, status=Needs Reply

### Run

```bash
php artisan smoke:human-takeover
```

---

## Webhook Events Smoke Expected

Smoke tests without real Telegram network calls.

Test cases:
1. `hi` → webhook event logged, channel=telegram, external_event_id=update_id, payload.message.text=hi, status=processed, processed_at set, F1 greeting still works
2. `xyzzyblah` → webhook event logged, status=processed, conversation status=Needs Reply, no outbound bot reply

### Run

```bash
php artisan smoke:webhook-events
```

---

## Telegram Image Smoke Expected

Smoke tests without real Telegram network calls.

Test cases:
1. Photo message (no caption) → normalizer detects image type, picks largest photo by file_size, metadata includes telegram_file_id/dimensions, webhook event logged, no bot auto-reply, conversation status=Needs Reply
2. Photo with caption "hello" → caption preserved in text and metadata

### Run

```bash
php artisan smoke:telegram-image
```

---

## Image Admin Reply Regression Smoke Expected

Tests the full image → admin reply → bot pause flow with faked Telegram network.

Flow:
1. Simulate Telegram photo → image saved, status=Needs Reply, no bot reply
2. Admin replies via TelegramBotService → outbound admin message saved, status=Needs Reply, bot_paused=true
3. Original image message and metadata preserved

Assertions:
- Image receive: webhook event, message_type=image, telegram_file_id, Needs Reply, no bot reply
- Admin reply: send faked, admin outbound saved, sender_type=admin, source=dashboard, status=Needs Reply, bot_paused=true
- Regression: original image still exists, telegram_file_id intact, timeline order correct

### Run

```bash
php artisan smoke:image-admin-reply
```

---

## FAQ Admin Smoke Expected

Smoke tests without real Telegram network calls.

Test cases:
- A: Create FAQ with keywords, answer, priority
- B: Update FAQ answer, keywords, priority
- C: Toggle active/inactive
- D: Active FAQ matches via FaqMatcher (including Burmese keyword)
- E: Inactive FAQ does not match

### Run

```bash
php artisan smoke:faq-admin
```

---

## Case Create Smoke Expected

Smoke tests without real Telegram network calls.

Test cases:
1. Create a case from an inbound Telegram text message
2. The case links to the source message and conversation
3. Source text is preserved
4. The case appears in `/cases` when queried through app logic

### Run

```bash
php artisan smoke:case-create
```

---

## Payment Smokes — Removed (R3)

All payment-related smoke commands (`smoke:payment-foundation`, `smoke:payment-screenshot`, `smoke:payment-webhook`, `smoke:payment-email-attach`, `smoke:payment-resolution`, `smoke:payment-duplicate`, `smoke:customer-emails`) have been deleted from the codebase. The respective sections have been removed from this document.
