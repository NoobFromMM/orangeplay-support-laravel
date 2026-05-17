# Implementation Scope Skill

Use this skill to prevent broad changes.

## Scope Rules
- One task = one feature or one bug.
- Do not modify unrelated files.
- Do not add future features early.
- Do not refactor broadly unless requested.
- Do not change architecture during feature work.

## Before Editing
State:
- intended files to edit
- intended behavior change
- tests to run

## Stop Conditions
Stop and report if:
- required schema change is discovered
- destructive command is needed
- feature scope expands
- secrets are needed
- a locked feature fails
