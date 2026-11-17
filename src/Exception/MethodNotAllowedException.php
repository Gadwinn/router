<?php

declare(strict_types=1);

namespace G\Router\Exception;

class MethodNotAllowedException extends RouterException
{
    /**
     * @param array<string> $allowedMethods HTTP methods allowed for the matched route path
     */
    public function __construct(
        string $message = '',
        int $code = 405,
        protected readonly array $allowedMethods = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the list of HTTP methods allowed for the route path that was matched.
     * Useful for building the `Allow` response header (RFC 9110).
     *
     * @return array<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
