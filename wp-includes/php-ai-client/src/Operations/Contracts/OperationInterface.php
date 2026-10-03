<?php

declare (strict_types=1);
namespace NotMattPress\AiClient\Operations\Contracts;

use NotMattPress\AiClient\Operations\Enums\OperationStateEnum;
/**
 * Interface for AI operations.
 *
 * Operations represent long-running AI tasks that may not complete immediately.
 * They provide a way to track the progress and retrieve results asynchronously.
 *
 * @since 0.1.0
 */
interface OperationInterface
{
    /**
     * Gets the operation ID.
     *
     * @since 0.1.0
     *
     * @return string The unique operation identifier.
     */
    public function getId(): string;
    /**
     * Gets the current state of the operation.
     *
     * @since 0.1.0
     *
     * @return OperationStateEnum The operation state.
     */
    public function getState(): OperationStateEnum;
}
