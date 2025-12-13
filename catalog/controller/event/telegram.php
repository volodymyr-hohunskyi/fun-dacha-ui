<?php
class ControllerEventTelegram extends Controller {

    public function sendOrder($order_id) {
        $route = '';
        $args = [$order_id];
        $output = '';
        
        if (!$order_id) {
            $this->logTelegram("❌ Empty order_id, abort");
            return;
        }

        $this->newOrder($route, $args, $output);
    }
    
    public function newOrder(&$route, &$args, &$output) {
        $order_id = $args[0];
        
        if (empty($order_id)) {
            $this->logTelegram("❌ No order_id in args");
            return;
        }


        $this->load->model('checkout/order');
        $this->load->model('catalog/product');
        $this->load->model('extension/telegram');

        $order = $this->model_checkout_order->getOrder($order_id);

        if (!$order) {
              $this->logTelegram("❌ Order not found: {$order_id}");
              return;
          }


        $products = $this->model_checkout_order->getOrderProducts($order_id);
        $totals   = $this->model_checkout_order->getOrderTotals($order_id);

        $this->logTelegram(
            "Order loaded. Products=" . count($products) .
            ", Totals=" . count($totals)
        );
        
        // --- Header ---
        $message  = "🛒 *NEW ORDER* #{$order_id}\n";
        $message .= "📅 " . date('d M Y, H:i', strtotime($order['date_added'])) . "\n\n";

        // --- Customer ---
        $message .= "👤 *Customer*\n";
        $message .= "{$order['firstname']} {$order['lastname']}\n";
        $message .= "📞 {$order['telephone']}\n";
        if ($order['email']) {
            $message .= "✉️ {$order['email']}\n";
        }
        $message .= "\n";

        // --- Products ---
        $message .= "📦 *Products*\n";
        $i = 1;
        foreach ($products as $product) {
            $product_info = $this->model_catalog_product->getProduct($product['product_id']);
            $product_url  = $product_info
                ? $this->url->link('product/product', 'product_id=' . $product['product_id'])
                : '';

            $price = $this->currency->format(
                $product['price'],
                $order['currency_code'],
                $order['currency_value'],
                false
            );

            $message .= "{$i}️⃣ *{$product['name']}*\n";
            $message .= "Qty: {$product['quantity']} × {$price} {$order['currency_code']}\n";

            if ($product_url) {
                $message .= "🔗 {$product_url}\n";
            }

            $message .= "\n";
            $i++;
        }

        // --- Comment ---
        if (!empty($order['comment'])) {
            $message .= "💬 *Customer Comment*\n";
            $message .= "{$order['comment']}\n\n";
        }

        // --- Totals ---
        $message .= "💰 *Summary*\n";
        foreach ($totals as $total) {
            $value = $this->currency->format(
                $total['value'],
                $order['currency_code'],
                $order['currency_value'],
                false
            );
            $message .= "{$total['title']}: {$value} {$order['currency_code']}\n";
        }

        // --- Admin Link ---
        $admin_url = HTTPS_SERVER . 'admin/index.php?route=sale/order/info&order_id=' . $order_id;
        $message .= "\n🔧 *Admin*\n{$admin_url}";

        try {
            $this->model_extension_telegram->send($message);
            $this->logTelegram("✅ Telegram sent successfully for order {$order_id}");
        } catch (\Throwable $e) {
            $this->logTelegram(
                "❌ Telegram send failed: " . $e->getMessage()
            );
        }
    }
    
    private function logTelegram($message) {
        $log = new Log('telegram.log');
        $log->write('[Telegram] ' . $message);
    }
}

