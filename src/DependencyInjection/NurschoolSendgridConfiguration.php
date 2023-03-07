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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class NurschoolSendgridConfiguration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('nurschool_sendgrid');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('api_key')->isRequired()->end()
                ->booleanNode('disable_delivery')->defaultFalse()->end()
                //->scalarNode('redirect_to')->defaultFalse()->end()
                ->booleanNode('sandbox')->defaultFalse()->end()
                ->scalarNode('default_sender_address')->isRequired()->end()
                ->scalarNode('default_sender_name')->isRequired()->end()
                ->arrayNode('emails')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                    //->children()
                        //->arrayNode('sendAccountValidationEmail')
                            //->isRequired()
                            ->children()
                                ->scalarNode('template')->isRequired()->end()
                                ->scalarNode('subject')->end()
                                ->arrayNode('sender')
                                    ->children()
                                        ->scalarNode('address')->end()
                                /*    ->validate()
                                    ->always(function(array $config) {
                                        if (!$config['address']) {
                                            $config['address'] = $config['default_address'];
                                        }
                                        return $config;
                                    })
                                ->end()*/
                                        ->scalarNode('name')->end()
                                    ->end()
                                ->end()
                            ->end()
                    ->end() // arrayPrototype
                ->end() // emails
            ->end()
        ;

        return $treeBuilder;
    }
}