# OrangePlay Support — Product Requirements

## Goal
Build a customer support dashboard for OrangePlay.

Customers contact OrangePlay through:
1. Telegram
2. Viber
3. Facebook Messenger

Start with Telegram only. Add Viber and Messenger later.

## Admin Needs
Admins need to:
- see customer inbox
- open customer conversations
- reply to customers
- see customer/bot/admin/system timeline
- handle payment screenshots
- manage support cases

## Bot Needs
The bot should:
- answer simple FAQ questions
- return pricing for package/member/renewal questions
- return payment account only for explicit payment account questions
- ask for email after payment screenshot
- stay silent or escalate when it cannot answer

## Phase Order
F1 — Telegram hi flow
F2 — FAQ pricing/payment text
F3 — Admin reply to Telegram
F4 — Image preview
F5 — Payment screenshot
F6 — Viber channel
F7 — Facebook Messenger channel

## Manual-First Rule
The user will manually test real behavior. Only after manual pass should the feature be locked with a smoke test.
