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

use JoomShaper\Component\EasyStore\Administrator\Plugin\Constants;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Class that contains constants for the Paddle payment gateway.
 * @since 1.0.0
 */
class MonerisConstants extends Constants
{
    public $ticket;

    /**
     * Plugin parameters
     *
     * @var Registry
     */
    protected $params;

    /**
     * The payment plugin name
     *
     * @var string
     */
    protected $name = 'moneris';

    public const TEST_PRELOAD_URL = 'https://gatewayt.moneris.com/chktv2/request/request.php';
    public const LIVE_PRELOAD_URL = 'https://gateway.moneris.com/chktv2/request/request.php';

    public const MONERIS_TEST_JS = 'https://gatewayt.moneris.com/chktv2/js/chkt_v2.00.js';
    public const MONERIS_LIVE_JS = 'https://gateway.moneris.com/chktv2/js/chkt_v2.00.js';

    /**
     * The constructor method.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        parent::__construct($this->name);
    }

    public function getPreloadUrl()
    {
        if($this->getPaymentEnvironment() === 'test') {
            return self::TEST_PRELOAD_URL;
        }

        return self::LIVE_PRELOAD_URL;
    }

    public function getStoreId()
    {
        if($this->getPaymentEnvironment() === 'test') {
            return $this->params->get('test_store_id');
        }

        return $this->params->get('live_store_id');
    }

    public function getApiToken()
    {
        if($this->getPaymentEnvironment() === 'test') {
            return $this->params->get('test_api_token');
        }

        return $this->params->get('live_api_token');
    }

    public function getCheckoutId()
    {
        return $this->params->get('checkout_id');
    }

    public function getEnvironment()
    {
        return $this->getPaymentEnvironment() === 'test' ? 'qa' : 'prod';
    }

    public function getScript()
    {
        return $this->getPaymentEnvironment() === 'test' ? self::MONERIS_TEST_JS : self::MONERIS_LIVE_JS;
    }

    public function setTicket($ticket)
    {
        return $this->ticket = $ticket;
    }

    public function getTicket()
    {
        return $this->ticket;
    }
}
