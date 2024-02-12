<?php

/**
 * @package     EasyStore.Site
 * @subpackage  EasyStore.Paddle
 *
 * @copyright   Copyright (C) 2023 JoomShaper <https://www.joomshaper.com>. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use JoomShaper\Component\EasyStore\Site\Helper\EasyStoreHelper;
use JoomShaper\Plugin\EasyStore\Moneris\Utils\MonerisConstants;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

extract($displayData);

$monerisConstants = new MonerisConstants();
$environment    = $monerisConstants->getEnvironment();

$inlineJS = <<<JS
    document.addEventListener('DOMContentLoaded' , () => {

        document.body.innerHTML += '<div id="monerisCheckout"></div>';

        var myCheckout = new monerisCheckout();
        myCheckout.setMode("qa");
        myCheckout.setCheckoutDiv("monerisCheckout");

        var myPageLoad = function(data) {
            console.log(data);
            const obj = JSON.parse(data);
            console.log(obj.ticket);
        };

        var myCancelTransaction = function(data) {
            console.log(data);
            const obj = JSON.parse(data);
            console.log(obj.ticket);
        };

        var myErrorEvent = function(data) {
            console.log(data);
            const obj = JSON.parse(data);
            console.log(obj.ticket);
        };

        var myPaymentReceipt = function(data) {
            console.log(data);
            const obj = JSON.parse(data);
            console.log(obj.ticket);
        };

        var myPaymentComplete = function(data) {
            console.log(data);
            const obj = JSON.parse(data);
            console.log(obj.ticket);
        };

        /**
         * Set callbacks in JavaScript:
         */
        myCheckout.setCallback("page_loaded", myPageLoad);
        myCheckout.setCallback("cancel_transaction", myCancelTransaction);
        myCheckout.setCallback("error_event", myErrorEvent);
        myCheckout.setCallback("payment_receipt", myPaymentReceipt);
        myCheckout.setCallback("payment_complete", myPaymentComplete);

        myCheckout.startCheckout($ticket);
    })
JS;

$wa = EasyStoreHelper::wa();

// Add script to the document head.

// if ($environment == 'test') {
    $wa->registerAndUseScript('moneris', MonerisConstants::MONERIS_TEST_JS, [], ['type' => 'text/javascript']);
// } else {
//     $wa->registerAndUseScript('moneris', MonerisConstants::MONERIS_LIVE_JS, [], ['type' => 'text/javascript']);
// }
$wa->addInlineScript($inlineJS);
