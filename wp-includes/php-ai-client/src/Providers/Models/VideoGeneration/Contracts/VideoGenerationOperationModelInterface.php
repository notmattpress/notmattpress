<?php

declare (strict_types=1);
namespace NotMattPress\AiClient\Providers\Models\VideoGeneration\Contracts;

use NotMattPress\AiClient\Messages\DTO\Message;
use NotMattPress\AiClient\Operations\DTO\GenerativeAiOperation;
/**
 * Interface for models that support asynchronous video generation operations.
 *
 * Provides methods for initiating long-running video generation tasks.
 *
 * @since 1.3.0
 */
interface VideoGenerationOperationModelInterface
{
    /**
     * Creates a video generation operation.
     *
     * @since 1.3.0
     *
     * @param list<Message> $prompt Array of messages containing the video generation prompt.
     * @return GenerativeAiOperation The initiated video generation operation.
     */
    public function generateVideoOperation(array $prompt): GenerativeAiOperation;
}
