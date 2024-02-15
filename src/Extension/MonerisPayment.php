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
        // Get the necessary data from `SampleConstants` which are needed to initiate payment process.
        $constants   = new MonerisConstants();
        $eventArguments = $event->getArguments();
        $productsList   = $eventArguments['subject'] ? $eventArguments['subject'] : [];
        $paymentData   = $eventArguments['subject'] ? $eventArguments['subject'] : [];

        $query['notify_url']    = $constants->getWebHookUrl();
        $query['return']        = $constants->getSuccessUrl();
        $query['cancel_return'] = $constants->getCancelUrl($productsList->order_id);

        $data = [];
        $data["store_id"] = $constants->getStoreId();
        $data["api_token"] = $constants->getApiToken();
        $data["checkout_id"] = $constants->getCheckoutId();
        $requestData["environment"] = $constants->getEnvironment();
        $data["action"] = "preload";
        $data["token"] = [];
        $data["ask_cvv"] = "Y";
        $data["order_no"] = "";
        $data["cust_id"] = (string) $paymentData->order_id;
        $data["dynamic_descriptor"] = "dyndesc";
        $data["language"] = "en";

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

            $ticket = "";
            // If response is true then return the url
            if ($responseData->response->success == "true") {

                $ticket = $responseData->response->ticket;

                $layoutPath  = JPATH_ROOT . '/plugins/easystore/moneris/src/layouts';

                echo LayoutHelper::render('checkoutJs', ['ticket' => $ticket], $layoutPath);
            } else {
                Log::add($responseData->response->error->message, Log::ERROR, 'moneris.easystore');
                $this->app->enqueueMessage($responseData->response->error->message, 'error');
                $this->app->redirect($paymentData->back_to_checkout_page);
            }
        } catch (\Throwable $error) {
            Log::add($error->getMessage(), Log::ERROR, 'moneris.easystore');
            $this->app->enqueueMessage($error->getMessage(), 'error');
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
            Log::add($responseData->response->error->message, Log::ERROR, 'moneris.easystore');
            $this->app->enqueueMessage($responseData->response->error->message, 'error');
            $this->app->redirect($paymentNotifyData->back_to_checkout_page);
        }
    }

}
