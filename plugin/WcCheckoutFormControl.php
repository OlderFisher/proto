<?php

namespace proto\plugin;

if (! defined('ABSPATH')) {
    exit;
}
if (! class_exists('WcCheckoutFormControl')) {
    /**
     * Class WcCheckoutFormControl
     */
    class WcCheckoutFormControl
    {
        public static string $CheckoutFormPhone;
        public function __construct()
        {
            add_filter('woocommerce_checkout_fields', [__CLASS__, 'pluginCheckoutFieldsTriggerRefresh'], 9999);
            add_action('woocommerce_checkout_update_order_review', [__CLASS__, 'pluginCheckoutUpdateValidation']);
            add_filter('woocommerce_cart_shipping_packages', [__CLASS__,'addDataToDestinationShippingPackage']);
        }

        /**
         * @param $fields
         *
         * @return void
         */
        public static function pluginCheckoutFieldsTriggerRefresh(array $fields): array
        {
            $fields['billing']['billing_phone']['class'][] = 'update_totals_on_change';

            return $fields;
        }
        /**
         * Data validation after Checkout form input fields updated
         * @throws \Exception
         */
        public static function pluginCheckoutUpdateValidation($posted_data)
        {
            parse_str($posted_data, $parsedCheckoutFormData);
            $validationCheckoutData = self::getCheckoutDataToValidate($parsedCheckoutFormData);
            self::$CheckoutFormPhone = $validationCheckoutData['destination']['phone'];
        }

        /**
         * Prepare updated Checkout form data for validation ( get quote )
         * @param array $parsedCheckoutFormData
         *
         * @return array
         */
        public static function getCheckoutDataToValidate(array $parsedCheckoutFormData): array
        {
            $delivery['id'] = 1;

            if (
                trim(get_option('woocommerce_ship_to_destination')) === 'billing' ||
                trim(get_option('woocommerce_ship_to_destination')) === 'billing_only'
            ) {
                $delivery['destination']['first_name'] = $parsedCheckoutFormData['billing_first_name'];
                $delivery['destination']['last_name'] = $parsedCheckoutFormData['billing_last_name'];
                $delivery['destination']['address_1'] = $parsedCheckoutFormData['billing_address_1'];
                $delivery['destination']['address_2'] = $parsedCheckoutFormData['billing_address_2'];
                $delivery['destination']['city']      = $parsedCheckoutFormData['billing_city'];
                $delivery['destination']['postcode']  = $parsedCheckoutFormData['billing_postcode'];
                $delivery['destination']['state']     = $parsedCheckoutFormData['billing_state'];
                $delivery['destination']['phone']     = self::$CheckoutFormPhone ?? $parsedCheckoutFormData['billing_phone'];
            }

            if (trim(get_option('woocommerce_ship_to_destination')) ===  'shipping') {
                $delivery['destination']['first_name'] = $parsedCheckoutFormData['shipping_first_name'];
                $delivery['destination']['last_name'] = $parsedCheckoutFormData['shipping_last_name'];
                $delivery['destination']['address_1'] = $parsedCheckoutFormData['shipping_address_1'];
                $delivery['destination']['address_2'] = $parsedCheckoutFormData['shipping_address_2'];
                $delivery['destination']['city']      = $parsedCheckoutFormData['shipping_city'];
                $delivery['destination']['postcode']  = $parsedCheckoutFormData['shipping_postcode'];
                $delivery['destination']['state']     = $parsedCheckoutFormData['shipping_state'];
                $delivery['destination']['phone']     = self::$CheckoutFormPhone ?? $parsedCheckoutFormData['shipping_phone'];
            }
            return $delivery;
        }

        /**
         * @param $packages
         *
         * @return array
         */
        public static function addDataToDestinationShippingPackage($packages): array
        {
            if (isset(self::$CheckoutFormPhone)) {
                foreach ($packages as $key => $package) {
                    $packages[ $key ]['destination']['phone'] = self::$CheckoutFormPhone;
                }
            }
            return $packages;
        }
    }
}
