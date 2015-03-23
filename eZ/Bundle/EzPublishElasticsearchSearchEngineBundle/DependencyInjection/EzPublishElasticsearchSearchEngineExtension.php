<?php
/**
 * This file is part of the eZ Publish Kernel package
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Bundle\EzPublishElasticsearchSearchEngineBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class EzPublishElasticsearchSearchEngineExtension extends Extension
{
    const MAIN_SEARCH_ENGINE_ID = "ezpublish.spi.search.elasticsearch";
    const HTTP_CLIENT_ID = "ezpublish.search.elasticsearch.content.gateway.client.http.stream";
    const CONTENT_SEARCH_HANDLER_ID = "ezpublish.spi.search.elasticsearch.content_handler";
    const CONTENT_SEARCH_GATEWAY_ID = "ezpublish.search.elasticsearch.content.gateway.native";
    const LOCATION_SEARCH_HANDLER_ID = "ezpublish.spi.search.elasticsearch.location_handler";
    const LOCATION_SEARCH_GATEWAY_ID = "ezpublish.search.elasticsearch.location.gateway.native";

    public function getAlias()
    {
        return "ez_search_engine_elasticsearch";
    }

    public function load( array $configs, ContainerBuilder $container )
    {
        $configuration = $this->getConfiguration( $configs, $container );
        $config = $this->processConfiguration( $configuration, $configs );

        // Loading configuration from Core/settings
        $loader = new YamlFileLoader(
            $container,
            new FileLocator( __DIR__ . '/../../../Publish/Core/settings' )
        );
        $loader->load( 'indexable_fieldtypes.yml' );
        $loader->load( "search_engines/elasticsearch.yml" );

        $loader = new YamlFileLoader(
            $container,
            new FileLocator( __DIR__ . '/../Resources/config' )
        );
        $loader->load( 'services.yml' );

        $this->processConnectionConfiguration( $container, $config );
    }

    /**
     * Processes connection configuration by flattening connection parameters
     * and setting them to the container as parameters.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @param array $config
     */
    private function processConnectionConfiguration( ContainerBuilder $container, $config )
    {
        $alias = $this->getAlias();

        if ( isset( $config["default_connection"] ) )
        {
            $container->setParameter(
                "{$alias}.default_connection",
                $config["default_connection"]
            );
        }

        foreach ( $config["connections"] as $name => $params )
        {
            $this->configureSearchServices( $container, $name, $params );
            $container->setParameter( "$alias.connection.$name", $params );
        }
    }

    /**
     * Creates needed search services for given connection name and parameters.
     *
     * @param ContainerBuilder $container
     * @param string $connectionName
     * @param array $connectionParams
     */
    private function configureSearchServices( ContainerBuilder $container, $connectionName, $connectionParams )
    {
        // Http client
        $httpClientId = static::HTTP_CLIENT_ID . ".$connectionName";
        $httpClientDef = new DefinitionDecorator( self::HTTP_CLIENT_ID );
        $httpClientDef->replaceArgument( 0, $connectionParams['server'] );
        $container->setDefinition( $httpClientId, $httpClientDef );

        // Content search gateway
        $contentSearchGatewayDef = new DefinitionDecorator( self::CONTENT_SEARCH_GATEWAY_ID );
        $contentSearchGatewayDef->replaceArgument( 0, new Reference( $httpClientId ) );
        $contentSearchGatewayDef->replaceArgument( 5, $connectionParams['index_name'] );
        $contentSearchGatewayId = self::CONTENT_SEARCH_GATEWAY_ID . ".$connectionName";
        $container->setDefinition( $contentSearchGatewayId, $contentSearchGatewayDef );

        // Content search handler
        $contentSearchHandlerDefinition = new DefinitionDecorator( static::CONTENT_SEARCH_HANDLER_ID );
        $contentSearchHandlerDefinition->replaceArgument( 0, new Reference( $contentSearchGatewayId ) );
        $contentSearchHandlerDefinition->replaceArgument( 3, $connectionParams['document_type_name']['content'] );
        $contentSearchHandlerId = self::CONTENT_SEARCH_HANDLER_ID . ".$connectionName";
        $container->setDefinition( $contentSearchHandlerId, $contentSearchHandlerDefinition );

        // Location search gateway
        $locationSearchGatewayDef = new DefinitionDecorator( static::LOCATION_SEARCH_GATEWAY_ID );
        $locationSearchGatewayDef->replaceArgument( 0, new Reference( $httpClientId ) );
        $locationSearchGatewayDef->replaceArgument( 5, $connectionParams['index_name'] );
        $locationSearchGatewayId = self::LOCATION_SEARCH_GATEWAY_ID . ".$connectionName";
        $container->setDefinition( $locationSearchGatewayId, $locationSearchGatewayDef );

        // Content search handler
        $contentSearchHandlerDefinition = new DefinitionDecorator( static::LOCATION_SEARCH_HANDLER_ID );
        $contentSearchHandlerDefinition->replaceArgument( 0, new Reference( $locationSearchGatewayId ) );
        $contentSearchHandlerDefinition->replaceArgument( 3, $connectionParams['document_type_name']['location'] );
        $locationSearchHandlerId = self::LOCATION_SEARCH_HANDLER_ID . ".$connectionName";
        $container->setDefinition( $locationSearchHandlerId, $contentSearchHandlerDefinition );

        // Search engine itself, for given connection name
        $searchEngineDef = new DefinitionDecorator( self::MAIN_SEARCH_ENGINE_ID );
        $searchEngineDef->replaceArgument( 0, new Reference( $contentSearchHandlerId ) );
        $searchEngineDef->replaceArgument( 1, new Reference( $locationSearchHandlerId ) );
        $searchEngineDef
            ->addTag( 'ezpublish.searchEngine', ["alias" => "elasticsearch.$connectionName"] )
            ->setLazy( true )
            ->setPublic( false );
        $container->setDefinition( self::MAIN_SEARCH_ENGINE_ID . ".$connectionName", $searchEngineDef );
    }

    public function getConfiguration( array $config, ContainerBuilder $container )
    {
        return new Configuration( $this->getAlias() );
    }
}
