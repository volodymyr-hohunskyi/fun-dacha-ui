# Telegram Integration Setup

## Overview
The Telegram integration sends notifications to Telegram when a new order is placed.

## Files
- **Controller**: `catalog/controller/event/telegram.php`
- **Model**: `extension/opencart/catalog/model/telegram.php`
- **Integration**: `catalog/model/checkout/order.php` (line 113)

## Configuration

The Telegram bot requires two configuration values:
1. **Bot Token**: Your Telegram bot token (from @BotFather)
2. **Chat ID**: The chat ID where messages should be sent

### Setting Configuration Values

You need to add these settings to your OpenCart configuration. You can do this via:

**Option 1: Admin Panel (Settings → Settings → Edit Store)**
- Add custom settings: `telegram_bot_token` and `telegram_chat_id`

**Option 2: Database**
Run this SQL (replace with your values):
```sql
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES
(0, 'config', 'telegram_bot_token', 'YOUR_BOT_TOKEN', 0),
(0, 'config', 'telegram_chat_id', 'YOUR_CHAT_ID', 0)
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
```

**Option 3: Config File**
Add to `config.php`:
```php
define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN');
define('TELEGRAM_CHAT_ID', 'YOUR_CHAT_ID');
```
Then update `extension/opencart/catalog/model/telegram.php` to use these constants.

## Getting Your Bot Token
1. Open Telegram and search for @BotFather
2. Send `/newbot` command
3. Follow instructions to create a bot
4. Copy the bot token

## Getting Your Chat ID
1. Start a chat with your bot
2. Send a message to your bot
3. Visit: `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates`
4. Find `"chat":{"id":123456789}` in the response
5. Use that number as your chat ID

## Testing
1. Place a test order
2. Check `system/storage/logs/telegram.log` for any errors
3. You should receive a Telegram message with order details

## Troubleshooting

### No messages received
1. Check `system/storage/logs/telegram.log` for errors
2. Verify bot token and chat ID are correct
3. Make sure you've sent at least one message to your bot
4. Check that cURL is enabled on your server

### Check logs
View the log file: `system/storage/logs/telegram.log`

Common errors:
- "Bot token or chat ID not configured" - Settings missing
- "cURL error" - Server connectivity issue
- "HTTP error: 401" - Invalid bot token
- "HTTP error: 400" - Invalid chat ID or message format

