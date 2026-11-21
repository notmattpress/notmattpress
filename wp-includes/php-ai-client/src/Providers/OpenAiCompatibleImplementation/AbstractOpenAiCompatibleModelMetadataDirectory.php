<?php

declare (strict_types=1);
namespace NotMattPress\AiClient\Providers\OpenAiCompatibleImplementation;

use NotMattPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use NotMattPress\AiClient\Providers\Http\DTO\Request;
use NotMattPress\AiClient\Providers\Http\DTO\Response;
use NotMattPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use NotMattPress\AiClient\Providers\Http\Exception\ResponseException;
use NotMattPress\AiClient\Providers\Http\Util\ResponseUtil;
use NotMattPress\AiClient\Providers\Models\DTO\ModelMetadata;
/**
 * Base class for a model metadata directory for providers that implement OpenAI's API format.
 *
 * This abstract class is designed to work with any AI provider that offers an OpenAI-compatible
 * models listing endpoint, including but not limited to Anthropic, Google, and other
 * providers that have adopted OpenAI's models API specification as a standard interface.
 *
 * @since 0.1.0
 */
abstract class AbstractOpenAiCompatibleModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory
{
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected function sendListModelsRequest(): array
    {
        $httpTransporter = $this->getHttpTransporter();
        $request = $this->createRequest(HttpMethodEnum::GET(), 'models');
        $request = $this->getRequestAuthentication()->authenticateRequest($request);
        $response = $httpTransporter->send($request);
        $this->throwIfNotSuccessful($response);
        $modelsMetadataList = $this->parseResponseToModelMetadataList($response);
        $modelMetadataMap = [];
        foreach ($modelsMetadataList as $modelMetadata) {
            $modelMetadataMap[$modelMetadata->getId()] = $modelMetadata;
        }
        return $modelMetadataMap;
    }
    /**
     * Creates a request object for the provider's API.
     *
     * @since 0.1.0
     *
     * @param HttpMethodEnum $method The HTTP method.
     * @param string $path The API endpoint path, relative to the base URI.
     * @param array<string, string|list<string>> $headers The request headers.
     * @param string|array<string, mixed>|null $data The request data.
     * @return Request The request object.
     */
    abstract protected function createRequest(HttpMethodEnum $method, string $path, array $headers = [], $data = null): Request;
    /**
     * Throws an exception if the response is not successful.
     *
     * @since 0.1.0
     *
     * @param Response $response The HTTP response to check.
     * @throws ResponseException If the response is not successful.
     */
    protected function throwIfNotSuccessful(Response $response): void
    {
        /*
         * While this method only calls the utility method, it's important to have it here as a protected method so
         * that child classes can override it if needed.
         */
        ResponseUtil::throwIfNotSuccessful($response);
    }
    /**
     * Parses the response from the API endpoint to list models into a list of model metadata objects.
     *
     * @since 0.1.0
     *
     * @param Response $response The response from the API endpoint to list models.
     * @return list<ModelMetadata> List of model metadata objects.
     */
    abstract protected function parseResponseToModelMetadataList(Response $response): array;
}
