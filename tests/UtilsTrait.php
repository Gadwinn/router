<?php

declare(strict_types=1);

namespace G\Router\Tests;

use Psr\Http\Message\{RequestInterface, ResponseInterface, ServerRequestInterface, StreamInterface, UriInterface};
use Psr\Http\Server\RequestHandlerInterface;

trait UtilsTrait
{
    /**
     * Returns a ServerRequestInterface stub pre-configured with the given method and URI path.
     *
     * Uses createStub() so no call-count expectations are enforced.
     * Tests that need expects() on the request must create their own mock with createMock().
     */
    public function serverRequestGenerator(
        string $method,
        string $uri,
        array $serverParams = []
    ): ServerRequestInterface {
        $uriStub = $this->createStub(UriInterface::class);
        $uriStub->method('getPath')->willReturn($uri);

        $streamStub = $this->createStub(StreamInterface::class);
        $streamStub->method('getContents')->willReturn('');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn($serverParams);
        $request->method('getCookieParams')->willReturn([]);
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getUploadedFiles')->willReturn([]);
        $request->method('getParsedBody')->willReturn(null);
        $request->method('getAttributes')->willReturn([]);
        $request->method('getRequestTarget')->willReturn('/');
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uriStub);
        $request->method('getProtocolVersion')->willReturn('1.1');
        $request->method('getHeaders')->willReturn([]);
        $request->method('getBody')->willReturn($streamStub);

        return $request;
    }

    /**
     * Returns a RequestInterface stub pre-configured with the given method and URI path.
     */
    public function requestGenerator(string $method, string $uri): RequestInterface
    {
        $uriStub = $this->createStub(UriInterface::class);
        $uriStub->method('getPath')->willReturn($uri);

        $streamStub = $this->createStub(StreamInterface::class);
        $streamStub->method('getContents')->willReturn('');

        $request = $this->createStub(RequestInterface::class);
        $request->method('getRequestTarget')->willReturn('/');
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uriStub);
        $request->method('getProtocolVersion')->willReturn('1.1');
        $request->method('getHeaders')->willReturn([]);
        $request->method('getBody')->willReturn($streamStub);

        return $request;
    }

    /**
     * Returns a ResponseInterface stub with the given status code and body.
     */
    public function responseGenerator(
        int $code = 200,
        string $reasonPhrase = '',
        string $body = ''
    ): ResponseInterface {
        $streamStub = $this->createStub(StreamInterface::class);
        $streamStub->method('getContents')->willReturn($body);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($code);
        $response->method('getReasonPhrase')->willReturn($reasonPhrase);
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getHeaders')->willReturn([]);
        $response->method('getBody')->willReturn($streamStub);

        return $response;
    }

    /**
     * Returns a RequestHandlerInterface stub that always returns a 200 response.
     *
     * Tests requiring call-count assertions (once(), never()) must create their
     * own handler with createMock(RequestHandlerInterface::class) directly.
     */
    public function requestHandlerGenerator(): RequestHandlerInterface
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->responseGenerator());

        return $handler;
    }
}


