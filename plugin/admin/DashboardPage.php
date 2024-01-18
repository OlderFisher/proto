<?php
namespace proto\plugin\admin;

use proto\App;
use proto\plugin\BeService;
use proto\plugin\CheckConfig;
use proto\plugin\Service;

$getDashboardPageData = [];
foreach (CheckConfig::$pages as $page) {
    if ($page['name'] === 'Dashboard') {
        $getDashboardPageData = $page;
    }
}

if (empty($getDashboardPageData) || empty($getDashboardPageData['url']) || !Service::isValidUrl($getDashboardPageData['url'])) {
    Service::wpLog('ERROR', "Can't find  page Dashboard or valid Dashboard URL in config file");
    return;
}

if (!get_option('_' . CheckConfig::$clientId . '_authorized') && $getDashboardPageData['authorized']) {
    echo  '<div class="container">
        <div class="text-center mt-5" >
            <h5>' . __('Thanks for installing ' . App::PLUGIN_NAME . ' plugin!', App::PLUGIN_TEXT_DOMAIN) . '</h5>
            <p>' .  __('To start using it please enter ' . App::PLUGIN_NAME . ' authorization code on Settings page', App::PLUGIN_TEXT_DOMAIN) . '</p>
        </div>
    </div>';
}
if (get_option('_' . CheckConfig::$clientId . '_authorized') == 'yes' && $getDashboardPageData['authorized']) {
       $dashboardUrl =  $getDashboardPageData['url'] . "/" . CheckConfig::$clientId .
                        "/dashboard?instance=" . BeService::getAccessToken();

    echo '<iframe id="iframe_dashboard" src="' . $dashboardUrl . '"></iframe>';
}
