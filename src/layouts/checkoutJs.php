<?php

/**
 * @package     EasyStore.Site
 * @subpackage  EasyStore.Paddle
 *
 * @copyright   Copyright (C) 2024 japporg <https://www.japporg.com>. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use JoomShaper\Component\EasyStore\Site\Helper\EasyStoreHelper;
use JoomShaper\Plugin\EasyStore\Moneris\Utils\MonerisConstants;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

extract($displayData);
$constants = new MonerisConstants();
$completeUrl = $constants->getWebHookUrl();
$cancelUrl = $constants->getCancelUrl($orderId);
$environment = $constants->getEnvironment();

$inlineJS = <<<JS
    document.addEventListener('DOMContentLoaded' , () => {
        document.body.innerHTML += '<div id="monerisCheckout"></div>';
        $("#sp-bottom").hide()
        var myCheckout = new monerisCheckout();
        myCheckout.setMode("$environment");
        myCheckout.setCheckoutDiv("monerisCheckout");

        var myPageLoad = function(data) {
            console.log("Success");
        };

        var myCancelTransaction = function(data) {
            console.log("myCancelTransaction");
            const obj = JSON.parse(data);
            setTimeout(function() {
                myCheckout.closeCheckout(obj.ticket);
                window.location.replace("$cancelUrl");
            }, 1000)
        };

        var myErrorEvent = function(data) {
            console.log("myErrorEvent");
            const obj = JSON.parse(data);
            setTimeout(function() {
                myCheckout.closeCheckout(obj.ticket);
                window.location.replace("$cancelUrl");
            }, 1000)
        };

        var myPaymentReceipt = function(data) {
            const obj = JSON.parse(data);
            setTimeout(function() {
                myCheckout.closeCheckout(obj.ticket);
                window.location.replace("$completeUrl&ticket="+obj.ticket);
            }, 1000)
        };

        var myPaymentComplete = function(data) {
            const obj = JSON.parse(data);

            setTimeout(function() {
                myCheckout.closeCheckout(obj.ticket);
                window.location.replace("$completeUrl&ticket="+obj.ticket);
            }, 1000)
        };

        /**
         * Set callbacks in JavaScript:
         */
        myCheckout.setCallback("page_loaded", myPageLoad);
        myCheckout.setCallback("cancel_transaction", myCancelTransaction);
        myCheckout.setCallback("error_event", myErrorEvent);
        myCheckout.setCallback("payment_receipt", myPaymentReceipt);
        myCheckout.setCallback("payment_complete", myPaymentComplete);

        myCheckout.startCheckout("{$ticket}");
    })
JS;

$wa = EasyStoreHelper::wa();

// Add script to the document head.

$wa->registerAndUseScript('moneris', $constants->getScript(), [], ['type' => 'text/javascript']);
$wa->addInlineScript($inlineJS);