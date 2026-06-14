<?php

declare (strict_types=1);
namespace NotMattPress\AiClient\Providers\Contracts;

use NotMattPress\AiClient\Common\Exception\InvalidArgumentException;
use NotMattPress\AiClient\Providers\DTO\ProviderMetadata;
use NotMattPress\AiClient\Providers\Models\Contracts\ModelInterface;
use NotMattPress\AiClient\Providers\Models\DTO\ModelConfig;
/**
 * Interface for AI providers.
 *
 * Providers represent AI services (Google, OpenAI, Anthropic, etc.)
 * and provide access to models, metadata, and availability information.
 *
 * @since 0.1.0
 */
interface ProviderInterface
{
    /**
     * Gets provider metadata.
     *
     * @since 0.1.0
     *
     * @return ProviderMetadata Provider metadata.
     */
    public static function metadata(): ProviderMetadata;
    /**
     * Creates a model instance.
     *
     * @since 0.1.0
     *
     * @param string $modelId Model identifier.
     * @param ?ModelConfig $modelConfig Model configuration.
     * @return ModelInterface Model instance.
     * @throws InvalidArgumentException If model not found or configuration invalid.
     */
    public static function model(string $modelId, ?ModelConfig $modelConfig = null): ModelInterface;
    /**
     * Gets provider availability checker.
     *
     * @since 0.1.0
     *
     * @return ProviderAvailabilityInterface Provider availability checker.
     */
    public static function availability(): \NotMattPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
    /**
     * Gets model metadata directory.
     *
     * @since 0.1.0
     *
     * @return ModelMetadataDirectoryInterface Model metadata directory.
     */
    public static function modelMetadataDirectory(): \NotMattPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
}
