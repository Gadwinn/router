<?php

declare(strict_types=1);

namespace G\Router\Routes;

class Route implements RouteInterface
{
    /**
     * @var callable|array<mixed,mixed>|string
     */
    protected readonly mixed $target;

    /**
     * @var array<string,string>
     */
    protected array $params = [];

    /**
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     */
    public function __construct(
        protected readonly string $method,
        protected readonly string $path,
        callable|array|string $target,
        protected readonly array $options = []
    ) {
        $this->target = $target;
    }

    #[\Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    #[\Override]
    public function getPath(): string
    {
        return $this->path;
    }

    #[\Override]
    public function getTarget(): callable|array|string
    {
        return $this->target;
    }

    #[\Override]
    public function getParams(): array
    {
        return $this->params;
    }

    #[\Override]
    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    #[\Override]
    public function getOptions(): array
    {
        return $this->options;
    }

    #[\Override]
    public function getOption(string $option, mixed $default = null): mixed
    {
        return $this->options[$option] ?? $default;
    }
}
