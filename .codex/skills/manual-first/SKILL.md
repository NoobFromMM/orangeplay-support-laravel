# Manual First Skill

Use for Telegram or dashboard behavior that the user must verify in real use.

## Rule
Smoke tests are not enough by themselves.

## Process
1. Implement the minimal requested behavior.
2. Run automated tests.
3. Ask the user to manually test the real flow.
4. If the user reports pass, update lock coverage.
5. If the user reports fail, inspect the evidence before changing anything else.

## Reminder
- Do not mark a feature locked before manual pass.
- Do not expand scope while waiting for manual feedback.
