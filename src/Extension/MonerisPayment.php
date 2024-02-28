<?php

/**
 * @package     EasyStore.Site
 * @subpackage  EasyStore.Sample
 *
 * @copyright   Copyright (C) 2024 japporg <https://www.japporg.com>. All rights reserved.
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
use JoomShaper\Component\EasyStore\Administrator\Helper\EasyStoreDatabaseOrm;
use Joomla\CMS\Filesystem\File;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class MonerisPayment extends PaymentGatewayPlugin
{
    /**
     * Check if all the required fields for the plugin are filled.
     *
     * @return void The result of the check, indicating whether the required fields are filled.
     * @since 1.0.3
     */
    public function onBeforePayment(Event $event)
    {
        $constant = new MonerisConstants();
        $storeId = $constant->getStoreId();
        $apiToken = $constant->getApiToken();
        $checkoutId = $constant->getCheckoutId();

        $isRequiredFieldsFilled = !empty($storeId) && !empty($apiToken) && !empty($checkoutId);

        $event->setArgument('result', $isRequiredFieldsFilled);
    }

    /**
     * Initiate an event that will lead to a redirection to the checkout page.
     *
     * @param Event $event -- The event object that contains cart data required for payment processing.
     *
     * @since 1.0.0
     */
    public function onPayment(Event $event)
    {
        $app = Factory::getApplication();
        $data = $app->input->get('data', '', 'STRING');
        $user = $app->getIdentity();

        $countriesJsonPath = JPATH_ROOT . '/media/com_easystore/data/countries.json';
        $countriesList = [];
        if (File::exists($countriesJsonPath)) {
            $countriesList = json_decode(file_get_contents($countriesJsonPath));
        }

        // Get the necessary data from `SampleConstants` which are needed to initiate payment process.
        $constants   = new MonerisConstants();
        $eventArguments = $event->getArguments();
        $paymentData   = $eventArguments['subject'] ? $eventArguments['subject'] : [];

        $orm = new EasyStoreDatabaseOrm();
        $order = $orm->get('#__easystore_orders', 'id', $paymentData->order_id)->loadObject();
        $shippingAddress = (object) json_decode($order->shipping_address);
        $billingAddress = (object) json_decode($order->billing_address);

        $billingAddressState = '';
        $billingAddressCountry = '';

        $shippingAddressState = '';
        $shippingAddressCountry = '';
        foreach($countriesList as $countries) {
            if($shippingAddress->country == $countries->numeric_code) {
                $shippingAddressCountry = $countries->name;
            }

            if($billingAddress->country == $countries->numeric_code) {
                $billingAddressCountry = $countries->name;
            }

            foreach ($countries->states as $states) {
                if($shippingAddress->state == $states->id) {
                    $shippingAddressState = $states->name;
                }
                if($billingAddress->state == $states->id) {
                    $billingAddressState = $states->name;
                }
            }
        }

        $data = [];
        $data["store_id"] = $constants->getStoreId();
        $data["api_token"] = $constants->getApiToken();
        $data["checkout_id"] = $constants->getCheckoutId();
        $data["environment"] = $constants->getEnvironment();
        $data["action"] = "preload";
        // $data["token"] = [];
        $data["ask_cvv"] = "Y";
        $data["order_no"] = (string) uniqid();
        $data["cust_id"] = (string) $paymentData->order_id;
        $data["dynamic_descriptor"] = "";
        $data["language"] = "en";
        
        $data["contact_details"] = [
            "first_name" => $user->name,
            "last_name" => "",
            "email" => $user->email,
            "phone" => "",
        ];

        $data["shipping_details"] = [
            "address_1" => (string) $shippingAddress->address_1,
            "address_2" => (string) $shippingAddress->address_2,
            "city" => $shippingAddress->city,
            "province" => $shippingAddressState,
            "country" => $shippingAddressCountry,
            "postal_code" => $shippingAddress->zip_code
        ];

        $data["billing_details"] = [
            "address_1" => (string) $billingAddress->address_1,
            "address_2" => (string) $billingAddress->address_2,
            "city" => $billingAddress->city,
            "province" => $billingAddressState,
            "country" => $billingAddressCountry,
            "postal_code" => $billingAddress->zip_code
        ];
        
        $txnTotal = 0;
        $items = [];
        if (!empty($paymentData)) {
            if(!empty($paymentData->items)) {
                foreach ($paymentData->items as $key => $product) {

                    $price = $product->discounted_price ? $product->discounted_price : $product->regular_price;

                    $items[$key]["url"] = "";
                    $items[$key]["quantity"] = (string) $product->quantity;
                    $items[$key]["unit_cost"] = (string) $price;
                    $items[$key]["description"] = $product->title;
                    $items[$key]["product_code"] = (string) $product->id;

                    $txnTotal += $price;
                }
            }

            $txnTotal = (string) $txnTotal + $paymentData->tax;
        }

        $data["cart"] = $items;
        $data["txn_total"] = number_format($paymentData->total_price, 2, $paymentData->decimal_separator, '');

        try {

            $response = HttpFactory::getHttp()->post($constants->getPreloadUrl(), json_encode($data));

            $responseData = json_decode($response->getBody());
            
            // If response is true then return the url
            if ($responseData->response->success == "true") {

                $ticket = $responseData->response->ticket;

                $layoutPath  = JPATH_ROOT . '/plugins/easystore/moneris/src/layouts';

                echo LayoutHelper::render('checkoutJs', ['ticket' => $ticket], $layoutPath);
            } else {
                $errors = array_values((array) $responseData->response->error);

                $str = "";
                foreach($errors as $s => $error) {
                    if(is_array($error) || is_object($error)) {
                        foreach ($error as $key => $value) {
                            $str .= "{$key} {$value}, ";
                        }
                    } else {
                        $str .= "{$s} {$error}, ";
                    }
                }

                Log::add($str, Log::ERROR, 'moneris.easystore');
                $this->app->enqueueMessage($str, 'error');
                $this->app->redirect($paymentData->back_to_checkout_page);
            }
        } catch (\Throwable $error) {
            Log::add("Invalid data", Log::ERROR, 'moneris.easystore');
            $this->app->enqueueMessage("Invalid data", 'error');
            $this->app->redirect($paymentData->back_to_checkout_page);
        }
    }

    /**
     * Handles notifications received from the webhook / payment portal.
     *
     * @param Event $event -- The event object contains relevant data for payment notification, including Raw Payload, GET data, POST data, server variables, and a variable representing an instance with two methods.
     * @since 1.0.0
     */

    public function onPaymentNotify(Event $event)
    {
        // Event Arguments
        $arguments         = $event->getArguments();
        $paymentNotifyData = $arguments['subject'] ?: new \stdClass();

        $constant        = new MonerisConstants();
        $input           = $this->app->input;
        $order           = $paymentNotifyData->order;

        $requestData["store_id"] = $constant->getStoreId();
        $requestData["api_token"] = $constant->getApiToken();
        $requestData["environment"] = $constant->getEnvironment();
        $requestData["checkout_id"] = $constant->getCheckoutId();
        $requestData["ticket"] = $input->get('ticket');
        $requestData["action"] = "receipt";

        $preloadUrl = $constant->getPreloadUrl();

        $response        = HttpFactory::getHttp()->post($preloadUrl, json_encode($requestData));
        $responseData = json_decode($response->getBody());

        if ($responseData->response->success == "true") {

            $receipt = $responseData->response->receipt;

            $data = (object) [
                'id'                   => $receipt->cc->cust_id,
                'payment_status'       => "paid",
                'payment_error_reason' => "",
                'transaction_id'       => $receipt->cc->order_no,
            ];

            try {
                $order->updateOrder($data);
                $order->onOrderPlacementCompletion();
                $this->app->redirect($constant->getSuccessURL());
            } catch (\Throwable $error) {
                Log::add($error->getMessage(), Log::ERROR, 'moneris.easystore');
                $this->app->enqueueMessage($error->getMessage(), 'error');
                $this->app->redirect($paymentNotifyData->back_to_checkout_page);
            }
        } else {
            $errors = array_values((array) $responseData->response->error);

            Log::add(implode($errors), Log::ERROR, 'moneris.easystore');
            $this->app->enqueueMessage(implode(' ', $errors), 'error');
            $this->app->redirect($paymentNotifyData->back_to_checkout_page);
        }
    }

}