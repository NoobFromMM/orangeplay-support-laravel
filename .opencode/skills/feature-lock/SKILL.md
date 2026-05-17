# Feature Lock Skill

Use this skill for every feature.

## Workflow
1. Identify requested feature only.
2. Identify currently locked features.
3. Do not change unrelated behavior.
4. Implement minimal change.
5. Run tests.
6. Wait for user manual test when required.
7. Add/update smoke test after manual pass.
8. Commit only when all gates pass.

## Required Gates
Before commit:

```bash
php artisan test
php artisan smoke:locked
```

Run the feature-specific smoke test too.

If frontend build exists:

```bash
npm run build
```

## Failure Rule
If any locked feature breaks, stop. Do not continue new feature work.

## Push Rule
Do not push unless the user explicitly asks.
