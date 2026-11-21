<?php

namespace NotMattPress\AiClientDependencies\Http\Discovery\Strategy;

use NotMattPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface;
use NotMattPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface;
use NotMattPress\AiClientDependencies\Psr\Http\Message\ServerRequestFactoryInterface;
use NotMattPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface;
use NotMattPress\AiClientDependencies\Psr\Http\Message\UploadedFileFactoryInterface;
use NotMattPress\AiClientDependencies\Psr\Http\Message\UriFactoryInterface;
/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * Don't miss updating src/Composer/Plugin.php when adding a new supported class.
 */
final class CommonPsr17ClassesStrategy implements DiscoveryStrategy
{
    /**
     * @var array
     */
    private static $classes = [RequestFactoryInterface::class => ['Phalcon\Http\Message\RequestFactory', 'Nyholm\Psr7\Factory\Psr17Factory', 'GuzzleHttp\Psr7\HttpFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Diactoros\RequestFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Guzzle\RequestFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Slim\RequestFactory', 'Laminas\Diactoros\RequestFactory', 'Slim\Psr7\Factory\RequestFactory', 'NotMattPress\AiClientDependencies\HttpSoft\Message\RequestFactory'], ResponseFactoryInterface::class => ['Phalcon\Http\Message\ResponseFactory', 'Nyholm\Psr7\Factory\Psr17Factory', 'GuzzleHttp\Psr7\HttpFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Diactoros\ResponseFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Guzzle\ResponseFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Slim\ResponseFactory', 'Laminas\Diactoros\ResponseFactory', 'Slim\Psr7\Factory\ResponseFactory', 'NotMattPress\AiClientDependencies\HttpSoft\Message\ResponseFactory'], ServerRequestFactoryInterface::class => ['Phalcon\Http\Message\ServerRequestFactory', 'Nyholm\Psr7\Factory\Psr17Factory', 'GuzzleHttp\Psr7\HttpFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Diactoros\ServerRequestFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Guzzle\ServerRequestFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Slim\ServerRequestFactory', 'Laminas\Diactoros\ServerRequestFactory', 'Slim\Psr7\Factory\ServerRequestFactory', 'NotMattPress\AiClientDependencies\HttpSoft\Message\ServerRequestFactory'], StreamFactoryInterface::class => ['Phalcon\Http\Message\StreamFactory', 'Nyholm\Psr7\Factory\Psr17Factory', 'GuzzleHttp\Psr7\HttpFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Diactoros\StreamFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Guzzle\StreamFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Slim\StreamFactory', 'Laminas\Diactoros\StreamFactory', 'Slim\Psr7\Factory\StreamFactory', 'NotMattPress\AiClientDependencies\HttpSoft\Message\StreamFactory'], UploadedFileFactoryInterface::class => ['Phalcon\Http\Message\UploadedFileFactory', 'Nyholm\Psr7\Factory\Psr17Factory', 'GuzzleHttp\Psr7\HttpFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Diactoros\UploadedFileFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Guzzle\UploadedFileFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Slim\UploadedFileFactory', 'Laminas\Diactoros\UploadedFileFactory', 'Slim\Psr7\Factory\UploadedFileFactory', 'NotMattPress\AiClientDependencies\HttpSoft\Message\UploadedFileFactory'], UriFactoryInterface::class => ['Phalcon\Http\Message\UriFactory', 'Nyholm\Psr7\Factory\Psr17Factory', 'GuzzleHttp\Psr7\HttpFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Diactoros\UriFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Guzzle\UriFactory', 'NotMattPress\AiClientDependencies\Http\Factory\Slim\UriFactory', 'Laminas\Diactoros\UriFactory', 'Slim\Psr7\Factory\UriFactory', 'NotMattPress\AiClientDependencies\HttpSoft\Message\UriFactory']];
    public static function getCandidates($type)
    {
        $candidates = [];
        if (isset(self::$classes[$type])) {
            foreach (self::$classes[$type] as $class) {
                $candidates[] = ['class' => $class, 'condition' => [$class]];
            }
        }
        return $candidates;
    }
}
