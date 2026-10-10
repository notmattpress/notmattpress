<?php

declare (strict_types=1);
namespace NotMattPress\AiClient\Messages\Enums;

use NotMattPress\AiClient\Common\AbstractEnum;
/**
 * Enum for message part channels.
 *
 * @since 0.1.0
 *
 * @method static self content() Creates an instance for CONTENT channel.
 * @method static self thought() Creates an instance for THOUGHT channel.
 * @method bool isContent() Checks if the channel is CONTENT.
 * @method bool isThought() Checks if the channel is THOUGHT.
 */
class MessagePartChannelEnum extends AbstractEnum
{
    /**
     * Regular (primary) content.
     */
    public const CONTENT = 'content';
    /**
     * Model thinking or reasoning.
     */
    public const THOUGHT = 'thought';
}
