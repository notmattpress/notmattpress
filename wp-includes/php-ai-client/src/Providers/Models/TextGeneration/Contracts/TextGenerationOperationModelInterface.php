<?php

declare (strict_types=1);
namespace NotMattPress\AiClient\Providers\Models\TextGeneration\Contracts;

use NotMattPress\AiClient\Messages\DTO\Message;
use NotMattPress\AiClient\Operations\DTO\GenerativeAiOperation;
/**
 * Interface for models that support asynchronous text generation operations.
 *
 * Provides methods for initiating long-running text generation tasks.
 *
 * @since 0.1.0
 */
interface TextGenerationOperationModelInterface
{
    /**
     * Creates a text generation operation.
     *
     * @since 0.1.0
     *
     * @param list<Message> $prompt Array of messages containing the text generation prompt.
     * @return GenerativeAiOperation The initiated text generation operation.
     */
    public function generateTextOperation(array $prompt): GenerativeAiOperation;
}
