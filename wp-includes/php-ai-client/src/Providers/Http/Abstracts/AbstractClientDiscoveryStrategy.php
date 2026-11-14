<?php

declare (strict_types=1);
namespace NotMattPress\AiClient\Providers\Http\Abstracts;

use NotMattPress\AiClientDependencies\Http\Discovery\Psr18ClientDiscovery;
use NotMattPress\AiClientDependencies\Http\Discovery\Strategy\DiscoveryStrategy;
use NotMattPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory;
use NotMattPress\AiClientDependencies\Psr\Http\Client\ClientInterface;
/**
 * Abstract discovery strategy for HTTP client implementations.
 *
 * Provides a base for registering custom HTTP client implementations
 * with HTTPlug's discovery mechanism. Subclasses must implement
 * the createClient() method to provide their specific PSR-18
 * HTTP client instance using the provided Psr17Factory.
 *
 * @since 1.1.0
 */
abstract class AbstractClientDiscoveryStrategy implements DiscoveryStrategy
{
    /**
     * Initializes and registers the discovery strategy.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public static function init(): void
    {
        if (!class_exists('NotMattPress\AiClientDependencies\Http\Discovery\Psr18ClientDiscovery')) {
            return;
        }
        Psr18ClientDiscovery::prependStrategy(static::class);
    }
    /**
     * {@inheritDoc}
     *
     * @since 1.1.0
     *
     * @param string $type The type of discovery.
     * @return array<array<string, mixed>> The discovery candidates.
     */
    public static function getCandidates($type)
    {
        if (ClientInterface::class === $type) {
            return [['class' => static function () {
                $psr17Factory = new Psr17Factory();
                return static::createClient($psr17Factory);
            }]];
        }
        $psr17Factories = ['NotMattPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface', 'NotMattPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface', 'NotMattPress\AiClientDependencies\Psr\Http\Message\ServerRequestFactoryInterface', 'NotMattPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface', 'NotMattPress\AiClientDependencies\Psr\Http\Message\UploadedFileFactoryInterface', 'NotMattPress\AiClientDependencies\Psr\Http\Message\UriFactoryInterface'];
        if (in_array($type, $psr17Factories, \true)) {
            return [['class' => Psr17Factory::class]];
        }
        return [];
    }
    /**
     * Creates an instance of the HTTP client.
     *
     * Subclasses must implement this method to return their specific
     * PSR-18 HTTP client instance. The provided Psr17Factory implements
     * all PSR-17 interfaces (RequestFactory, ResponseFactory, StreamFactory,
     * etc.) and can be used to satisfy client constructor dependencies.
     *
     * @since 1.1.0
     *
     * @param Psr17Factory $psr17Factory The PSR-17 factory for creating HTTP messages.
     * @return ClientInterface The PSR-18 HTTP client.
     */
    abstract protected static function createClient(Psr17Factory $psr17Factory): ClientInterface;
}
