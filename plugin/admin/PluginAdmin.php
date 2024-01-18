<?php

namespace proto\plugin\admin;

use proto\App;
use proto\plugin\BeService;
use proto\plugin\CheckConfig;
use proto\plugin\Service;
use proto\plugin\WcPayloadService;

if (! defined('ABSPATH')) {
    exit;
}
if (! class_exists('PluginAdmin')) {
    class PluginAdmin
    {
        public function __construct()
        {
            add_action('admin_menu', [$this, 'addPluginAdminMenu']);
            add_action('admin_action_pluginValidateCredentials', [$this,'pluginValidateCredentials']);

            // Add scripts and styles to admin pages
            add_action('admin_enqueue_scripts', [$this, 'pluginAdminEnqueueStyles']);
            add_action('admin_enqueue_scripts', [$this, 'pluginAdminEnqueueScripts']);
        }

        /**
         * Plugin admin menu / submenu generating
         * @return void
         */
        public function addPluginAdminMenu(): void
        {
            if (empty(CheckConfig::$credentialsObject) || empty(CheckConfig::$pages)) {
                return;
            }

            add_menu_page(
                CheckConfig::$appName,
                CheckConfig::$appName,
                'manage_options',
                CheckConfig::$clientId,
                [$this,'pluginSettingsRender'],
                App::getPluginUrl() . 'assets/images/png/courier-20-20.png',
                65
            );

            foreach (CheckConfig::$pages as $page) {
                if ($page['name'] === 'Settings' && $page['credentials']) {
                    add_submenu_page(
                        CheckConfig::$clientId,
                        'Settings',
                        'Settings',
                        'manage_options',
                        CheckConfig::$clientId,
                        [$this,'pluginSettingsRender']
                    );
                }
                if (
                    $page['name'] === 'Dashboard' &&
                    $page['authorized'] &&
                    get_option('_' . CheckConfig::$clientId . '_application_created') === 'yes'
                ) {
                    add_submenu_page(
                        CheckConfig::$clientId,
                        'Dashboard',
                        'Dashboard',
                        'manage_options',
                        CheckConfig::$clientId . '_dashboard',
                        [$this,'pluginDashboardRender']
                    );
                }
            }
        }

        /**
         * Add plugin styles to his admin menu pages
         * @return void
         */
        public function pluginAdminEnqueueStyles(): void
        {
            if (strstr(get_current_screen()->base, 'page_' . CheckConfig::$clientId)) {
                $pluginUrl = plugin_dir_url(App::PLUGIN_FILE);
                wp_enqueue_style(
                    App::PLUGIN_NAME . '-bootstrap',
                    $pluginUrl .
                    'assets/admin/css/bootstrap.min.css',
                    array(),
                    '5'
                );
                wp_enqueue_style(
                    App::PLUGIN_NAME,
                    $pluginUrl .
                    'assets/admin/css/onequote-admin-styles.css',
                    array(),
                    App::VERSION
                );
            }
        }

        /**
         * Add plugin styles to his admin menu pages
         * @return void
         */
        public function pluginAdminEnqueueScripts(): void
        {
            if (strstr(get_current_screen()->base, 'page_' . CheckConfig::$clientId)) {
                $pluginUrl = plugin_dir_url(App::PLUGIN_FILE);
                wp_enqueue_script(
                    'bootstrap',
                    $pluginUrl .
                    'assets/admin/js/libs/bootstrap.bundle.min.js',
                    array( 'jquery' ),
                    '5'
                );
                wp_enqueue_script(
                    'onequote-scripts',
                    $pluginUrl .
                    'assets/admin/js/onequote-admin-scripts.js',
                    array( 'jquery', 'bootstrap' ),
                    '5',
                    true
                );
            }
        }

        /**
         * Validate Credentials to Client's REST API
         * @return void
         * @throws \Exception
         */
        public function pluginValidateCredentials(): void
        {

            check_admin_referer('pluginValidateCredentials', 'vc_message');

            Service::wpLog('INFO', 'Validate Credentials started');

            CheckConfig::$methodTitle = esc_html($_POST['method_title']);
            $authorizationCode = esc_html($_POST['authorization_code']);

            $credentials     = array(
                'authorization' => $authorizationCode,
                'tenantId' => CheckConfig::$tenantId,
            );

            $args = array(
                'timeout'     => 45,
                'redirection' => 5,
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode($credentials),
            );

            $result = wp_remote_post(
                CheckConfig::$validateCredentialsUrl,
                $args
            );

            if (is_wp_error($result)) {
                update_option('_' . CheckConfig::$clientId . '_authorized', 'no');
                $error_message = $result->get_error_message();
                Service::wpLog('ERROR', 'Error result', $error_message);
            }

            $result = json_decode(wp_remote_retrieve_body($result), true);
            $isValidated = isset($result['validationResult']) && $result['validationResult'];

            Service::wpLog('INFO', 'Validate credentials Result', $result);

            if ($isValidated) {
                CheckConfig::$secretId = $result['referenceId'];
                CheckConfig::$secretKey = $result['secret'];

                update_option('_' . CheckConfig::$clientId . '_authorization_code', $authorizationCode);
                update_option('_' . CheckConfig::$clientId . '_authorized', 'yes');
                update_option('_' . CheckConfig::$clientId . '_secret_id', CheckConfig::$secretId);
                update_option('_' . CheckConfig::$clientId . '_secret_key', CheckConfig::$secretKey);

                $updateConfigurationArray = [
                    "configuration" => [],
                    "data" => [
                        'authorization' => $authorizationCode,
                        'wordpressdata' => Service::getActivePluginsAndThemes()
                    ]
                ];
                Service::updateApplicationConfigurationData($updateConfigurationArray);
                Service::wpLog('INFO', 'Validate credentials - success');
            } else {
                Service::wpLog('ERROR', 'Validate credentials - failure');
                update_option('_' . CheckConfig::$clientId . '_authorized', 'no');
                wp_redirect($_SERVER['HTTP_REFERER']);
                exit;
            }

            wp_redirect($_SERVER['HTTP_REFERER']);
            exit;
        }


        /**
         * Render Plugin admin menu Settings page
         * @return void
         */
        public function pluginSettingsRender()
        {
            include_once 'SettingsPage.php';
        }

        /**
         * Render Plugin admin menu Dashboard page
         * @return void
         */
        public function pluginDashboardRender()
        {
            include_once 'DashboardPage.php';
        }
    }
}
