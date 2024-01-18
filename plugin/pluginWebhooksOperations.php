<?php
namespace proto\plugin;

use proto\App;

/**
 * Function to activate SafeDigit pipeline application
 * @param string $clientId
 * @param string $tenantId
 * @param string $activateWedHookUrl
 *
 * @return void
 */
function activationWebhookOperations(string $clientId, string $tenantId, string $activateWedHookUrl)
{
    if (get_option('_' . $clientId . '_admin_application_timestamp') && '_' . $clientId . '_tenantid') {
        return;
    }
    webhooksOperationsLog('INFO', 'Start to create Application');
    $data     = [
        "tenantId"       => $tenantId,
        "name"           => get_bloginfo("name"),
        "configuration"  => array(
            "restUrl"        => get_rest_url(null, "wc/v3/orders/"),
            "restUrlBatch"   => get_rest_url(null, "wc/v3/orders/batch"),
            "consumerKey"    => get_option('_' . $clientId . '_consumer_key'),
            "consumerSecret" => get_option('_' . $clientId . '_consumer_secret'),
            "secretKey"      => get_option('_' . $clientId . '_consumer_secret_key')
        )

    ];
    $requestData = array(
        'timeout'     => 45,
        'redirection' => 5,
        "headers" => [
            'instanceId' => $tenantId,
            'Content-Type' => 'application/json'
        ],
        "body" => json_encode($data),
    );

    $response = wp_remote_post($activateWedHookUrl, $requestData);

    if (!$response['body'] && $response['response']['code'] === 200 && $response['response']['message'] === 'OK') {
        webhooksOperationsLog('Application has successfully created', $response);
        update_option('_' . $clientId . '_application_created', 'yes');
        update_option('_' . $clientId . '_admin_application_timestamp', time());
    } else {
        update_option('_' . $clientId . '_application_created', 'no');
        webhooksOperationsLog('Something went wrong ', json_decode($response));
    }
}

/**
 * Function to deactivate SafeDigit pipeline application
 * @param string $clientId
 * @param string $tenantId
 * @param string $deactivateWedHookUrl
 *
 * @return void
 */
function deactivationWebhookOperations(string $clientId, string $tenantId, string $deactivateWedHookUrl)
{
    webhooksOperationsLog('INFO', 'Start to remove Application');
    $headers = [
        'instanceId' => $tenantId,
        'Content-Type' => 'application/json',
    ];
    $args = [
        'timeout' => 450,
        'redirection' => 5,
        'headers' => $headers,
        'body' => [],
        'cookies' => [],
    ];

    wp_remote_post($deactivateWedHookUrl, $args);
    webhooksOperationsLog('INFO', 'Application has removed');
}

/**
 * @param string $message
 * @param $data
 *
 * @return void
 */
function webhooksOperationsLog(string $message, $data = ''): void
{
    $logFilePath = App::getPluginPath() . strtolower(App::PLUGIN_NAME) . '-debug.log' ;
    $cont =  '[' . date('d.m.Y h:i:s') . '][' . $message . ']' . json_encode($data) . PHP_EOL;
    file_put_contents($logFilePath, $cont, FILE_APPEND);
}
