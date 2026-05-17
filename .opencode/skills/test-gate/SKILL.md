# Test Gate Skill

Use this skill before declaring done or committing.

## Required Commands

```bash
php artisan test
php artisan smoke:locked
```

Run feature-specific smoke command.

If frontend assets exist:

```bash
npm run build
```

## Report Format
Include:
- command
- result
- failures if any
- skipped tests and reason

Never say done if a gate failed.
