# Guest-Only Mode Configuration

This document explains how to enable/disable account authentication (Sign In / Sign Up) on the site.

## Overview

By default, the site runs in **guest-only mode**, which means:
- Sign In and Sign Up links are hidden from the header
- Direct access to `/account/login` and `/account/register` is blocked
- Users can only shop as guests
- Guest checkout is fully functional

## How to Toggle

### Option 1: Using SQL (Recommended)

Run the appropriate SQL command in your database:

**To DISABLE (Guest-only mode - default):**
```sql
DELETE FROM `oc_setting` WHERE `code` = 'config' AND `key` = 'config_account_enabled';
```

**To ENABLE (Allow sign in/sign up):**
```sql
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) 
VALUES (0, 'config', 'config_account_enabled', '1', 0)
ON DUPLICATE KEY UPDATE `value` = '1';
```

**Note:** Replace `oc_` with your actual database prefix if different.

### Option 2: Using Admin Panel (Future)

You can add this setting to the admin panel's Settings page if needed. The setting key is:
- **Key:** `config_account_enabled`
- **Code:** `config`
- **Value:** `1` (enabled) or empty/0 (disabled)

## Files Modified

1. `catalog/controller/common/header.php` - Checks config and conditionally sets account links
2. `catalog/view/template/common/header.twig` - Conditionally displays account dropdown
3. `catalog/controller/account/login.php` - Blocks access when disabled
4. `catalog/controller/account/register.php` - Blocks access when disabled

## Testing

1. **Guest-only mode (default):**
   - Visit the homepage - no account dropdown should be visible
   - Try accessing `/account/login` - should redirect to homepage
   - Try accessing `/account/register` - should redirect to homepage
   - Guest checkout should work normally

2. **Enabled mode:**
   - Visit the homepage - account dropdown should be visible
   - Click "Sign In" or "Sign Up" - should work normally
   - Users can create accounts and log in

