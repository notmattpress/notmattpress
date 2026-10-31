<?php

declare (strict_types=1);
namespace NotMattPress\AiClient\Providers\Http\Contracts;

use NotMattPress\AiClient\Common\Contracts\WithJsonSchemaInterface;
use NotMattPress\AiClient\Providers\Http\DTO\Request;
/**
 * Interface for HTTP request authentication.
 *
 * @since 0.1.0
 */
interface RequestAuthenticationInterface extends WithJsonSchemaInterface
{
    /**
     * Authenticates an HTTP request.
     *
     * @since 0.1.0
     *
     * @param Request $request The request to authenticate.
     * @return Request The authenticated request.
     */
    public function authenticateRequest(Request $request): Request;
}
