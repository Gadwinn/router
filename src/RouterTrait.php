<?php

declare(strict_types=1);

namespace G\Router;

trait RouterTrait
{
    #[\Override]
    public function any(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map($this->getAllowedMethods(), $path, $target, $options);
    }

    #[\Override]
    public function get(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map('GET', $path, $target, $options);
    }

    #[\Override]
    public function post(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map('POST', $path, $target, $options);
    }

    #[\Override]
    public function put(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map('PUT', $path, $target, $options);
    }

    #[\Override]
    public function patch(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map('PATCH', $path, $target, $options);
    }

    #[\Override]
    public function delete(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map('DELETE', $path, $target, $options);
    }

    #[\Override]
    public function connect(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map('CONNECT', $path, $target, $options);
    }

    #[\Override]
    public function head(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map('HEAD', $path, $target, $options);
    }

    #[\Override]
    public function options(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map('OPTIONS', $path, $target, $options);
    }

    #[\Override]
    public function trace(string $path, callable|array|string $target, array $options = []): static
    {
        return $this->map('TRACE', $path, $target, $options);
    }
}
