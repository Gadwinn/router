<?php

declare(strict_types=1);

namespace G\Router\Tests\Middlewares;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use G\Router\Tests\UtilsTrait;
use G\Router\Middlewares\RouteResolverMiddleware;
use G\Router\Routes\{Route, RouteInterface};
use G\Router\Exceptions\NotFoundException;

/**
 * Tests for RouteResolverMiddleware.
 *
 * This middleware sits downstream from RouterMiddleware in a PSR-15 pipeline.
 * It reads the pre-matched route from the 'route' request attribute and either:
 *   - delegates to the next handler when a valid RouteInterface is present, or
 *   - throws NotFoundException when the attribute is absent (no route was matched).
 *
 * Expected process() signature:
 *   public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
 */
final class RouteResolverMiddlewareTest extends TestCase
{
    use UtilsTrait;

    private RouteResolverMiddleware $middleware;

    public function setUp(): void
    {
        $this->middleware = new RouteResolverMiddleware();
    }

    // -----------------------------------------------------------------------
    // Happy path - route attribute is present
    // -----------------------------------------------------------------------

    /**
     * When a RouteInterface is stored in the 'route' attribute, process()
     * must delegate to the next handler exactly once.
     */
    public function testProcessCallsHandlerWhenRouteAttributeIsPresent(): void
    {
        $route = new Route('GET', '/test', fn() => 'test');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn($route);

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($this->responseGenerator());

        $this->middleware->process($request, $handler);
    }

    /**
     * process() must return the response produced by the next handler.
     */
    public function testProcessReturnsHandlerResponse(): void
    {
        $route = new Route('GET', '/test', fn() => 'test');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn($route);

        $expectedResponse = $this->responseGenerator(200);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $response = $this->middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * process() must pass the original request (unchanged) to the handler -
     * it has no reason to modify the request.
     */
    public function testProcessPassesRequestUnmodifiedToHandler(): void
    {
        $route = new Route('POST', '/submit', fn() => 'ok');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn($route);

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($this->responseGenerator());

        $this->middleware->process($request, $handler);
    }

    /**
     * The route object stored in the attribute remains accessible to handlers
     * further down the pipeline.
     */
    public function testRouteAttributeRemainsAccessibleToDownstreamHandlers(): void
    {
        $route = new Route('GET', '/items/{id:i}', fn() => 'item');
        $route->setParams(['id' => '7']);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn($route);

        $capturedRoute = null;

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willReturnCallback(
                function (ServerRequestInterface $req) use (&$capturedRoute) {
                    $capturedRoute = $req->getAttribute('route');
                    return $this->responseGenerator();
                }
            );

        $this->middleware->process($request, $handler);

        $this->assertSame($route, $capturedRoute);
        $this->assertSame('7', $capturedRoute->getParams()['id']);
    }

    // -----------------------------------------------------------------------
    // Error path - route attribute is absent
    // -----------------------------------------------------------------------

    /**
     * When the 'route' attribute is null (RouterMiddleware did not run or
     * matched nothing), process() must throw NotFoundException.
     */
    public function testProcessThrowsNotFoundExceptionWhenRouteAttributeIsNull(): void
    {
        $this->expectException(NotFoundException::class);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn(null);

        $handler = $this->requestHandlerGenerator();

        $this->middleware->process($request, $handler);
    }

    /**
     * When NotFoundException is thrown, the next handler must never be invoked.
     */
    public function testHandlerIsNotCalledWhenRouteAttributeIsMissing(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn(null);

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        try {
            $this->middleware->process($request, $handler);
        } catch (NotFoundException) {
            // Expected.
        }
    }

    /**
     * When the 'route' attribute exists but is not a RouteInterface instance,
     * process() must throw NotFoundException rather than passing invalid data
     * downstream.
     */
    public function testProcessThrowsNotFoundExceptionWhenRouteAttributeIsNotRouteInterface(): void
    {
        $this->expectException(NotFoundException::class);

        $request = $this->createStub(ServerRequestInterface::class);
        // Simulate a corrupted attribute (e.g. a string stored by mistake).
        $request->method('getAttribute')->willReturn('not-a-route');

        $handler = $this->requestHandlerGenerator();

        $this->middleware->process($request, $handler);
    }
}

