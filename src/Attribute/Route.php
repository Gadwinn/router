<?php

declare(strict_types=1);

namespace G\Router\Attribute;

use Attribute;

/**
 * Maps a controller method to a route. Repeatable: a method can answer several routes.
 * Inside a class carrying a Group attribute, path and name are relative to the group.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /**
     * @param string $path Path of the route (e.g. '/{id:i}').
     * @param string[]|string $methods HTTP method(s).
     * @param string $name Name of the route, for RouterInterface::url().
     * @param array<string,mixed> $options Other route options.
     */
    public function __construct(
        public readonly string $path,
        public readonly array|string $methods = 'GET',
        public readonly string $name = '',
        public readonly array $options = [],
    ) {
    }
}
