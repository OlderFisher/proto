<?php

use proto\App;
use proto\plugin\BeService;
use proto\plugin\CheckConfig;
use proto\plugin\WcCheckoutFormControl;

/**
 * Register new WooCommerce Shipping Method
 * @param $methods
 *
 * @return mixed
 */
function registerShippingMethod($methods)
{
    $methods[ CheckConfig::$clientId ] = '\ProtoShippingMethod';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'registerShippingMethod');

class ProtoShippingMethod extends WC_Shipping_Method
{
    /**
     * Constructor. The instance ID is passed to this.
     */
    public function __construct($instance_id = 0)
    {
        $this->id                    = CheckConfig::$clientId;

        $this->instance_id           = abs($instance_id);
        $this->method_title          = __(CheckConfig::$methodTitle, App::PLUGIN_TEXT_DOMAIN);
        $this->method_description    = __(CheckConfig::$methodDescription, App::PLUGIN_TEXT_DOMAIN);
        $this->title                 = __(CheckConfig::$methodTitle, App::PLUGIN_TEXT_DOMAIN);
        $this->supports              = array(
            'shipping-zones',
        );
    }

    /**
     * @throws Exception
     */
    public function calculate_shipping($package = [])
    {
        CheckConfig::$delivery = self::prepareDelivery($package);

        $quote  = BeService::getQuote(CheckConfig::$delivery);
        if (is_array($quote) && $quote['success']) {
            $parsedQuote = $quote['quote'];
                $rate = [
                    'id'        => $this->id . $this->instance_id,
                    'label'     => !empty($parsedQuote['label']) ? $parsedQuote['label'] : $this->title,
                    'cost'      => $parsedQuote['cost'],
                    'calc_tax'  => 'per_order',
                    'meta_data' => [
                        'external_quote' => $quote['quote'],
                        'external_data'  => $quote['quote'],
                    ],
                ];
        } else {
            $rate = [
                'id'        => $this->id . $this->instance_id,
                'label'     => '',
                'cost'      => '',
                'calc_tax'  => 'per_order',
                'meta_data' => [],
            ];
            $errorsArray = array_map(function ($error) {
                return $error['message'];
            }, $quote['errors']);
        }
        $this->add_rate($rate);
    }

    /**
     * @param $package
     *
     * @return array
     */
    private static function prepareDelivery($package = []): array
    {

        $package['id'] = 1;

        if (
            trim(get_option('woocommerce_ship_to_destination')) === 'billing' ||
            trim(get_option('woocommerce_ship_to_destination')) === 'billing_only'
        ) {
            $billing_params = WC()->customer->get_billing() ;

            $package['destination']['first_name'] = $billing_params['first_name'];
            $package['destination']['last_name']  = $billing_params['last_name'];
            $package['destination']['company']    = $billing_params['company'];
            $package['destination']['address_1']  = $billing_params['address_1'];
            $package['destination']['address_2']  = $billing_params['address_2'];
            $package['destination']['city']       = $billing_params['city'];
            $package['destination']['postcode']   = $billing_params['postcode'];
            $package['destination']['country']    = $billing_params['country'];
            $package['destination']['state']      = $billing_params['state'];
            $package['destination']['email']      = $billing_params['email'];
            $package['destination']['phone']      = WcCheckoutFormControl::$CheckoutFormPhone ?? $billing_params['phone'];
        }

        if (trim(get_option('woocommerce_ship_to_destination')) ===  'shipping') {
            $shipping_params = WC()->customer->get_shipping() ;

            $package['destination']['first_name'] = $shipping_params['first_name'];
            $package['destination']['last_name'] = $shipping_params['last_name'];
            $package['destination']['company']   = $shipping_params['company'];
            $package['destination']['address_1'] = $shipping_params['address_1'];
            $package['destination']['address_2'] = $shipping_params['address_2'];
            $package['destination']['city']      = $shipping_params['city'];
            $package['destination']['postcode']  = $shipping_params['postcode'];
            $package['destination']['country']   = $shipping_params['country'];
            $package['destination']['state']     = $shipping_params['state'];
            $package['destination']['phone']     = WcCheckoutFormControl::$CheckoutFormPhone ?? $shipping_params['phone'];
        }

        if (
            empty($package['destination']['postcode'])
            || empty($package['destination']['city'])
            || empty($package['destination']['state'])
            || empty($package['destination']['phone'])
        ) {
            return [];
        }

        $package['shipping_options'] = self::getShippingOptions($package);

        WC()->session->set(CheckConfig::$clientId . '_shipping_options', $package['shipping_options']);

        return $package;
    }

    /**
     * @param array $package
     *
     * @return array
     */
    private static function getShippingOptions(array $package): array
    {
        return [];
    }
}
