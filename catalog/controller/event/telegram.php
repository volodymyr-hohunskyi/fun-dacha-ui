<?php
namespace Opencart\Catalog\Controller\Event;

class Telegram extends \Opencart\System\Engine\Controller {
    
    public function sendOrder($order_id): void {
        if (!$order_id) {
            $this->logTelegram("❌ Empty order_id, abort");
            return;
        }

        $this->load->model('checkout/order');
        $this->load->model('catalog/product');
        $this->load->model('catalog/category');
        $this->load->model('extension/opencart/telegram');

        $order = $this->model_checkout_order->getOrder($order_id);

        if (!$order) {
            $this->logTelegram("❌ Order not found: {$order_id}");
            return;
        }

        $products = $this->model_checkout_order->getProducts($order_id);
        $totals   = $this->model_checkout_order->getTotals($order_id);

        $this->logTelegram(
            "Order loaded. Products=" . count($products) .
            ", Totals=" . count($totals)
        );
        
        // --- Header ---
        $message  = "🛒 НОВЕ ЗАМОВЛЕННЯ #{$order_id}\n";
        $message .= "📅 " . date('d M Y, H:i', strtotime($order['date_added'])) . "\n\n";

        // --- Customer ---
        $message .= "👤 Клієнт\n";
        $message .= "{$order['firstname']} {$order['lastname']}\n";
        $message .= "📞 {$order['telephone']}\n";
        if ($order['email']) {
            $message .= "✉️ {$order['email']}\n";
        }
        $message .= "\n";

        // --- Products ---
        $message .= "📦 Товари\n";
        $i = 1;
        foreach ($products as $product) {
            $product_info = $this->model_catalog_product->getProduct($product['product_id']);
            
            // Build product URL directly to avoid SEO URL rewrite issues
            $product_url = '';
            if ($product_info) {
                $base_url = $this->config->get('config_url');
                if (empty($base_url)) {
                    $protocol = (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $base_url = $protocol . $this->request->server['HTTP_HOST'] . rtrim(dirname($this->request->server['PHP_SELF']), '/.\\') . '/';
                }
                
                // Get product categories and build path
                $product_categories = $this->model_catalog_product->getCategories($product['product_id']);
                $path_param = '';
                
                if (!empty($product_categories)) {
                    // Get the first category (main category)
                    $main_category_id = $product_categories[0]['category_id'];
                    
                    // Get category path
                    $category_path_query = $this->db->query("SELECT `path_id` FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$main_category_id . "' ORDER BY `level` ASC");
                    
                    if ($category_path_query->num_rows > 0) {
                        $path_parts = [];
                        foreach ($category_path_query->rows as $path_row) {
                            $path_parts[] = $path_row['path_id'];
                        }
                        $path_parts[] = $main_category_id;
                        $path_param = '&path=' . implode('_', $path_parts);
                    } else {
                        // Fallback: use category_id directly if no path found
                        $path_param = '&path=' . $main_category_id;
                    }
                }
                
                $product_url = rtrim($base_url, '/') . '/index.php?route=product/product&language=' . $this->config->get('config_language') . '&product_id=' . $product['product_id'] . $path_param;
            }

            $price = $this->currency->format(
                $product['price'],
                $order['currency_code'],
                $order['currency_value'],
                false
            );

            // Format product number - use emoji for 1-9, regular numbers for 10+
            $product_number = ($i <= 9) ? "{$i}️⃣" : "{$i}.";
            $message .= "{$product_number} {$product['name']}\n";

            $order_options = $this->model_checkout_order->getOptions($order_id, (int)$product['order_product_id']);
            foreach ($order_options as $option) {
                $opt_label = trim((string)($option['name'] ?? ''));
                $opt_value = $this->sanitizeTelegramOptionValue((string)($option['value'] ?? ''));
                if ($opt_value === '') {
                    continue;
                }
                $line = $opt_label !== '' ? "{$opt_label}: {$opt_value}" : $opt_value;
                $message .= "   • {$line}\n";
            }

            $message .= "Кількість: {$product['quantity']} × {$price} {$order['currency_code']}\n";

            if ($product_url) {
                $message .= "🔗 {$product_url}\n";
            }

            $message .= "\n";
            $i++;
        }

        // --- Comment ---
        if (!empty($order['comment'])) {
            $message .= "💬 Коментар клієнта\n";
            $message .= "{$order['comment']}\n\n";
        }

        // --- Shipping Address ---
        if (!empty($order['shipping_address_1']) || !empty($order['shipping_city'])) {
            $message .= "📍 Адреса доставки\n";
            
            if (!empty($order['shipping_firstname']) || !empty($order['shipping_lastname'])) {
                $shipping_name = trim(($order['shipping_firstname'] ?? '') . ' ' . ($order['shipping_lastname'] ?? ''));
                if ($shipping_name) {
                    $message .= "{$shipping_name}\n";
                }
            }
            
            if (!empty($order['shipping_company'])) {
                $message .= "{$order['shipping_company']}\n";
            }
            
            if (!empty($order['shipping_address_1'])) {
                $message .= "{$order['shipping_address_1']}\n";
            }
            
            if (!empty($order['shipping_address_2'])) {
                $message .= "{$order['shipping_address_2']}\n";
            }
            
            $address_parts = [];
            if (!empty($order['shipping_city'])) {
                $address_parts[] = $order['shipping_city'];
            }
            if (!empty($order['shipping_zone'])) {
                $address_parts[] = $order['shipping_zone'];
            }
            if (!empty($order['shipping_postcode'])) {
                $address_parts[] = $order['shipping_postcode'];
            }
            if (!empty($address_parts)) {
                $message .= implode(', ', $address_parts) . "\n";
            }
            
            if (!empty($order['shipping_country'])) {
                $message .= "{$order['shipping_country']}\n";
            }
            
            $message .= "\n";
        }

        // --- Totals ---
        $message .= "💰 Підсумок\n";
        foreach ($totals as $total) {
            // Skip shipping/delivery if amount is 0
            $total_value = (float)$total['value'];
            $total_title_lower = mb_strtolower($total['title'], 'UTF-8');
            
            // Check if it's a shipping/delivery total with zero value
            $is_shipping = (
                stripos($total_title_lower, 'доставк') !== false ||
                stripos($total_title_lower, 'shipping') !== false ||
                stripos($total_title_lower, 'delivery') !== false ||
                stripos($total_title_lower, 'новою поштою') !== false ||
                stripos($total_title_lower, 'nova poshta') !== false
            );
            
            if ($is_shipping && abs($total_value) < 0.01) {
                continue;
            }
            
            $value = $this->currency->format(
                $total['value'],
                $order['currency_code'],
                $order['currency_value'],
                false
            );
            $message .= "{$total['title']}: {$value} {$order['currency_code']}\n";
        }

        // --- Admin Link ---
        // Build admin URL from catalog URL
        $catalog_url = $this->config->get('config_url');
        if (defined('HTTP_CATALOG')) {
            $base_url = HTTP_CATALOG;
        } elseif ($catalog_url) {
            $base_url = $catalog_url;
        } else {
            $protocol = (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $base_url = $protocol . $this->request->server['HTTP_HOST'] . rtrim(dirname($this->request->server['PHP_SELF']), '/.\\') . '/';
        }
        
        // Replace catalog path with admin path
        $admin_url = str_replace('/catalog/', '/adminpage/', $base_url);
        // Use sale/order.info route (with dot) and don't include user_token - user will need to login
        $admin_url = rtrim($admin_url, '/') . '/index.php?route=sale/order.info&order_id=' . $order_id;
        // Fix HTML entities in URL
        $admin_url = str_replace('&amp;', '&', $admin_url);
        $message .= "\n🔧 Адмін\n{$admin_url}";

        try {
            $this->model_extension_opencart_telegram->send($message);
            $this->logTelegram("✅ Telegram sent successfully for order {$order_id}");
        } catch (\Throwable $e) {
            $this->logTelegram(
                "❌ Telegram send failed: " . $e->getMessage()
            );
        }
    }
    
    private function logTelegram(string $message): void {
        $log = new \Opencart\System\Library\Log('telegram.log');
        $log->write('[Telegram] ' . $message);
    }

    /**
     * Strip HTML / normalize whitespace for plain-text Telegram lines (options may contain markup).
     */
    private function sanitizeTelegramOptionValue(string $value): string {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value;
    }
}

