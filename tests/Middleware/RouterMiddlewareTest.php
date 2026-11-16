<?php

declare(strict_types=1);

namespace G\Router\Tests\Middleware;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ServerRequestInterface, UriInterface};
use Psr\Http\Server\RequestHandlerInterface;
use G\Router\Tests\UtilsTrait;
use G\Router\Middleware\RouterMiddleware;
use G\Router\Router;
use G\Router\Route\RouteInterface;
use G\Router\Exception\{MethodNotAllowedException, NotFoundException, RouterException};
use Psr\Http\Message\ResponseInterface;

/**
 * Tests for RouterMiddleware.
 *
 * Covers the PSR-15 contract:
 * - the matched route is stored as the 'route' request attribute,
 * - the next handler receives the modified request,
 * - NotFoundException / MethodNotAllowedException propagate unchanged.
 */
final class RouterMiddlewareTest extends TestCase
{
    use UtilsTrait;

    private Router $router;
    private RouterMiddleware $middleware;

    public function setUp(): void
    {
        $this->router = new Router();
        $this->middleware = new RouterMiddleware($this->router);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    /**
     * process() must call withAttribute('route', <RouteInterface>) on the
     * incoming request exactly once.
     */
    public function testProcessSetsRouteAttributeOnRequest(): void
    {
        $this->router->get('/test', fn() => 'test');

        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/test');

        /** @var ServerRequestInterface&MockObject $request */
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->atLeastOnce())->method('getMethod')->willReturn('GET');
        $request->expects($this->atLeastOnce())->method('getUri')->willReturn($uri);

        $modifiedRequest = $this->createStub(ServerRequestInterface::class);

        $request->expects($this->once())
            ->method('withAttribute')
            ->with(
                $this->identicalTo('route'),
                $this->isInstanceOf(RouteInterface::class)
            )
            ->willReturn($modifiedRequest);

        $handler = $this->requestHandlerGenerator();

        $this->middleware->process($request, $handler);
    }

    /**
     * process() must pass the modified request (containing the route attribute)
     * to the next handler, not the original request.
     */
    public function testProcessCallsHandlerWithModifiedRequest(): void
    {
        $this->router->get('/test', fn() => 'test');

        /** @var ServerRequestInterface&MockObject $request */
        $request = $this->serverRequestGenerator('GET', '/test');
        $modifiedRequest = $this->createStub(ServerRequestInterface::class);
        $request->method('withAttribute')->willReturn($modifiedRequest);

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($modifiedRequest))
            ->willReturn($this->responseGenerator());

        $this->middleware->process($request, $handler);
    }

    /**
     * process() must return the response produced by the next handler.
     */
    public function testProcessReturnsHandlerResponse(): void
    {
        $this->router->get('/test', fn() => 'test');

        /** @var ServerRequestInterface&MockObject $request */
        $request = $this->serverRequestGenerator('GET', '/test');
        $request->method('withAttribute')->willReturn($request);

        $expectedResponse = $this->responseGenerator(200);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $response = $this->middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * The route stored in the request attribute must be the one matched by the
     * router (correct path and method).
     */
    public function testProcessRouteAttributeContainsMatchedRoute(): void
    {
        $this->router->get('/users/{id:i}', fn() => 'user', ['name' => 'users.show']);

        /** @var ServerRequestInterface&MockObject $request */
        $request = $this->serverRequestGenerator('GET', '/users/42');

        $capturedRoute = null;
        $request->method('withAttribute')
            ->willReturnCallback(
                function (string $name, mixed $value) use ($request, &$capturedRoute) {
                    if ($name === 'route') {
                        $capturedRoute = $value;
                    }
                    return $request;
                }
            );

        $handler = $this->requestHandlerGenerator();
        $this->middleware->process($request, $handler);

        $this->assertInstanceOf(RouteInterface::class, $capturedRoute);
        $this->assertSame('42', $capturedRoute->getParams()['id']);
    }

    // -----------------------------------------------------------------------
    // Error propagation
    // -----------------------------------------------------------------------

    /**
     * process() must let NotFoundException bubble up when no route matches
     * the request path.
     */
    public function testProcessThrowsNotFoundExceptionWhenNoRouteMatches(): void
    {
        $this->expectException(NotFoundException::class);

        /** @var ServerRequestInterface&MockObject $request */
        $request = $this->serverRequestGenerator('GET', '/not-found');
        $handler = $this->requestHandlerGenerator();

        $this->middleware->process($request, $handler);
    }

    /**
     * process() must let MethodNotAllowedException bubble up when the path is
     * known but the HTTP method is not registered for it.
     */
    public function testProcessThrowsMethodNotAllowedExceptionWhenMethodNotAllowed(): void
    {
        $this->router->get('/test', fn() => 'test');

        $this->expectException(MethodNotAllowedException::class);

        /** @var ServerRequestInterface&MockObject $request */
        $request = $this->serverRequestGenerator('POST', '/test');
        $handler = $this->requestHandlerGenerator();

        $this->middleware->process($request, $handler);
    }

    /**
     * When an exception is thrown, the next handler must never be called.
     */
    public function testHandlerIsNotCalledOnException(): void
    {
        // No routes registered → NotFoundException
        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        /** @var ServerRequestInterface&MockObject $request */
        $request = $this->serverRequestGenerator('GET', '/missing');

        try {
            $this->middleware->process($request, $handler);
        } catch (NotFoundException) {
            // Expected - the important assertion is above.
        }
    }

    // -----------------------------------------------------------------------
    // onError callback
    // -----------------------------------------------------------------------

    /**
     * When onError is set and a NotFoundException occurs, the callback must be
     * invoked and its response returned instead of throwing.
     */
    public function testOnErrorCallbackCalledOnNotFoundException(): void
    {
        $errorResponse = $this->responseGenerator(404);
        $callbackCalled = false;

        $middleware = new RouterMiddleware(
            router: $this->router,
            onError: function (RouterException $e, ServerRequestInterface $req) use ($errorResponse, &$callbackCalled): ResponseInterface {
                $callbackCalled = true;
                return $errorResponse;
            }
        );

        $request = $this->serverRequestGenerator('GET', '/not-found');
        $handler = $this->requestHandlerGenerator();

        $response = $middleware->process($request, $handler);

        $this->assertTrue($callbackCalled);
        $this->assertSame($errorResponse, $response);
    }

    /**
     * When onError is set and a MethodNotAllowedException occurs, the callback must be
     * invoked and its response returned instead of throwing.
     */
    public function testOnErrorCallbackCalledOnMethodNotAllowedException(): void
    {
        $this->router->get('/test', fn() => 'test');

        $errorResponse = $this->responseGenerator(405);
        $callbackCalled = false;

        $middleware = new RouterMiddleware(
            router: $this->router,
            onError: function (RouterException $e, ServerRequestInterface $req) use ($errorResponse, &$callbackCalled): ResponseInterface {
                $callbackCalled = true;
                return $errorResponse;
            }
        );

        $request = $this->serverRequestGenerator('POST', '/test');
        $handler = $this->requestHandlerGenerator();

        $response = $middleware->process($request, $handler);

        $this->assertTrue($callbackCalled);
        $this->assertSame($errorResponse, $response);
    }

    /**
     * The onError callback must receive the correct exception and request instances.
     */
    public function testOnErrorCallbackReceivesCorrectExceptionAndRequest(): void
    {
        $this->router->get('/test', fn() => 'test');

        $capturedEx = null;
        $capturedReq = null;

        $request = $this->serverRequestGenerator('POST', '/test');

        $middleware = new RouterMiddleware(
            router: $this->router,
            onError: function (RouterException $e, ServerRequestInterface $req) use (&$capturedEx, &$capturedReq): ResponseInterface {
                $capturedEx = $e;
                $capturedReq = $req;
                return $this->responseGenerator(405);
            }
        );

        $middleware->process($request, $this->requestHandlerGenerator());

        $this->assertInstanceOf(MethodNotAllowedException::class, $capturedEx);
        $this->assertSame($request, $capturedReq);
        $this->assertContains('GET', $capturedEx->getAllowedMethods());
    }

    /**
     * When onError handles the exception, the next handler must never be called.
     */
    public function testHandlerIsNotCalledWhenOnErrorHandlesException(): void
    {
        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $middleware = new RouterMiddleware(
            router: $this->router,
            onError: fn(RouterException $e, ServerRequestInterface $req): ResponseInterface => $this->responseGenerator(404)
        );

        $request = $this->serverRequestGenerator('GET', '/missing');
        $middleware->process($request, $handler);
    }

    /**
     * When onError is set and routing succeeds, the handler must be called normally.
     */
    public function testOnErrorCallbackNotCalledOnSuccess(): void
    {
        $this->router->get('/test', fn() => 'test');

        $callbackCalled = false;

        $request = $this->serverRequestGenerator('GET', '/test');
        $request->method('withAttribute')->willReturn($request);

        $middleware = new RouterMiddleware(
            router: $this->router,
            onError: function (RouterException $e, ServerRequestInterface $req) use (&$callbackCalled): ResponseInterface {
                $callbackCalled = true;
                return $this->responseGenerator(500);
            }
        );

        $expectedResponse = $this->responseGenerator(200);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $response = $middleware->process($request, $handler);

        $this->assertFalse($callbackCalled);
        $this->assertSame($expectedResponse, $response);
    }
}
