<?php

declare(strict_types=1);

namespace G\Router\Route;

interface RouteInterface
{
    /**
     * @return string
     */
    public function getMethod(): string;

    /**
     * @return string
     */
    public function getPath(): string;

    /**
     * @return callable|array<mixed,mixed>|string
     */
    public function getTarget(): callable|array|string;

    /**
     * @return array<string,string>
     */
    public function getParams(): array;

    /**
     * @param array<string,string> $params
     * @return static
     */
    public function setParams(array $params): static;

    /**
     * @return array<string,mixed>
     */
    public function getOptions(): array;

    /**
     * @return mixed
     */
    public function getOption(string $option, mixed $default = null): mixed;
}
