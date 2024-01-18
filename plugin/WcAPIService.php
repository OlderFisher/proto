<?php
namespace proto\plugin;

/**
 * Create Woocommerce REST API for Plugin
 *
 * @param string $clientId
 * @param string $restApiTitle
 *
 * @return void
 */
function createWcRestApi(string $clientId, string $restApiTitle)
{
    global $wpdb;

    $apiKeys = $wpdb->get_results($wpdb->prepare(
        "
		SELECT *
		FROM {$wpdb->prefix}woocommerce_api_keys
		WHERE description = %s;
		",
        $restApiTitle
    ));

    if (! empty($apiKeys)) {
        return;
    }

    $consumerKey    = 'ck_' . wc_rand_hash();
    $consumerSecret = 'cs_' . wc_rand_hash();

    update_option('_' . $clientId . '_consumer_key', $consumerKey);
    update_option('_' . $clientId . '_consumer_secret', $consumerSecret);

    $userId = get_current_user_id();
    $description = $restApiTitle;
    $permissions = 'read_write';

    $data = array(
        'user_id'         => $userId,
        'description'     => $description,
        'permissions'     => $permissions,
        'consumer_key'    => wc_api_hash($consumerKey),
        'consumer_secret' => $consumerSecret,
        'truncated_key'   => substr($consumerKey, -7),
    );

    $data = $wpdb->insert(
        $wpdb->prefix . 'woocommerce_api_keys',
        $data,
        array(
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        )
    );
}


/**
 * Create WooCommerce webhooks for Plugin
 *
 * @param string $appName
 * @param string $clientId
 * @param array $wcWebhooks
 *
 * @return void
 */
function createWcConfigWebhooks(string $appName, string $clientId, array $wcWebhooks): void
{

    $webHookNameToSearch = $wcWebhooks[0]['name'];

    // Check for current webhooks.
    $dataStore = \WC_Data_Store::load("webhook");
    $foundWebhooks   = $dataStore->search_webhooks([ "search" => $webHookNameToSearch ]);

    if (!empty($foundWebhooks)) {
        return;
    }
    $webhooks = $wcWebhooks;

    $userID     = get_current_user_id();
    $secretKey = \wp_generate_password(24);
    update_option('_' . $clientId . '_consumer_secret_key', $secretKey);

    foreach ($webhooks as $webhook) {
        $webhookName = str_replace('{appName}', $appName, $webhook['name']);

        $webhookObj = new \WC_Webhook();
        $webhookObj->set_user_id($userID);
        $webhookObj->set_topic($webhook['topic']);
        $webhookObj->set_status("active");
        $webhookObj->set_name($webhookName);
        $webhookObj->set_secret($secretKey);
        $webhookObj->set_delivery_url($webhook['delivery_url']);
        $webhookObj->save();
    }
}

/**
 * Remove Woocommerce REST API for Plugin
 *
 * @param string $restApiTitle
 *
 * @return void
 */
function removeWcRestApi(string $restApiTitle): void
{
    global $wpdb;
    $apiKeys = $wpdb->get_results($wpdb->prepare(
        "
		SELECT *
		FROM {$wpdb->prefix}woocommerce_api_keys
		WHERE description = %s;
		",
        $restApiTitle
    ));

    if (! empty($apiKeys)) {
        $wpdb->delete($wpdb->prefix . "woocommerce_api_keys", ['description' => $restApiTitle]);
    }
}

/**
 * Remove WooCommerce webhooks for Plugin
 *
 * @param array $wcWebhooks
 *
 * @return void
 */
function removeWcConfigWebhooks(array $wcWebhooks): void
{
    foreach ($wcWebhooks as $wcWebhook) {
        $data_store = \WC_Data_Store::load("webhook");
        $webhooks   = $data_store->search_webhooks([ "search" => $wcWebhook['name'] ]);

        if ($webhooks) {
            $webhook = \wc_get_webhook($webhooks[0]);
            $webhook->delete(true);
        }
    }
}


