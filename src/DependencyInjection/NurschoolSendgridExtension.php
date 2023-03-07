<?php

/*
 * This file is part of the Nurschool project.
 *
 * (c) Nurschool <https://github.com/abbarrasa/nurschool>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Nurschool\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class NurschoolSendgridExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new NurschoolSendgridConfiguration();
        $config = $this->processConfiguration($configuration, $configs);

        //Sets default values for mails
        foreach(array_keys($config['emails']) as $mail) {
            if (!isset($config['emails'][$mail]['sender']['address'])) {
                $config['emails'][$mail]['sender']['address'] = $config['default_sender_address'];
            }

            if (!isset($config['emails'][$mail]['sender']['name'])) {
                $config['emails'][$mail]['sender']['name'] = $config['default_sender_name'];
            }
        }

        $container->setParameter('nurschool_sendgrid.api_key', $config['api_key']);
        $container->setParameter('nurschool_sendgrid.disable_delivery', $config['disable_delivery']);
        //$container->setParameter('nurschool_sendgrid.redirect_to', $config['redirect_to']);
        $container->setParameter('nurschool_sendgrid.sandbox', $config['sandbox']);
        $container->setParameter('nurschool_sendgrid.emails', $config['emails']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../../../config'));
        $loader->load('services.yaml');
    }
}