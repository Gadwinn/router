<?php

declare(strict_types=1);

namespace G\Router;

use Psr\Http\Message\RequestInterface;
use G\Router\Routes\RouteInterface;
use G\Router\Exceptions\{MethodNotAllowedException, NotFoundException, RouterException};

interface RouterInterface
{
    /**
     * Get the allowed methods
     *
     * Returns an array of all currently allowed HTTP methods.
     *
     * @return string[]
     */
    public function getAllowedMethods(): array;

    /**
     * Add an allowed method
     *
     * Adds the provided HTTP method to the list of allowed methods.
     *
     * @param string $method
     *
     * @return static
     */
    public function addAllowedMethod(string $method): static;

    /**
     * @return array<string,string> Returns a mapping of pattern aliases to their respective regex patterns.
     */
    public function getPatterns(): array;

    /**
     * Add a new pattern
     *
     * Associates a regex pattern with the given alias for route matching.
     *
     * @param string $alias
     * @param string $regex
     *
     * @return static
     */
    public function addPattern(string $alias, string $regex): static;

    /**
     * Match a request to a route
     *
     * Finds a matching route or throws an exception if none is found.
     *
     * @param RequestInterface $request
     *
     * @return RouteInterface
     *
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     */
    public function match(RequestInterface $request): RouteInterface;

    /**
     * Get a route by its name
     *
     * Returns the route or null if not found.
     *
     * @param string $name
     *
     * @return ?RouteInterface
     */
    public function matchByName(string $name): ?RouteInterface;

    /**
     * Generate a URL
     *
     * Generates a URL with an optional route name and parameters.
     *
     * @param ?string $name
     * @param array<string,string> $params
     *
     * @return string
     */
    public function url(?string $name, array $params = []): string;

    /**
     * Get all routes
     *
     * Returns an associative array of routes keyed by route name.
     *
     * @return array<string,RouteInterface>
     */
    public function getRoutes(): array;

    /**
     * Create a route group
     *
     * Defines routes under a specific path prefix and shared options.
     *
     * @param string $prefixPath
     * @param callable $callback
     * @param array<string,mixed> $options
     *
     * @return static
     */
    public function group(string $prefixPath, callable $callback, array $options = []): static;

    /**
     * Map one or more methods to a route
     *
     * Associates the given methods, path, and target handler with optional settings.
     *
     * @param string[]|string $method
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function map(
        array|string $method,
        string $path,
        callable|array|string $target,
        array $options = []
    ): static;

    /**
     * Unmap a route definition
     *
     * Removes routes specified by method and path if they exist.
     *
     * @param string[]|string $method
     * @param string $path
     *
     * @return static
     */
    public function unmap(array|string $method, string $path): static;

    /**
     * Unmap a route by its name
     *
     * Removes the route corresponding to the specified name.
     *
     * @param string $name
     *
     * @return static
     */
    public function unmapByName(string $name): static;

    /**
     * Map all HTTP methods
     *
     * Creates a route for all methods with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function any(string $path, callable|array|string $target, array $options = []): static;

    /**
     * Map a GET request
     *
     * Creates a route for a GET request with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function get(string $path, callable|array|string $target, array $options = []): static;

    /**
     * Map a POST request
     *
     * Creates a route for a POST request with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function post(string $path, callable|array|string $target, array $options = []): static;

    /**
     * Map a PUT request
     *
     * Creates a route for a PUT request with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function put(string $path, callable|array|string $target, array $options = []): static;

    /**
     * Map a PATCH request
     *
     * Creates a route for a PATCH request with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function patch(string $path, callable|array|string $target, array $options = []): static;

    /**
     * Map a DELETE request
     *
     * Creates a route for a DELETE request with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function delete(string $path, callable|array|string $target, array $options = []): static;

    /**
     * Map a CONNECT request
     *
     * Creates a route for a CONNECT request with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function connect(string $path, callable|array|string $target, array $options = []): static;

    /**
     * Map a HEAD request
     *
     * Creates a route for a HEAD request with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function head(string $path, callable|array|string $target, array $options = []): static;

    /**
     * Map an OPTIONS request
     *
     * Creates a route for an OPTIONS request with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function options(string $path, callable|array|string $target, array $options = []): static;

    /**
     * Map a TRACE request
     *
     * Creates a route for a TRACE request with the specified path and target.
     *
     * @param string $path
     * @param callable|array<mixed,mixed>|string $target
     * @param array<string,mixed> $options
     *
     * @return static
     * @throws RouterException
     */
    public function trace(string $path, callable|array|string $target, array $options = []): static;
}
