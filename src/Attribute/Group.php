<?php

declare(strict_types=1);

namespace G\Router\Attribute;

use Attribute;

/**
 * Declares a controller class as a route group: the routes of its methods are mapped
 * under the path prefix and the name prefix of the group, as with RouterInterface::group().
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Group
{
    /**
     * @param string $path Path prefix of the routes of the class (e.g. '/users').
     * @param string $name Name prefix of the routes of the class (e.g. 'users.').
     * @param array<string,mixed> $options Other group options.
     */
    public function __construct(
        public readonly string $path = '',
        public readonly string $name = '',
        public readonly array $options = [],
    ) {
    }
}
