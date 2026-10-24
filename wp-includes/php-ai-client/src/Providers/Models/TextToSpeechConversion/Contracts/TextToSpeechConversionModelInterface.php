<?php

declare (strict_types=1);
namespace NotMattPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts;

use NotMattPress\AiClient\Messages\DTO\Message;
use NotMattPress\AiClient\Results\DTO\GenerativeAiResult;
/**
 * Interface for models that support text-to-speech conversion.
 *
 * Provides synchronous methods for converting text to speech audio.
 *
 * @since 0.1.0
 */
interface TextToSpeechConversionModelInterface
{
    /**
     * Converts text to speech.
     *
     * @since 0.1.0
     *
     * @param list<Message> $prompt Array of messages containing the text to convert to speech.
     * @return GenerativeAiResult Result containing generated speech audio.
     */
    public function convertTextToSpeechResult(array $prompt): GenerativeAiResult;
}
