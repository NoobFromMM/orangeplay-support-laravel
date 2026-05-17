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

Runs all locked feature smoke tests. Currently runs `smoke:f1`.

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
