# Codex Task Template

Use this template for a single narrow task.

```text
Project: OrangePlay Laravel MVP
Lane: Codex
Branch: <branch-name>

Task:
<one feature or one bug only>

Allowed files:
<list exact files or folders>

Forbidden:
- app/*
- database/*
- routes/*
- resources/views/*
- .env
- any file outside scope

Rules:
- Do not change application behavior unless requested.
- Do not change migrations unless requested.
- Do not refactor architecture.
- Do not print secrets.
- Do not commit .env.
- Do not push unless the user asks.

Required gates:
php artisan test
php artisan smoke:locked
<feature-specific smoke if needed>

Final report:
- summary
- files changed
- behavior
- tests
- commit hash
- pushed? yes/no
- manual test needed/result
- risks deferred
```
