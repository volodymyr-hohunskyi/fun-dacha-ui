<?php
namespace Opencart\Catalog\Model\Extension\Opencart;

class Telegram extends \Opencart\System\Engine\Model {
    
    /**
     * Send message to Telegram
     *
     * @param string $message
     * @return bool
     */
    public function send(string $message): bool {
        // Try to get from config first
        $bot_token = $this->config->get('telegram_bot_token');
        $chat_id = $this->config->get('telegram_chat_id');
        
        // Fallback to constants if config is empty
        if (empty($bot_token) && defined('TELEGRAM_BOT_TOKEN')) {
            $bot_token = TELEGRAM_BOT_TOKEN;
        }
        
        if (empty($chat_id) && defined('TELEGRAM_CHAT_ID')) {
            $chat_id = TELEGRAM_CHAT_ID;
        }
        
        $log = new \Opencart\System\Library\Log('telegram.log');
        
        if (empty($bot_token) || empty($chat_id)) {
            $log->write('[Telegram] Bot token or chat ID not configured. Token: ' . (!empty($bot_token) ? 'SET' : 'EMPTY') . ', Chat ID: ' . (!empty($chat_id) ? $chat_id : 'EMPTY'));
            return false;
        }
        
        $log->write('[Telegram] Sending message to Chat ID: ' . $chat_id . ', Bot Token: ' . substr($bot_token, 0, 10) . '...');
        
        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        
        // Decode HTML entities first (in case data already contains them)
        $decoded_message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Escape HTML special characters for Telegram HTML parse mode
        // Telegram HTML supports: <b>, <i>, <u>, <s>, <a>, <code>, <pre>
        // Need to escape: <, >, &
        $escaped_message = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $decoded_message);
        
        $data = [
            'chat_id' => $chat_id,
            'text' => $escaped_message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $log = new \Opencart\System\Library\Log('telegram.log');
            $log->write('[Telegram] cURL error: ' . $error);
            return false;
        }
        
        if ($http_code !== 200) {
            $log = new \Opencart\System\Library\Log('telegram.log');
            $log->write('[Telegram] HTTP error: ' . $http_code . ' Response: ' . $response);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['ok']) || !$result['ok']) {
            $log = new \Opencart\System\Library\Log('telegram.log');
            $log->write('[Telegram] API error: ' . ($result['description'] ?? 'Unknown error') . ' Response: ' . $response);
            return false;
        }
        
        $log = new \Opencart\System\Library\Log('telegram.log');
        $log->write('[Telegram] Message sent successfully. Chat ID used: ' . $chat_id . ', Message ID: ' . ($result['result']['message_id'] ?? 'N/A'));
        
        return true;
    }
}

