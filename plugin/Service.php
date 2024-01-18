<?php
namespace proto\plugin;

use proto\App;

if (! class_exists('Service')) {
    /**
     * Class Service
     */
    class Service
    {
        /**
         * @var null
         */
        private static $_instance = null;
        /**
         * @var string
         */
        private static $logFilePath ;

        /**
         *
         */
        private function __construct()
        {
            self::$logFilePath = App::getPluginPath() . strtolower(App::PLUGIN_NAME) . '-debug.log' ;
        }

        /**
         * Place log messages into log file
         * @param string $type
         * @param string $message
         * @param $var
         *
         * @return void
         */
        public static function wpLog(string $type = '', string $message = '', $var = null)
        {

            if ($var !== null) {
                $message .= ' - ';
                if (is_array($var)) {
                    $message .= str_replace(array("\n", '  '), array('', ' '), var_export($var, true));
                } elseif (is_object($var)) {
                    $message .= str_replace(array('":', ',', '"'), array(' => ', ', ', ''), json_encode($var, true));
                } elseif (is_bool($var)) {
                    $message .= $var ? 'TRUE' : 'FALSE';
                } else {
                    $message .= $var;
                }
            }
            $log_message = sprintf("[%s][%s] %s\n", date('d.m.Y h:i:s'), $type, $message);
            error_log($log_message, 3, self::$logFilePath);
        }

        /**
         * @return void
         */
        public static function getActivePluginsAndThemes(): array
        {
            if (! function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugins = get_plugins();
            $activePluginsList = [];
            foreach ($plugins as $name => $plugin) {
                if (is_plugin_active($name)) {
                    $activePluginsList[] = $plugin['Name'] . ', version ' . $plugin['Version'];
                }
            }
            $activeTheme = wp_get_theme();
            $activeThemeData =  $activeTheme->get('Name') . ', version ' . $activeTheme->get('Version');

            return [
                'php' => phpversion(),
                'wordpress' => get_bloginfo('version'),
                'plugins' => $activePluginsList,
                'theme' => $activeThemeData
            ];
        }

        /**
         * @param string $authorization
         *
         * @return void
         * @throws \Exception
         */
        public static function updateApplicationConfigurationData(array $updateConfigurationArray): void
        {
            $beService = new BeService();
            $body = $updateConfigurationArray;
            $result = $beService::sendPut(
                CheckConfig::$updateConfigurationUrl,
                $body,
            );
        }


        /**
         * Return Url validation result
         * @param $url
         *
         * @return false|int
         */
        public static function isValidUrl($url)
        {
            return preg_match('%^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu', $url);
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
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
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
}
