<?php

namespace proto\plugin;

if (! defined('ABSPATH')) {
    exit;
}
if (! class_exists('WcPayloadService')) {
    class WcPayloadService
    {
        public function __construct()
        {
            add_filter('woocommerce_rest_prepare_shop_order_object', [__CLASS__, 'updateRestShopOrderObject'], 10, 3);
            add_filter('woocommerce_webhook_payload', [__CLASS__, 'updateWebhookPayload']);
            add_filter('woocommerce_webhook_http_args', [__CLASS__, 'updateWebhookHttpArgs'], 99, 3);
            add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'updateOrderMeta'], 80, 2);
        }

        /**
         * @param $response
         * @param $webhook
         * @param $request
         *
         * @return mixed
         */
        public function updateRestShopOrderObject($response, $webhook, $request)
        {
            $data = $response->data;
            $data['package'] = self::getPackageFromPayload($data);
            $response->set_data($data);
            return $response;
        }

        /**
         * @param  array  $payload
         *
         * @return array
         */
        public static function updateWebhookPayload(array $payload): array
        {
            if (! self::checkShippingMethod($payload)) {
                return $payload;
            }

            $payload['package'] = self::getPackageFromPayload($payload);
            return $payload;
        }

        /**
         * @param array $http_args request args
         * @param $arg
         * @param $webhook_id
         *
         * @return array
         */
        public static function updateWebhookHttpArgs(array $http_args, $arg, $webhook_id): array
        {
            try {
                $webhook = new \WC_Webhook($webhook_id);

                if (
                    self::checkWebhookDeliveryUrl($webhook->get_delivery_url()) &&
                    self::checkShippingMethod($http_args['body'])
                ) {
                    $http_args['headers'][BeService::HEADER_AUTHORIZATION] = BeService::getAccessToken();
                    $http_args['headers']['instanceId'] = CheckConfig::$tenantId;
                }
            } catch (\Throwable $e) {
                Service::wpLog("ERROR", 'update_webhook_http_args', $e->getMessage());
            }

            return $http_args;
        }

        /**
         * @param string $deliveryUrl
         *
         * @return bool
         */
        public static function checkWebhookDeliveryUrl(string $deliveryUrl): bool
        {
            $createdWebhookUrl =  self::getWebhookDeliveryUrl('created');
            $updatedWebhookUrl = self::getWebhookDeliveryUrl('updated');
            return in_array($deliveryUrl, [$createdWebhookUrl, $updatedWebhookUrl]);
        }

    /**
     * @param array $payload
     *
     * @return array
     */
        public static function getPackageFromPayload(array $payload): array
        {
            $package = [
            'id' => $payload['id'] ,
            'status'          => $payload['status'],
            'webhook_source'  => CheckConfig::$tenantId,
            'view_order_link' => admin_url('post.php?post=' . $payload['id'] . '&action=edit'),
            'destination' => [
                'first_name' => $payload['shipping']['first_name'],
                'last_name'  => $payload['shipping']['last_name'],
                'city'       => $payload['shipping']['city'],
                'state'      => $payload['shipping']['state'],
                'postcode'   => $payload['shipping']['postcode'],
                'address_1'  => $payload['shipping']['address_1'],
                'address_2'  => $payload['shipping']['address_2'],
                'company'    => $payload['shipping']['company'],
                'phone'      => $payload['shipping']['phone'] ??  $payload['billing']['phone'],
                'email'      => $payload['shipping']['email'] ??  $payload['billing']['email']
            ],
            'shipping_total'   => $payload['shipping_total'] ?? 0,
            'shipping_options' => get_post_meta($payload['id'], CheckConfig::$clientId . '_shipping_options', true)
            ];

            if (isset($payload['line_items'])) {
                $contents = self::prepareLineItems($payload['line_items']);
                $package['contents'] = !is_wp_error($contents) ? $contents : $payload['line_items'];
            }

            if (isset($payload['shipping_lines']) && is_array($payload['shipping_lines'])) {
                foreach ($payload['shipping_lines'] as $line) {
                    if ($line['method_id'] === CheckConfig::$clientId) {
                        $external_quote = array_filter($line['meta_data'], function ($item) {
                            return $item['display_key'] === 'external_quote';
                        });
                        $external_data = array_filter($line['meta_data'], function ($item) {
                            return $item['display_key'] === 'external_data';
                        });

                        $external_quote = reset($external_quote);
                        $external_data = reset($external_data);

                        $package['external_data'] = $external_data['display_value'];
                        $package['external_data']['quote'] = $external_quote['display_value'];
                    }
                }
            }

            if (isset($payload['line_items'])) {
                $contents = self::prepareLineItems($payload['line_items']);
                $package['contents'] = !is_wp_error($contents) ? $contents : $payload['line_items'];
            }

            return $package;
        }

    /**
     * @param array $items
     *
     * @return array|WP_Error
     */
        private static function prepareLineItems(array $items): array
        {
            $errors = [];
            $weight_unit    = get_option('woocommerce_weight_unit');
            $dimension_unit = get_option('woocommerce_dimension_unit');
            $dimensions = [
            'length' => [
                'func'      => 'wc_get_dimension',
                'to_unit'   => 'cm',
                'from_unit' => $dimension_unit
            ],
            'width'  => [
                'func'      => 'wc_get_dimension',
                'to_unit'   => 'cm',
                'from_unit' => $dimension_unit
            ],
            'height' => [
                'func'      => 'wc_get_dimension',
                'to_unit'   => 'cm',
                'from_unit' => $dimension_unit
            ],
            'weight' => [
                'func'      => 'wc_get_weight',
                'to_unit'   => 'kg',
                'from_unit' => $weight_unit
            ],
            ];

            $items = array_filter(array_values($items), function ($item) {
                $product  = wc_get_product($item['product_id']);
                return !$product->get_virtual();
            });
            $items = array_map(function ($item) use ($dimensions, &$errors) {
                $product  = wc_get_product($item['product_id']);
                $class_id = $product->get_shipping_class_id();
                $item = [
                'description' => '',
                'quantity' => $item['quantity']
                ];
                if ($class_id) {
                    $term = get_term_by('id', $class_id, 'product_shipping_class');

                    if ($term && ! is_wp_error($term)) {
                        $item['description'] = $term->name;
                    }
                }

                foreach ($dimensions as $dimension => $args) {
                    $func      = 'get_' . $dimension;
                    $converter = $args['func'];
                    $value     = $product->$func();
                    if (!$value) {
                        $errors[] = "Product <b>{$product->get_title()}</b> has no <em>$dimension</em> set.";
                    }
                    $item[$dimension] = $converter($value, $args['to_unit'], $args['from_unit']);
                }

                return $item;
            }, $items);

            foreach ($items as $item) {
                if ($item['quantity'] < 2) {
                    continue;
                }
                foreach (range(1, $item['quantity'] - 1) as $_index) {
                    $items[] = $item;
                }
            }

            if ($errors) {
                $wp_error = new WP_Error();
                foreach ($errors as $message) {
                    $wp_error->add('ERROR', $message);
                }
                return $wp_error;
            }

            return $items;
        }
    /**
     * @param string|array $body
     *
     * @return bool
     */
        public static function checkShippingMethod($body): bool
        {
            if (is_array($body)) {
                $body = json_encode($body);
            }
            $body     = json_decode($body);
            $shipping = $body->shipping_lines[0] ?? [];
            $method   = $shipping->method_id ?? '';
            return $method === CheckConfig::$clientId;
        }

    /**
     * @param string $type
     *
     * @return string
     */
        public static function getWebhookDeliveryUrl(string $type): string
        {
            $deliveryUrl = '';
            foreach (CheckConfig::$webhooks as $value) {
                if (strstr($value['topic'], $type)) {
                    $deliveryUrl = $value['delivery_url'];
                }
            }
            return $deliveryUrl;
        }
        /**
         * @param $order_id
         * @param $data
         *
         * @return void
         */
        public static function updateOrderMeta($order_id, $data): void
        {
            $order = wc_get_order($order_id);
            $shipping_options  = WC()->session->get(CheckConfig::$clientId . '_shipping_options');
            if (!empty($shipping_options)) {
                $order->update_meta_data(CheckConfig::$clientId . '_shipping_options', $shipping_options);
            }

            $order->save();
        }
    }


}
