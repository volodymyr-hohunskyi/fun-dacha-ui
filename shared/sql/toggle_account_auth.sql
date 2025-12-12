-- Toggle Account Authentication (Sign In / Sign Up)
-- 
-- This script allows you to enable or disable account authentication.
-- When disabled (guest-only mode), sign in and sign up links are hidden,
-- and direct access to login/register pages is blocked.
--
-- Usage:
--   - To DISABLE (guest-only mode): Run the DELETE statement
--   - To ENABLE (allow sign in/sign up): Run the INSERT statement
--
-- Note: Replace 'oc_' with your actual database prefix if different

-- DISABLE Account Authentication (Guest-only mode)
-- This removes the setting, defaulting to disabled
DELETE FROM `oc_setting` WHERE `code` = 'config' AND `key` = 'config_account_enabled';

-- ENABLE Account Authentication (Allow sign in/sign up)
-- This sets the setting to enabled (value = 1)
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) 
VALUES (0, 'config', 'config_account_enabled', '1', 0)
ON DUPLICATE KEY UPDATE `value` = '1';

