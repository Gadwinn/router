<?php

declare(strict_types=1);

namespace G\Router;

use Psr\Http\Message\RequestInterface;
use G\Router\Routes\{Route, RouteInterface};
use G\Router\Exceptions\{MethodNotAllowedException, NotFoundException, RouterException};

class Router extends AbstractRouter
{
    /**
     * @var array<string,RouteInterface>
     */
    protected array $routes = [];

    /**
     * Path templates (as given to map(), group prefix included) by route hash.
     *
     * @var array<string,string>
     */
    protected array $templates = [];

    protected string $prefixPath = '';

    /**
     * Path the application is served under (no trailing slash), '' at the web root.
     */
    protected string $basePath = '';

    protected string $prefixName = '';

    /**
     * @var array<string>
     */
    protected array $allowedMethods = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'CONNECT',
        'HEAD',
        'OPTIONS',
        'TRACE'
    ];

    /**
     * @var array<string,string>
     */
    protected array $patterns = [
        'i' => '\d+', // Integer : alias Digits
        'd' => '\d+', // Digits
        'h' => '[0-9a-fA-F]+', // Hexadecimal
        's' => '[\w\-]+', // Slug : letters, digits, underscores or hyphens
        '*' => '[^\/]+', // Whole parameter
        '**' => '.+', // Trailing parameters
    ];

    /**
     * A parameter is `{name}` or `{name:pattern}`; the pattern may contain
     * balanced braces (quantifiers) and escaped characters.
     *
     * @example '/{id:i}/{slug}/{year:[0-9]{4}}/{custom:[a-z\-]+-[0-9]+}'
     *       => '/(?P<id>\d+)/(?P<slug>[^\/]+)/(?P<year>[0-9]{4})/(?P<custom>[a-z\-]+-[0-9]+)'
     */
    protected const string REGEX_PARAM = '/\{([a-z_]\w*)(?::((?:[^{}\\\\]|\\\\.|\{(?2)\})*))?\}/i';
    protected const string REGEX_NAME = '/^[\w\'`#$%&*+.^|~!-]+$/';
    protected const string REGEX_UNESCAPED_DELIMITER = '/(?<!\\\\)((?:\\\\\\\\)*)#/';
    protected const string DEFAULT_PATTERN = '*';
    protected const string ROOT_PATH = '/';
    protected const string METHOD_GET = 'GET';
    protected const string METHOD_HEAD = 'HEAD';

    /**
     * @param class-string<RouteInterface> $routeClass Class name of RouteInterface implementation
     *
     * @throws RouterException
     */
    public function __construct(
        protected readonly string $routeClass = Route::class
    ) {
        if (!is_subclass_of($routeClass, RouteInterface::class)) {
            throw new RouterException('Route class must implement RouteInterface');
        }
    }

    #[\Override]
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    #[\Override]
    public function addAllowedMethod(string $method): static
    {
        $method = strtoupper(trim($method));

        if (in_array($method, $this->allowedMethods, true)) {
            throw new RouterException('Method (' . $method . ') already exists');
        }

        if (!preg_match(self::REGEX_NAME, $method)) {
            throw new RouterException('Method (' . $method . ') not valid');
        }

        $this->allowedMethods[] = $method;

        return $this;
    }

    #[\Override]
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    #[\Override]
    public function setBasePath(string $basePath): static
    {
        $basePath = trim($basePath, '/');
        $this->basePath = $basePath !== '' ? '/' . $basePath : '';

        return $this;
    }

    #[\Override]
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    #[\Override]
    public function addPattern(string $alias, string $regex): static
    {
        if (isset($this->patterns[$alias])) {
            throw new RouterException('Alias (' . $alias . ') already exists');
        }

        if (!preg_match(self::REGEX_NAME, $alias)) {
            throw new RouterException('Alias (' . $alias . ') not valid');
        }

        if (@preg_match('#' . $this->escapeDelimiter($regex) . '#', '') === false) {
            throw new RouterException('Regex (' . $regex . ') not valid');
        }

        $this->patterns[$alias] = $regex;

        return $this;
    }

    /**
     * A HEAD request falls back to the GET route of the same path when no
     * HEAD route is mapped (RFC 9110 §9.3.2).
     */
    #[\Override]
    public function match(RequestInterface $request): RouteInterface
    {
        $requestMethod = strtoupper($request->getMethod());

        if (!in_array($requestMethod, $this->allowedMethods, true)) {
            throw new MethodNotAllowedException('Method not allowed', 405, $this->allowedMethods);
        }

        $requestPath = $this->stripBasePath($request->getUri()->getPath());
        if ($requestPath === null) {
            throw new NotFoundException('Route not found', 404);
        }

        $matchedRoute = null;
        $matchedParams = [];
        /** @var array<string> $pathAllowedMethods Methods allowed for the matched path (for 405 Allow header) */
        $pathAllowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match('#^' . $route->getPath() . '$#D', $requestPath, $matches)) {
                continue;
            }

            $routeMethod = strtoupper($route->getMethod());
            $pathAllowedMethods[] = $routeMethod;

            if ($routeMethod === $requestMethod) {
                $matchedRoute = $route;
                $matchedParams = $matches;
                break;
            }

            if ($matchedRoute === null && $requestMethod === self::METHOD_HEAD && $routeMethod === self::METHOD_GET) {
                $matchedRoute = $route;
                $matchedParams = $matches;
            }
        }

        if ($matchedRoute === null && $pathAllowedMethods !== []) {
            if (in_array(self::METHOD_GET, $pathAllowedMethods, true)) {
                $pathAllowedMethods[] = self::METHOD_HEAD;
            }

            throw new MethodNotAllowedException(
                'Method not allowed',
                405,
                array_values(array_unique($pathAllowedMethods))
            );
        }

        if ($matchedRoute === null) {
            throw new NotFoundException('Route not found', 404);
        }

        $params = array_filter($matchedParams, 'is_string', ARRAY_FILTER_USE_KEY);
        $params = array_map('rawurldecode', $params);

        // The registered route is left untouched: each match gets its own copy.
        return (clone $matchedRoute)->setParams($params);
    }

    /**
     * @param string $name
     *
     * @return ?RouteInterface
     */
    #[\Override]
    public function matchByName(string $name): ?RouteInterface
    {
        $hash = $this->findHashByName($name);

        return $hash !== null ? $this->routes[$hash] : null;
    }

    /**
     * Parameters that are not part of the path are appended as a query string.
     *
     * @param ?string $name
     * @param array<string,mixed> $params
     *
     * @return string
     *
     * @throws RouterException When the route is unknown, or a path parameter is missing or invalid
     */
    #[\Override]
    public function url(?string $name, array $params = []): string
    {
        $hash = $name !== null ? $this->findHashByName($name) : null;
        if ($hash === null) {
            throw new RouterException('Route (' . $name . ') not found');
        }

        $path = $this->basePath;
        $offset = 0;
        $template = $this->templates[$hash];

        preg_match_all(self::REGEX_PARAM, $template, $placeholders, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($placeholders as $placeholder) {
            [$text, $position] = $placeholder[0];
            $param = $placeholder[1][0];
            $value = $params[$param] ?? '';
            $value = is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';

            if ($value === '') {
                throw new RouterException('Route (' . $name . '): parameter (' . $param . ') is missing');
            }

            $pattern = $this->escapeDelimiter($this->resolvePattern($placeholder[2][0] ?? ''));
            if (!preg_match('#^(?:' . $pattern . ')$#D', $value)) {
                throw new RouterException(
                    'Route (' . $name . '): parameter (' . $param . ') does not accept the value (' . $value . ')'
                );
            }

            // Slashes are kept so that a trailing parameter ({rest:**}) stays a path.
            $path .= substr($template, $offset, $position - $offset)
                . implode('/', array_map('rawurlencode', explode('/', $value)));
            $offset = $position + strlen($text);
            unset($params[$param]);
        }

        $path .= substr($template, $offset);
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $query !== '' ? $path . '?' . $query : $path;
    }

    #[\Override]
    public function getRoutes(): array
    {
        return $this->routes;
    }

    #[\Override]
    public function group(string $prefixPath, callable $callback, array $options = []): static
    {
        $previousPrefixPath = $this->prefixPath;
        $previousPrefixName = $this->prefixName;
        $this->prefixPath .= $prefixPath;
        $this->prefixName .= isset($options['name']) && is_string($options['name']) ? $options['name'] : '';

        try {
            $callback($this);
        } finally {
            $this->prefixPath = $previousPrefixPath;
            $this->prefixName = $previousPrefixName;
        }

        return $this;
    }

    #[\Override]
    public function map(
        array|string $method,
        string $path,
        callable|array|string $target,
        array $options = []
    ): static {
        $template = $this->prefixPath . $path;
        $path = $this->convertPathToRegex($template);

        if (@preg_match('#^' . $path . '$#', '') === false) {
            throw new RouterException('Path (' . $template . ') not valid');
        }

        if (!empty($options['name']) && is_string($options['name'])) {
            $options['name'] = $this->prefixName . $options['name'];
        }

        foreach ((array) $method as $m) {
            $m = strtoupper($m);

            if (!in_array($m, $this->allowedMethods, true)) {
                throw new RouterException('Method (' . $m . ') not allowed');
            }

            $hash = $this->generateRouteHash($m, $path);
            if (isset($this->routes[$hash])) {
                throw new RouterException('Route already exists');
            }

            $this->routes[$hash] = new $this->routeClass($m, $path, $target, $options);
            $this->templates[$hash] = $template;
        }

        return $this;
    }

    #[\Override]
    public function unmap(array|string $method, string $path): static
    {
        $path = $this->convertPathToRegex($this->prefixPath . $path);

        foreach ((array) $method as $m) {
            $hash = $this->generateRouteHash(strtoupper($m), $path);

            unset($this->routes[$hash], $this->templates[$hash]);
        }

        return $this;
    }

    #[\Override]
    public function unmapByName(string $name): static
    {
        foreach ($this->routes as $hash => $route) {
            if ($route->getOption('name') === $name) {
                unset($this->routes[$hash], $this->templates[$hash]);
            }
        }

        return $this;
    }

    /* --- */

    /**
     * Converts a path template to a regex: parameters become named groups,
     * everything else is matched literally.
     *
     * @param string $path
     *
     * @return string
     */
    protected function convertPathToRegex(string $path): string
    {
        $regex = '';
        $offset = 0;

        preg_match_all(self::REGEX_PARAM, $path, $params, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($params as $param) {
            [$placeholder, $position] = $param[0];
            $pattern = $this->escapeDelimiter($this->resolvePattern($param[2][0] ?? ''));

            $regex .= preg_quote(substr($path, $offset, $position - $offset), '#')
                . '(?P<' . $param[1][0] . '>' . $pattern . ')';
            $offset = $position + strlen($placeholder);
        }

        return $regex . preg_quote(substr($path, $offset), '#');
    }

    /**
     * Removes the base path from a request path.
     *
     * @return ?string Null when the path is outside the base path
     */
    protected function stripBasePath(string $path): ?string
    {
        if ($this->basePath === '') {
            return $path;
        }

        if ($path === $this->basePath) {
            return self::ROOT_PATH;
        }

        return str_starts_with($path, $this->basePath . '/') ? substr($path, strlen($this->basePath)) : null;
    }

    /**
     * Returns the regex of a parameter pattern: an alias, an inline regex, or
     * the default pattern when empty.
     */
    protected function resolvePattern(string $pattern): string
    {
        $pattern = $pattern !== '' ? $pattern : self::DEFAULT_PATTERN;

        return $this->patterns[$pattern] ?? $pattern;
    }

    /**
     * Escapes the regex delimiter (#) wherever it is not already escaped.
     */
    protected function escapeDelimiter(string $regex): string
    {
        return preg_replace(self::REGEX_UNESCAPED_DELIMITER, '$1\\#', $regex) ?? $regex;
    }

    protected function findHashByName(string $name): ?string
    {
        foreach ($this->routes as $hash => $route) {
            if ($route->getOption('name') === $name) {
                return $hash;
            }
        }

        return null;
    }

    /**
     * @param string $method
     * @param string $path
     *
     * @return string
     */
    protected function generateRouteHash(string $method, string $path): string
    {
        return sha1("$method $path");
    }
}
