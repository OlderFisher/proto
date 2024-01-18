<?php
/**
 * Plugin Name:       Proto
 * Plugin URI:        www.com.io
 * Description:       Proto plugin
 * Version:           1.0.0
 * Author:
 * Author URI:
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       proto
 * Domain Path:       /languages
 */

namespace proto;

if (!defined('WPINC')) {
    die;
}

if (version_compare('7.4.0', PHP_VERSION, '>')) {
    die(sprintf('We are sorry, but you need to have at least PHP 7.4.0 to run this plugin
    (currently installed version: %s)'
                . ' - please upgrade or contact your system administrator.', PHP_VERSION));
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (!is_plugin_active('woocommerce/woocommerce.php')) {
    die(__('We are sorry, but you need to have activated WooCommerce plugin to use onequote plugin', 'onequote'));
}

if (class_exists('safedigit\onequote\App')) {
    die('Plugin is already activated.');
}

require_once plugin_dir_path(__FILE__) . 'plugin/Psr4AutoloaderClass.php';
$loader = new plugin\Psr4AutoloaderClass();
$loader->register();
$loader->addNamespace(__NAMESPACE__, untrailingslashit(plugin_dir_path(__FILE__)));


/**
 *  Application class
 */
class App extends plugin\PluginAbstract
{
    /**
     * Plugin version
     */
    public const VERSION = '1.0.0';
    /**
     * Plugin name
     */
    public const PLUGIN_NAME = 'OneQuote';
    /**
     * Plugin name extended
     */
    public const PLUGIN_NAME_EXTENDED = 'Safedigit  OneQuote';
    /**
     * Plugin file
     */
    public const PLUGIN_FILE = __FILE__;
    /**
     * Plugin text domain
     */
    public const PLUGIN_TEXT_DOMAIN = 'onequote';

    /**
     * SafeDigit API url
     */
    public const API_URL = '';
    /**
     * SafeDigit Backend for frontend url
     */
    public const BFF_URL = '';

    /**
     * Get Plugin folder URL
     * @return string
     */
    public static function getPluginUrl(): string
    {
        return plugin_dir_url(__FILE__);
    }

    /**
     * Get Plugin server path
     * @return string
     */
    public static function getPluginPath(): string
    {
        return plugin_dir_path(__FILE__);
    }
}

new App();

register_activation_hook(__FILE__, ['safedigit\onequote\App','pluginActivation']);