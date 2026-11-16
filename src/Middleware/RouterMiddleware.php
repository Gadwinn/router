<?php

declare(strict_types=1);

namespace G\Router\Middleware;

use G\Router\Exception\RouterException;
use G\Router\RouterInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

class RouterMiddleware implements MiddlewareInterface
{
    protected const string ROUTE_ATTRIBUTE_NAME = 'route';

    /**
     * @param RouterInterface $router
     * @param callable(RouterException, ServerRequestInterface): ResponseInterface|null $onError
     *   Optional error handler. When provided, router exceptions (404, 405) are caught and
     *   passed to this callback instead of bubbling up the pipeline. The callback receives
     *   the exception and the current request, and must return a ResponseInterface.
     *   When null (default), exceptions propagate normally — suitable for PSR-15 pipelines
     *   with a dedicated error-handler middleware.
     */
    public function __construct(
        protected RouterInterface $router,
        protected mixed $onError = null,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws RouterException When no onError callback is set and routing fails.
     */
    #[\Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            $route = $this->router->match($request);
        } catch (RouterException $e) {
            if ($this->onError === null) {
                throw $e;
            }

            return ($this->onError)($e, $request);
        }

        $request = $request->withAttribute(self::ROUTE_ATTRIBUTE_NAME, $route);

        return $handler->handle($request);
    }
}
