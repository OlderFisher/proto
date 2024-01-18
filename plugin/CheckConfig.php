<?php

namespace proto\plugin;

use proto\App;

/**
 * CheckConfig Class is wrapper for config file
 */
class CheckConfig
{
    private static $instance = null;

    public static string $appName;
    public static string $clientId;

    public static string $apiUrl;
    public static string $bffUrl;
    public static string $tokenUrl;
    public static string $secretId;
    public static string $secretKey;

    public static string $accessToken;

    public static string $activateWebhookUrl;
    public static string $deactivateWebhookUrl;

    public static string $updateConfigurationUrl;
    public static string $validateCredentialsUrl;
    public static string $updateCredentialsUrl;
    public static string $quotesUrl;
    public static string $methodId;
    public static string $methodTitle;
    public static string $methodDescription;
    public static string $WcRestApiTitle;
    public static array $credentialsObject;
    public static array $pages;
    public static array $webhooks;
    public static string $tenantId;

    public static array $delivery=[];


    /**
     * CheckConfig Class constructor
     */
    private function __construct()
    {
        $configArray = self::getConfigToArray() ;
//  General Settings.
        self::$appName = $configArray['appName'];
        self::$clientId = $configArray['clientId'];
//  Urls Settings
        self::$apiUrl = App::API_URL;
        self::$tokenUrl = $configArray['appUrls']['tokenUrl'];
        self::$validateCredentialsUrl = $configArray['shippingMethodSettingsPage']['validateCredentialsUrl'];
        self::$updateCredentialsUrl = $configArray['shippingMethodSettingsPage']['updateCredentialsUrl'];
        self::$quotesUrl = $configArray['appUrls']['quotesUrl'];
        self::$activateWebhookUrl = $configArray['appUrls']['activateWebhookUrl'];
        self::$deactivateWebhookUrl = $configArray['appUrls']['deactivateWebhookUrl'];
        self::$updateConfigurationUrl = $configArray['appUrls']['updateConfigurationUrl'];
//  Shipping Method Settings
        self::$methodId = $configArray['shippingMethodSettingsPage']['methodId'];
        self::$methodTitle = $configArray['shippingMethodSettingsPage']['defaultMethodTitle'];
        self::$methodDescription = $configArray['shippingMethodSettingsPage']['defaultMethodDescription'];
//  Shipping Method Form Fields Settings
        self::$credentialsObject = $configArray['shippingMethodSettingsPage']['credentialsObject'];
//  Admin Pages Settings
        self::$pages = $configArray['pages'];
        self::$WcRestApiTitle = trim($configArray['appName']) . ' REST API';
        self::$webhooks = $configArray['webhooks'];
        self::$tenantId = self::createTenantId();
    }

    /**
     * Get config file and put into array
     * @return array
     */
    private static function getConfigToArray(): array
    {
        $dir = untrailingslashit(dirname(plugin_dir_path(__FILE__)));
        $configData = file_get_contents($dir . '/config.json');
        if (!$configData) {
            die("Can't read or find config file");
        }
        return json_decode($configData, true);
    }

    /**
     * Create and return tenantId
     * @return string
     */
    private static function createTenantId(): string
    {
        $tenantId = get_option('_' . self::$clientId . '_tenantid');
        if (!$tenantId) {
            $tenantId = wp_generate_uuid4();
            $tenantId = parse_url(home_url())['host'] . '-' . self::$appName . '-' . $tenantId ;
            update_option('_' . self::$clientId . '_tenantid', $tenantId);
        }
        return $tenantId;
    }

    /**
     * Return all current Config data
     * @return array
     */
    public static function getAllCheckConfigData(): array
    {
        return [
            'appName' => self::$appName,
            'clientId' => self::$clientId,
            'apiUrl' => self::$apiUrl,
            'tokenUrl' => self::$tokenUrl,
            'validateCredentialsUrl' => self::$validateCredentialsUrl,
            'updateCredentialsUrl' => self::$updateCredentialsUrl,
            'quotesUrl' => self::$quotesUrl,
            'activateWebhookUrl' => self::$activateWebhookUrl,
            'deactivateWebhookUrl' => self::$deactivateWebhookUrl,
            'methodId' => self::$methodId,
            'methodTitle' => self::$methodTitle,
            'methodDescription' => self::$methodDescription,
            'credentialsObject' => self::$credentialsObject,
            'pages' => self::$pages,
            'WcRestApiTitle' => self::$WcRestApiTitle,
            'webhooks' => self::$webhooks,
            'tenantId' => self::$tenantId
        ];
    }

    /**
     * @return void
     */
    protected function __clone()
    {
    }

    /**
     * @return self|null
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return void
     */
    public function import()
    {
    }

    /**
     * @return void
     */
    public function get()
    {
    }
}
