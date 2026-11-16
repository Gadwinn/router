<?php

declare(strict_types=1);

namespace G\Router\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use G\Router\Route\RouteInterface;
use G\Router\Exception\NotFoundException;

class RouteResolverMiddleware implements MiddlewareInterface
{
    /**
     * Verify that a route has been matched and stored in the request attributes
     * by RouterMiddleware, then delegate to the next handler.
     *
     * @throws NotFoundException When no valid RouteInterface is found in the 'route' attribute.
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute('route');

        if (!$route instanceof RouteInterface) {
            throw new NotFoundException('No route found in request attributes', 404);
        }

        return $handler->handle($request);
    }
}
