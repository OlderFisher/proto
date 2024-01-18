<?php

namespace proto\plugin;

use proto\App;
use proto\plugin\admin\PluginAdmin;

use function proto\plugin\createWcConfigWebhooks;
use function proto\plugin\removeWcConfigWebhooks;
use function proto\plugin\createWcRestApi;
use function proto\plugin\removeWcRestApi;

/**
 *  Abstract Class PluginAbstract
 */
abstract class PluginAbstract
{
    /**
     * Plugin version
     */
    public const VERSION = null;
    /**
     *Plugin prefix
     */
    public const PREFIX = null;
    /**
     * Plugin slug
     */
    public const SLUG = null;
    /**
     * Plugin name
     */
    public const PLUGIN_NAME = null;
    /**
     * Plugin file
     */
    public const PLUGIN_FILE = null;
    /**
     * Plugin name extended
     */
    public const PLUGIN_NAME_EXTENDED = null;

    /**
     * Application name
     * @var string
     */
    private static string $appName;
    /**
     * Client Id
     * @var string
     */
    private static string $clientId;
    /**
     * Woocommerce REST API plugin title
     * @var string
     */
    private static string $WcRestApiTitle;
    /**
     * Woocommerce Webhooks Array
     * @var array
     */
    private static array $wcWebhooks;
    /**
     * WebHook URL for  application activation
     * @var string
     */
    private static string $activateWebhookUrl;


    /**
     * Class constructor
     */
    public function __construct()
    {
        add_action('activated_plugin', [$this, 'pluginActivatedWebHook'], 10, 2);
        add_action('deactivated_plugin', [$this, 'pluginDeactivatedWebHook'], 10, 2);
        register_deactivation_hook(static::PLUGIN_FILE, [$this, 'pluginDeactivation']);

        add_action('plugins_loaded', function () {
            if (!class_exists('proto\App')) {
                return;
            }
            CheckConfig::getInstance();
            Service::getInstance();
            new PluginAdmin();
            new WcCheckoutFormControl();
            new WcPayloadService();

            if (get_option('_' . CheckConfig::$clientId . '_authorized') ==  'yes') {
                new BeService();
                include_once 'ShippingMethod.php';
            }
        });
    }

    /**
     * Plugin activation callback function
     * @throws \Exception
     */
    public static function pluginActivation()
    {
        // Set activation log record
        self::setActivationLog();

        // Create Woocommerce REST API Service and Webhooks
        self::$clientId = trim(self::getConfigParamByKey('clientId'));
        self::$appName = trim(self::getConfigParamByKey('appName'));
        self::$WcRestApiTitle = self::$appName . ' REST API';
        self::$wcWebhooks = self::getConfigParamByKey('webhooks');
        self::$activateWebhookUrl = self::getConfigParamByKey('activateWebhookUrl');

        include_once 'WcAPIService.php';
        createWcRestApi(
            self::$clientId,
            self::$WcRestApiTitle
        );
        createWcConfigWebhooks(
            self::$appName,
            self::$clientId,
            self::$wcWebhooks
        );

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation callback function
     * @return void
     */
    public function pluginDeactivation(): void
    {
        // Remove Woocommerce REST API Service and Webhooks
        include_once 'WcAPIService.php';
        removeWcRestApi(CheckConfig::$WcRestApiTitle);
        removeWcConfigWebhooks(CheckConfig::$webhooks);

        Service::wpLog(' Plugin has deactivated --------------------->');
        flush_rewrite_rules();
    }


    /**
     * Call the webhook after plugin deactivation
     * @param $plugin
     * @param $network_wide
     * @return void
     */
    public function pluginActivatedWebHook($plugin, $network_wide)
    {
        include_once 'pluginWebhooksOperations.php';
        self::$clientId = trim(self::getConfigParamByKey('clientId'));
        self::$appName = trim(self::getConfigParamByKey('appName'));
        self::$activateWebhookUrl = trim(self::getConfigParamByKey('activateWebhookUrl'));

        activationWebhookOperations(
            self::$clientId,
            self::getTenantId(
                self::$clientId,
                self::$appName
            ),
            self::$activateWebhookUrl
        );
    }
    /**
     * Call the webhook after plugin deactivation
     * @param $plugin
     * @param $network_wide
     *
     * @return void
     */
    public function pluginDeactivatedWebHook($plugin, $network_wide)
    {
        include_once 'pluginWebhooksOperations.php';
        deactivationWebhookOperations(
            CheckConfig::$clientId,
            CheckConfig::$tenantId,
            CheckConfig::$deactivateWebhookUrl
        );
        delete_option('_' . CheckConfig::$clientId . '_admin_application_timestamp');
        delete_option('_' . CheckConfig::$clientId . '_application_created');
        delete_option('_' . CheckConfig::$clientId . '_consumer_key');
        delete_option('_' . CheckConfig::$clientId . '_consumer_secret');
        delete_option('_' . CheckConfig::$clientId . '_consumer_secret_key');
        delete_option('_' . CheckConfig::$clientId . '_authorization_code');
        delete_option('_' . CheckConfig::$clientId . '_authorized');
        delete_option('_' . CheckConfig::$clientId . '_secret_id');
        delete_option('_' . CheckConfig::$clientId . '_secret_key');
    }


    /**
     * Just set plugin's activation log into log file
     * @return void
     */
    private static function setActivationLog(): void
    {
        $logFilePath = App::getPluginPath() . strtolower(App::PLUGIN_NAME) . '-debug.log' ;
        $cont =  '[' . date('d.m.Y h:i:s') . '][ Plugin has activated ----------------------->]' . PHP_EOL;
        file_put_contents($logFilePath, $cont, FILE_APPEND);
    }

    /**
     * Function To get parameter value from config file during plugin activation
     * @param string $key
     *
     * @return mixed|void
     */
    private static function getConfigParamByKey(string $key)
    {
        $configData = file_get_contents(App::getPluginPath() . '/config.json');
        if (!$configData) {
            die("Can't read or find config file");
        }
        $dataArray = json_decode($configData, true);

        if ($key === 'activateWebhookUrl' || $key === 'deactivateWebhookUrl') {
            return $dataArray['appUrls'][$key];
        }
        return $dataArray[$key];
    }

    /**
     * Function to get tenantId during plugin activation
     * @param string $clientId
     * @param string $appName
     *
     * @return string
     */
    private static function getTenantId(string $clientId, string $appName): string
    {
        $tenantId = get_option('_' . $clientId . '_tenantid');
        if (!$tenantId) {
            $tenantId = wp_generate_uuid4();
            $tenantId = parse_url(home_url())['host'] . '-' . $clientId . '-' . $tenantId ;
            update_option('_' . $clientId . '_tenantid', $tenantId);
        }
        return $tenantId;
    }
}
