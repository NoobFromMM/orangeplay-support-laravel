# Channel Adapter Skill

Use this skill when implementing Telegram, Viber, or Messenger.

## Rule
Channel-specific details must stay inside channel services.
Business logic receives normalized data only.

## Normalized Message Shape
- platform
- platform_user_id
- display_name
- username
- message_type
- text
- file_id
- mime_type
- raw_payload
- metadata

## Telegram Mapping
From Telegram update:
- message.text -> text
- message.chat.id -> platform_user_id/chat id
- message.from.id -> user id
- message.from.first_name -> display_name
- message.from.username -> username
- message.photo[*].file_id -> file_id

## Sending
Use server-side API calls only.
Never expose bot token.

## Future Channels
Viber/Messenger must map to the same normalized shape.
Do not leak platform-specific logic into core business services.
