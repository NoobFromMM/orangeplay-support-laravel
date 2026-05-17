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
- simulate Telegram `hi`
- create/update customer
- create/update conversation
- store inbound message
- create outbound bot reply
- label resolves to `Resolved`
- reply text does not contain `OrangePlayAI`
- reply text does not contain `Support Bot`
