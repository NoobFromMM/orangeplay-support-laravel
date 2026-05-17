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
