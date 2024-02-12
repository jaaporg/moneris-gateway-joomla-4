<?php

/**
 * @package     EasyStore.Site
 * @subpackage  EasyStore.Moneris
 *
 * @copyright   Copyright (C) 2023 JoomShaper <https://www.joomshaper.com>. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace JoomShaper\Plugin\EasyStore\Moneris\Utils;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\PluginHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Class that contains constants for the Paddle payment gateway.
 * @since 1.0.0
 */
class MonerisConstants
{
    public $plugin;

    public $ticket;

    public $pluginParams;

    /**
     * Constant values for payment settings.
     */
    const TEST_DISPLAY_URL = 'https://gatewayt.moneris.com/chktv2/display/index.php';
    const LIVE_DISPLAY_URL = 'https://gateway.moneris.com/chktv2/display/index.php';

    const TEST_PRELOAD_URL = 'https://gatewayt.moneris.com/chktv2/request/request.php';
    const LIVE_PRELOAD_URL = 'https://gateway.moneris.com/chktv2/request/request.php';

    const MONERIS_TEST_JS = '<script src="https://gatewayt.moneris.com/chktv2/js/chkt_v2.00.js">';
    const MONERIS_LIVE_JS = '<script src="https://gateway.moneris.com/chktv2/js/chkt_v2.00.js">';

    /**
     * The constructor method.
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $this->plugin       = PluginHelper::getPlugin('easystore', 'moneris');
        $this->pluginParams = new Registry($this->plugin->params);
    }

    public function getDisplayUrl(){
        if($this->pluginParams->get('payment_environment') == 'test') {
            return self::TEST_DISPLAY_URL;
        }

        return self::LIVE_DISPLAY_URL;
    }

    public function getPreloadUrl(){
        if($this->pluginParams->get('payment_environment') == 'test') {
            return self::TEST_PRELOAD_URL;
        }

        return self::LIVE_PRELOAD_URL;
    }

    public function getStoreId() {
        if($this->pluginParams->get('payment_environment') == 'test') {
            return $this->pluginParams->get('test_store_id');
        }

        return $this->pluginParams->get('live_store_id');
    }

    public function getApiToken() {
        if($this->pluginParams->get('payment_environment') == 'test') {
            return $this->pluginParams->get('test_api_token');
        }

        return $this->pluginParams->get('live_api_token');
    }

    public function getCheckoutId() {
        return $this->pluginParams->get('checkout_id');
    }

    public function getNotifyUrl() {
        return Route::_(Uri::base() . 'index.php?option=com_easystore&task=payment.onPaymentNotify&type=moneris');
    }

    public function getReturnUrl() {
        return Route::_(Uri::base() . 'index.php?option=com_easystore&task=payment.onPaymentSuccess');
    }

    public function getCancelUrl() {
        return Route::_(Uri::base() . 'index.php?option=com_easystore&task=payment.onPaymentCancel');
    }

    public function getEnvironment() {
        if($this->pluginParams->get('payment_environment') == 'test') {
            return 'test';
        };
        return '';
    }

    public function setTicket($ticket) {
        return $this->ticket = $ticket;
    }

    public function getTicket() {
        return $this->ticket;
    }
}
