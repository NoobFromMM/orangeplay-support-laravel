# Manual First QA Skill

Use this skill for flows that depend on real Telegram/Viber/Messenger behavior.

## Rule
Manual real-world test comes before locking.

Do not mark a feature done only from synthetic tests when the feature depends on channel behavior.

## Process
1. Implement minimal feature.
2. Run automated gates.
3. Ask user for manual test.
4. User reports pass/fail.
5. If fail, fix only that issue.
6. If pass, add/update smoke lock.
7. Commit.

## Manual Result Template
```text
Feature:
Manual steps:
Expected:
Actual:
OK/Fail:
Screenshots/issues:
```
