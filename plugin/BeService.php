<?php

namespace proto\plugin;

if (! defined("ABSPATH")) {
    exit;
}
class BeService
{
    const HEADER_AUTHORIZATION = "Authorization";

    private static string $accessToken;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        self::$accessToken = false;
    }

    /**
     * @param string $url
     * @param array|null $body
     * @param array $headers
     * @param bool $is_retry
     *
     * @return false|string
     * @throws \Exception
     */


    public static function sendPost(string $url, ?array $body = array(), array $headers = array(), bool $is_retry = false)
    {
        return self::apiCall($url, "POST", $body, $headers, $is_retry);
    }

    /**
     * @param string $url
     * @param array|null $body
     * @param array $headers
     *
     * @return false|string
     * @throws \Exception
     */
    public static function sendPut(string $url, ?array $body = array(), array $headers = array())
    {
        return self::apiCall($url, "PUT", $body, $headers);
    }

    /**
     * @param string $url
     * @param array|null $body
     * @param array $headers
     *
     * @return false|string
     * @throws \Exception
     */
    public static function sendGet(string $url, ?array $body = array(), array $headers = array())
    {
        return self::apiCall($url, "GET", $body, $headers);
    }

    /**
     * @param string $url
     * @param string $method
     * @param array|null $body
     * @param array $headers
     * @param bool $is_retry
     *
     * @return false|string
     * @throws \Exception
     */
    protected static function apiCall(
        string $url,
        string $method = "GET",
        ?array $body = null,
        array $headers = [],
        bool $is_retry = false
    ) {
        Service::wpLog("INFO", "[{$method}] API URL:", $url);

        if (! self::$accessToken) {
            self::$accessToken = self::getAccessToken();
        }
        $data = [
            "method"  => $method,
            "headers" => self::updateHeaders($headers),
            "timeout" => 1000
        ];

        Service::wpLog("INFO", "Request Data:", $data);

        if ($body) {
            if ($method === 'GET') {
                $url = add_query_arg($body, $url);
            } else {
                $data["body"] = json_encode($body);
            }
        }

        Service::wpLog("INFO", "[$method] wp_remote_post ", $data);

        $response = wp_remote_request($url, $data);

        if (is_wp_error($response)) {
            $errorResponse = $response->get_error_message();
            Service::wpLog("ERROR", $method, $errorResponse);

            return false;
        }

        $responseData = wp_remote_retrieve_body($response);
        $status_code  = wp_remote_retrieve_response_code($response);

        if ($status_code === 401 && ! $is_retry) {
            Service::wpLog("INFO", "BEService: Retry with new token");
            self::$accessToken = null;
            return self::apiCall($url, $method, $body, $headers, true);
        } elseif ($status_code != 200) {
            Service::wpLog("ERROR", "[$method] Something went wrong while calling the BE");
            Service::wpLog("ERROR", "[$method] Status code: " . $status_code, $responseData);

            return false;
        }

        if (
            isset($data["headers"]["Content-Type"]) && strpos(
                $data["headers"]["Content-Type"],
                "application/json"
            ) === 0
        ) {
            $responseData = json_decode($responseData, true);
        }

        Service::wpLog("INFO", "[$method] API success. Response: ", $responseData);

        return $responseData;
    }

    /**
     * Update headers before request
     * @param array $headers
     *
     * @return array
     * @throws \Exception
     */
    private static function updateHeaders(array $headers = array()): array
    {
        $headers = array_merge([
            self::HEADER_AUTHORIZATION => self::getAccessToken(),
            'instanceId' => CheckConfig::$tenantId,
            'Content-Type' => 'application/json'
        ], $headers);

        if ($headers[ self::HEADER_AUTHORIZATION ] === false) {
            unset($headers[ self::HEADER_AUTHORIZATION ]);
        }

        return $headers;
    }


    /**
     * Return access token to SafeDigit pipeline
     * @return string
     * @throws \Exception
     */
    public static function getAccessToken(): string
    {
        $secretId  = get_option('_' . CheckConfig::$clientId . '_secret_id');
        $secretKey = get_option('_' . CheckConfig::$clientId . '_secret_key');

        if (! $secretId || ! $secretKey) {
            Service::wpLog('ERROR', "No SECRET_ID or SECRET_KEY provided.");
            return false;
        }

        Service::wpLog("INFO", 'Start to get token');

        $newAccessToken = false;
        try {
            $args = array(
                'timeout'     => 45,
                'redirection' => 5,
                'headers' => [
                    'tenantId' => CheckConfig::$tenantId,
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode([
                    "id"     => $secretId,
                    "secret" => $secretKey
                ]),
            );
            $response = wp_remote_post(
                CheckConfig::$tokenUrl,
                $args
            );
            $body = wp_remote_retrieve_body($response);
            $apiResponse = json_decode($body, true);
            Service::wpLog("INFO", 'Access token API response ', $apiResponse);

            if ($apiResponse && $apiResponse['token']) {
                $newAccessToken = $apiResponse["token"];
            }
        } catch (\Throwable $e) {
            Service::wpLog("ERROR", $e->getMessage());
        }

        return $newAccessToken;
    }

    /**
     * Get delivery  quote from client's API
     * @param array $delivery
     *
     * @return false|string
     * @throws \Exception
     */
    public static function getQuote(array $delivery)
    {
        $key = '_' . CheckConfig::$clientId . '_quote_' . md5(serialize($delivery));
        $quote = get_transient($key) ;

        if (!$quote) {
            $quote = self::sendPost(CheckConfig::$quotesUrl, $delivery);
            if (is_array($quote) && $quote['success']) {
                set_transient($key, $quote, MINUTE_IN_SECONDS * 10);
            }
        }
        Service::wpLog('INFO', 'Quote', $quote);

        return $quote;
    }
}
