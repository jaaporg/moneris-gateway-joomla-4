<?php

/**
 * @package     EasyStore.Site
 * @subpackage  EasyStore.Sample
 *
 * @copyright   Copyright (C) 2023 JoomShaper <https://www.joomshaper.com>. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace JoomShaper\Plugin\EasyStore\Moneris\Extension;


use Joomla\CMS\Log\Log;
use Joomla\Event\Event;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Application\CMSApplication;
use JoomShaper\Plugin\EasyStore\Moneris\Utils\MonerisConstants;
use JoomShaper\Component\EasyStore\Administrator\Plugin\PaymentGatewayPlugin;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Layout\LayoutHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class MonerisPayment extends PaymentGatewayPlugin
{    
    /**
     * Initiate an event that will lead to a redirection to the checkout page.
     *
     * @param Event $event -- The event object that contains cart data required for payment processing.
     * 
     * @since 1.0.0
     */
    public function onPayment(Event $event)
    {
        // Get the necessary data from `SampleConstants` which are needed to initiate payment process.
        $constants   = new MonerisConstants();
        $eventArguments = $event->getArguments();  
        $productsList   = $eventArguments['subject'] ? $eventArguments['subject'] : [];

        $data = [];
        $data["store_id"] = $constants->getStoreId();
        $data["api_token"] = $constants->getApiToken();
        $data["checkout_id"] = $constants->getCheckoutId();

        if($constants->getEnvironment() == "test") {
            $data["environment"] = "qa";
        }

        $data["action"] = "preload";
        $data["token"] = [];
        $data["ask_cvv"] = "Y";
        $data["order_no"] = "";
        $data["cust_id"] = "chkt - cust - 0303";
        $data["dynamic_descriptor"] = "dyndesc";
        $data["language"] = "en";

        $txnTotal = 0;
        $items = [];
        if (!empty($productsList)) {
            if(!empty($productsList->items)) {
                foreach ($productsList->items as $key => $product) {
                    
                    $price = $product->discounted_price ? $product->discounted_price : $product->regular_price;

                    $items[$key]["url"]    = "";
                    $items[$key]["product_code"]    = (string)$product->id;
                    $items[$key]["description"]    = $product->title;
                    $items[$key]["quantity"]    = (string)$product->quantity;
                    $items[$key]["unit_cost"]      = (string)$price;
                    
                    $txnTotal += $price;
                }
            }

            $txnTotal = (string) $txnTotal + $productsList->tax;
        }

        $data["cart"] = $items;
        $data["txn_total"] = number_format($txnTotal, 2, ".", "");
             
        // $data["contact_details"] = [
        //     "first_name" => "bill",
        //     "last_name" => "smith",
        //     "email" => "test@moneris.com",
        //     "phone" => "4165551234"
        // ];

        // $data["shipping_details"] = [
        //     "address_1" => "1 main st",
        //     "address_2" => "Unit 2012",
        //     "city" => "Toronto",
        //     "province" => "ON",
        //     "country" => "CA",
        //     "postal_code" => "M1M1M1"
        // ];

        // $data["billing_details"] = [
        //     "address_1" => "1 main st",
        //     "address_2" => "Unit 2000",
        //     "city" => "Toronto",
        //     "province" => "ON",
        //     "country" => "CA",
        //     "postal_code" =>"M1M1M1"
        // ];

        try {
            
            $response = HttpFactory::getHttp()->post($constants->getPreloadUrl(), json_encode($data));

            $responseData = json_decode((string)$response->getBody(),true);

            $ticket = "";
            // If response is true then return the url
            if ($responseData['response']['success'] == "true") {

                $ticket = $responseData['response']['ticket'];

                $layoutPath  = JPATH_ROOT . '/plugins/easystore/moneris/src/layouts';

                echo LayoutHelper::render('checkoutJs', ['ticket' => $ticket], $layoutPath);

                $event->setArgument('redirectionUrl', "https://gatewayt.moneris.com/chktv2/display/index.php?tck={$ticket}");
            } else {
                Factory::getApplication()->enqueueMessage($responseData['response']['error'], 'error');
            }
        } catch (\Throwable $error) {
            Factory::getApplication()->enqueueMessage($error->getMessage(), 'error');
        }
    }

    /**
     * Handles notifications received from the webhook / payment portal.
     *
     * @param Event $event -- The event object contains relevant data for payment notification, including Raw Payload, GET data, POST data, server variables, and a variable representing an instance with two methods.
     * @since 1.0.0
     */

    public function onPaymentNotify( )
    { 
        //
    }

}
