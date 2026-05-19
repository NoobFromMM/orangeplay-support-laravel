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

Runs all locked feature smoke tests. Currently runs `smoke:f1`, `smoke:f2`, `smoke:f3`, `smoke:webhook-events`, `smoke:telegram-image`, `smoke:image-admin-reply`, `smoke:payment-foundation`, `smoke:payment-screenshot`, `smoke:payment-webhook`, `smoke:payment-email-attach`, `smoke:payment-resolution`, `smoke:faq-admin`, and `smoke:payment-duplicate`.

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
- Conversation status = in_chat
- Timeline order = created_at ASC, id ASC
- Failed send: no admin message saved, status unchanged

### Run

```bash
php artisan smoke:f3
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

Tests the full image → admin reply → in_chat flow with faked Telegram network.

Flow:
1. Simulate Telegram photo → image saved, status=Needs Reply, no bot reply
2. Admin replies via TelegramBotService → outbound admin message saved, status=in_chat
3. Original image message and metadata preserved

Assertions:
- Image receive: webhook event, message_type=image, telegram_file_id, Needs Reply, no bot reply
- Admin reply: send faked, admin outbound saved, sender_type=admin, source=dashboard, status=in_chat
- Regression: original image still exists, telegram_file_id intact, timeline order correct

### Run

```bash
php artisan smoke:image-admin-reply
```

---

## Payment Foundation Smoke Expected

Tests the payment case infrastructure without real network calls.

Test cases:
- A: Worker URL missing — returns safe failure (ok=false)
- B: Worker fake success — normalized result with provider, transaction_id, amount
- C: PaymentCaseService creates case with provider, transaction_id, status, worker_response, image_message_id
- D: Missing transaction_id — still creates case with null transaction_id
- E: is_payment=false — throws InvalidArgumentException

### Run

```bash
php artisan smoke:payment-foundation
```

---

## Payment Screenshot Processing Smoke Expected

Tests the PaymentScreenshotService without real network calls.

Test cases:
- A: is_payment=true with transaction_id — payment case + review card created, metadata links payment_case_id
- B: is_payment=true with null transaction_id — still creates case and review card
- C: is_payment=false — returns null, nothing created
- D: Non-image message — throws InvalidArgumentException

### Run

```bash
php artisan smoke:payment-screenshot
```

---

## Payment Webhook Integration Smoke Expected

Tests the full Telegram image webhook to payment check pipeline with Http::fake.

Test cases:
- A: image + is_payment=true → payment case + review card created, no bot reply
- B: image + is_payment=false → no case, no review card, status Needs Reply
- C: payment check HTTP 500 → image preserved, no crash, status Needs Reply
- D: text hi → F1 greeting still works

### Run

```bash
php artisan smoke:payment-webhook
```
