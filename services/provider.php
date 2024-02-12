<?php

/**
 * @package     EasyStore.Site
 * @subpackage  EasyStore.Moneris
 *
 * @copyright   Copyright (C) 2023 JoomShaper <https://www.joomshaper.com>. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\DI\Container;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\DispatcherInterface;
use Joomla\DI\ServiceProviderInterface;
use Joomla\CMS\Extension\PluginInterface;
use JoomShaper\Plugin\EasyStore\Moneris\Extension\MonerisPayment;


return new class() implements ServiceProviderInterface
{
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin = new MonerisPayment(
                    $dispatcher,
                    (array) PluginHelper::getPlugin('easystore', 'moneris') // Replace the plugin name `sample` with your plugin name. 
                );
                
                $plugin->setApplication(Factory::getApplication());

                // Initializes a logging system based on the debugging mode. If you don't want to add log then you can remove this part.
                if (\defined('JDEBUG') && JDEBUG) {
                    $logLevels = Log::ALL;
                    Log::addLogger([
                        'text_file' => "easystore_moneris.php",
                        'text_entry_format' => '{DATE} \t {TIME} \t {LEVEL} \t {CODE} \t {MESSAGE}',
                    ], $logLevels, ["moneris.easystore"]);
                }

                return $plugin;
            }
        );
    }
};
