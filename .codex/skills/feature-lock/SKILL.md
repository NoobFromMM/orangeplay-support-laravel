# Feature Lock Skill

Use for any feature work.

## Rules
- Read `docs/qa/FEATURE_LOCKS.md` first.
- Identify locked features.
- Do not break locked behavior.
- Keep the task to one requested feature or bug.

## Required Gates
Before commit:

```bash
php artisan test
php artisan smoke:locked
```

Run the feature-specific smoke test too.

## Manual First
- User-facing behavior needs manual confirmation before it is treated as locked.
- Update lock coverage only after manual pass.

## Push Rule
- Do not push unless the user explicitly asks.
