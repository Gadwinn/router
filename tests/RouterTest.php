<?php

declare(strict_types=1);

namespace G\Router\Tests;

use PHPUnit\Framework\TestCase;
use G\Router\Tests\UtilsTrait;
use G\Router\{Router, RouterInterface};
use G\Router\Exceptions\{MethodNotAllowedException, NotFoundException, RouterException};
use G\Router\Routes\{Route, RouteInterface};

final class RouterTest extends TestCase
{
    use UtilsTrait;

    protected Router $router;

    public function setUp(): void
    {
        $this->router = new Router();
    }

    public function testConstruct(): void
    {
        $this->assertInstanceOf(RouterInterface::class, $this->router);
    }

    public function testConstructWithRouteClass(): void
    {
        $router = new Router(Route::class);
        $this->assertInstanceOf(RouterInterface::class, $router);
    }

    public function testConstructWithRouteClassException(): void
    {
        $this->expectException(RouterException::class);
        new Router('InvalidRoute');
    }

    public function testAllowedMethods(): void
    {
        $this->router->addAllowedMethod('TEST');
        $this->assertContainsEquals('TEST', $this->router->getAllowedMethods());
    }

    public function testAllowedMethodsTwice(): void
    {
        $this->expectException(RouterException::class);
        $this->router->addAllowedMethod('TEST');
        $this->router->addAllowedMethod('TEST');
    }

    public function testAllowedMethodsExecption(): void
    {
        $this->expectException(RouterException::class);
        $this->router->addAllowedMethod("TEST TEST");
    }

    public function testPatterns(): void
    {
        $this->router->addPattern('test', '[a-z]+');
        $this->assertArrayHasKey('test', $this->router->getPatterns());
    }

    public function testPatternsExecptionAlreadyExists(): void
    {
        $this->expectException(RouterException::class);
        $this->router->addPattern('test', '[a-z]');
        $this->router->addPattern('test', '[a-z]');
    }

    public function testPatternsExecptionAlias(): void
    {
        $this->expectException(RouterException::class);
        $this->router->addPattern('test test', '[a-z]');
    }

    public function testPatternsExecptionRegex(): void
    {
        $this->expectException(RouterException::class);
        $this->router->addPattern('test', '[a-z');
    }

    public function testMap(): void
    {
        $this->router->map('GET', '/', 'target');

        $routes = $this->router->getRoutes();
        $this->assertCount(1, $routes);
    }

    public function testMapTrait(): void
    {
        $this->router->any('/any', 'target');
        $this->router->get('/uri', 'target');
        $this->router->post('/uri', 'target');
        $this->router->put('/uri', 'target');
        $this->router->patch('/uri', 'target');
        $this->router->delete('/uri', 'target');
        $this->router->connect('/uri', 'target');
        $this->router->head('/uri', 'target');
        $this->router->options('/uri', 'target');
        $this->router->trace('/uri', 'target');

        $routes = $this->router->getRoutes();
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'CONNECT', 'HEAD', 'OPTIONS', 'TRACE'] as $method) {
            $this->assertCount(2, array_filter($routes, fn ($route) => $route->getMethod() === $method));
        }
    }

    public function testMapAlreadyExists(): void
    {
        $this->expectException(RouterException::class);
        $this->router->map('GET', '/', 'target');
        $this->router->map('GET', '/', 'target');
    }

    public function testMapInvalidMethod(): void
    {
        $this->expectException(RouterException::class);
        $this->router->map('INVALID', '/', 'target');
    }

    public function testUnmap(): void
    {
        $this->router->map('GET', '/', 'target');
        $this->router->map('POST', '/', 'target');
        $this->router->map('GET', '/uri', 'target');

        $this->router->unmap('GET', '/');
        $this->assertCount(2, $this->router->getRoutes());
    }

    public function testUnmapNotFound(): void
    {
        $this->router->map('GET', '/', 'target');
        $this->router->map('POST', '/', 'target');
        $this->router->map('GET', '/uri', 'target');

        $this->router->unmap('GET', '/invalid');
        $this->router->unmap('DELETE', '/');
        $this->router->unmap('INVALID', '/');
        $this->assertCount(3, $this->router->getRoutes());
    }
    
    public function testUnmapByName(): void
    {
        $this->router->map('GET', '/', 'target', ['name' => 'target']);
        $this->router->map('POST', '/', 'target', ['name' => 'target']);
        $this->router->map('GET', '/uri', 'target', ['name' => 'target']);

        $this->router->unmapByName('target');
        $this->assertCount(0, $this->router->getRoutes());
    }

    public function testMatch(): void
    {
        $this->router->map('GET', '/', 'target');
        $this->router->map('GET', '/uri', 'target');
        $this->router->map('GET', '/uri/{id:i}/{slug:s}', 'target');
        $this->router->map('GET', '/uri/{id:i}/{slug:s}/{custom:[a-z\-]+}', 'target');
        $this->router->map('GET', '/uri/{id:i}/{slug:s}/{custom:[a-z\-]+}-{customid:[0-9]+}', 'target');;

        $this->assertEquals(
            'target',
            $this->router->match($this->requestGenerator('GET', '/'))->getTarget()
        );
        $this->assertEquals(
            'target',
            $this->router->match($this->requestGenerator('GET', '/uri'))->getTarget()
        );
        $this->assertEquals(
            'target',
            $this->router->match($this->requestGenerator('GET', '/uri/1/slug'))->getTarget()
        );
        $this->assertEquals(
            'target',
            $this->router->match($this->requestGenerator('GET', '/uri/1/slug/custom'))->getTarget()
        );
        $this->assertEquals(
            'target',
            $this->router->match($this->requestGenerator('GET', '/uri/1/slug/custom-1'))->getTarget()
        );
    }

    public function testMatchNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->router->match($this->requestGenerator('GET', '/'));
    }

    public function testMatchMethodNotAllowed(): void
    {
        $this->expectException(MethodNotAllowedException::class);
        $this->router->match($this->requestGenerator('INVALID', '/'));
    }

    public function testMatchMethodNotAllowedForPath(): void
    {
        $this->router->map('GET', '/', 'target');
        $this->expectException(MethodNotAllowedException::class);
        $this->router->match($this->requestGenerator('POST', '/'));
    }

    public function testMatchMethodNotAllowedForPathCarriesAllowedMethods(): void
    {
        $this->router->map('GET', '/', 'target');
        $this->router->map('POST', '/', 'target2');

        try {
            $this->router->match($this->requestGenerator('DELETE', '/'));
            $this->fail('MethodNotAllowedException expected');
        } catch (MethodNotAllowedException $e) {
            $this->assertContains('GET', $e->getAllowedMethods());
            $this->assertContains('POST', $e->getAllowedMethods());
            $this->assertNotContains('DELETE', $e->getAllowedMethods());
        }
    }

    public function testMatchMethodNotAllowedCarriesRouterAllowedMethods(): void
    {
        try {
            $this->router->match($this->requestGenerator('INVALID', '/'));
            $this->fail('MethodNotAllowedException expected');
        } catch (MethodNotAllowedException $e) {
            $this->assertNotEmpty($e->getAllowedMethods());
            $this->assertContains('GET', $e->getAllowedMethods());
        }
    }

    public function testGroup(): void
    {
        $this->router->group('/prefix', function (RouterInterface $router) {
            $this->router->group('/subprefix', function (RouterInterface $router) {
                $router->map('GET', '/uri', 'subtarget', ['name' => 'subtarget']);
            }, ['name' => 'subgroup.']);

            $this->router->group('/subprefix2', function (RouterInterface $router) {
                $router->map('GET', '/uri', 'subtarget2');
            }, ['name' => 'subgroup2']);

            $router->map('GET', '/uri', 'target', ['name' => 'target']);
        }, ['name' => 'group.']);

        $uri = $this->requestGenerator('GET', '/prefix/uri');
        $this->assertEquals('target', $this->router->match($uri)->getTarget());
        $this->assertEquals('group.target', $this->router->match($uri)->getOption('name'));

        $uri = $this->requestGenerator('GET', '/prefix/subprefix/uri');
        $this->assertEquals('subtarget', $this->router->match($uri)->getTarget());
        $this->assertEquals('group.subgroup.subtarget', $this->router->match($uri)->getOption('name'));

        $uri = $this->requestGenerator('GET', '/prefix/subprefix2/uri');
        $this->assertEquals('subtarget2', $this->router->match($uri)->getTarget());
        $this->assertEquals(null, $this->router->match($uri)->getOption('name'));
    }

    public function testMatchByName(): void
    {
        $this->router->map('GET', '/target', 'target', ['name' => 'target']);

        $this->assertEquals('/target', $this->router->matchByName('target')->getPath());
    }

    public function testMatchByNameNotFound(): void
    {
        $this->assertNull($this->router->matchByName('target'));
    }

    public function testUrl(): void
    {
        $this->router->map('GET', '/target', 'target', ['name' => 'target']);
        $this->assertEquals('/target', $this->router->url('target'));

        $this->router->map('GET', '/target/{id:i}/{slug:s}', 'target', ['name' => 'target.slug']);
        $this->assertEquals('/target/1/slug', $this->router->url('target.slug', ['id' => 1, 'slug' => 'slug']));
    }

    public function testUrlNameNull(): void
    {
        $this->assertEmpty($this->router->url(null));
    }

    public function testUrlNotFound(): void
    {
        $this->assertEmpty($this->router->url('target'));
    }

    public function testUrlInvalidParams(): void
    {
        $this->router->map('GET', '/target/{id:i}/{slug:s}', 'target', ['name' => 'target']);
        $this->assertEmpty($this->router->url('target', ['id' => 'id', 'slug' => 'slug']));
        $this->assertEmpty($this->router->url('target', ['id' => 1]));
        $this->assertEmpty($this->router->url('target', ['id' => 1, 'slug' => 'slug', 'custom' => 'custom']));
    }

    // -------------------------------------------------------------------------
    // Parameters
    // -------------------------------------------------------------------------

    public function testParameterWithoutPatternMatchesOneSegment(): void
    {
        $this->router->get('/users/{name}', 'target');

        $this->assertSame(
            ['name' => 'alice'],
            $this->router->match($this->requestGenerator('GET', '/users/alice'))->getParams()
        );

        $this->expectException(NotFoundException::class);
        $this->router->match($this->requestGenerator('GET', '/users/alice/posts'));
    }

    public function testInlinePatternWithQuantifier(): void
    {
        $this->router->get('/articles/{year:[0-9]{4}}/{slug:s}', 'target');

        $this->assertSame(
            ['year' => '2024', 'slug' => 'hello'],
            $this->router->match($this->requestGenerator('GET', '/articles/2024/hello'))->getParams()
        );

        $this->expectException(NotFoundException::class);
        $this->router->match($this->requestGenerator('GET', '/articles/24/hello'));
    }

    public function testAliasPatternWithQuantifier(): void
    {
        $this->router->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
        $this->router->get('/orders/{id:uuid}', 'target', ['name' => 'order']);
        $uuid = '123e4567-e89b-12d3-a456-426614174000';

        $this->assertSame(
            ['id' => $uuid],
            $this->router->match($this->requestGenerator('GET', '/orders/' . $uuid))->getParams()
        );
        $this->assertSame('/orders/' . $uuid, $this->router->url('order', ['id' => $uuid]));
    }

    public function testTrailingParameter(): void
    {
        $this->router->get('/files/{path:**}', 'target', ['name' => 'file']);

        $this->assertSame(
            ['path' => 'a/b c/d.txt'],
            $this->router->match($this->requestGenerator('GET', '/files/a/b%20c/d.txt'))->getParams()
        );
        $this->assertSame('/files/a/b%20c/d.txt', $this->router->url('file', ['path' => 'a/b c/d.txt']));
    }

    public function testParametersAreUrlDecodedButPlusIsKept(): void
    {
        $this->router->get('/tags/{tag}', 'target');

        $this->assertSame(
            ['tag' => 'c++ été'],
            $this->router->match($this->requestGenerator('GET', '/tags/c++%20%C3%A9t%C3%A9'))->getParams()
        );
    }

    public function testStaticPartsAreMatchedLiterally(): void
    {
        $this->router->get('/file.json', 'dot');
        $this->router->get('/a#b(c)+', 'special');

        $this->assertSame('dot', $this->router->match($this->requestGenerator('GET', '/file.json'))->getTarget());
        $this->assertSame('special', $this->router->match($this->requestGenerator('GET', '/a#b(c)+'))->getTarget());

        $this->expectException(NotFoundException::class);
        $this->router->match($this->requestGenerator('GET', '/fileXjson'));
    }

    public function testPatternContainingTheRegexDelimiter(): void
    {
        $this->router->get('/anchor/{name:[a-z#]+}', 'target');

        $this->assertSame(
            ['name' => 'a#b'],
            $this->router->match($this->requestGenerator('GET', '/anchor/a#b'))->getParams()
        );
    }

    public function testInvalidInlinePatternThrowsWhenMapped(): void
    {
        $this->expectException(RouterException::class);
        $this->router->get('/users/{id:[0-9}', 'target');
    }

    public function testPathsAreCaseSensitive(): void
    {
        $this->router->get('/Users', 'upper');
        $this->router->get('/users', 'lower');

        $this->assertSame('upper', $this->router->match($this->requestGenerator('GET', '/Users'))->getTarget());
        $this->assertSame('lower', $this->router->match($this->requestGenerator('GET', '/users'))->getTarget());
    }

    // -------------------------------------------------------------------------
    // Matching
    // -------------------------------------------------------------------------

    public function testMatchDoesNotAlterTheRegisteredRoute(): void
    {
        $this->router->get('/users/{id:i}', 'target', ['name' => 'user']);

        $first = $this->router->match($this->requestGenerator('GET', '/users/1'));
        $second = $this->router->match($this->requestGenerator('GET', '/users/2'));

        $this->assertSame(['id' => '1'], $first->getParams());
        $this->assertSame(['id' => '2'], $second->getParams());
        $this->assertSame([], $this->router->matchByName('user')?->getParams());
    }

    public function testHeadFallsBackToTheGetRoute(): void
    {
        $this->router->get('/users/{id:i}', 'get-target');

        $route = $this->router->match($this->requestGenerator('HEAD', '/users/7'));

        $this->assertSame('get-target', $route->getTarget());
        $this->assertSame(['id' => '7'], $route->getParams());
    }

    public function testExplicitHeadRouteWinsOverTheGetRoute(): void
    {
        $this->router->get('/users', 'get-target');
        $this->router->head('/users', 'head-target');

        $this->assertSame(
            'head-target',
            $this->router->match($this->requestGenerator('HEAD', '/users'))->getTarget()
        );
    }

    public function testHeadIsNotAllowedWithoutAGetRoute(): void
    {
        $this->router->post('/users', 'target');

        try {
            $this->router->match($this->requestGenerator('HEAD', '/users'));
            $this->fail('MethodNotAllowedException expected');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(['POST'], $e->getAllowedMethods());
        }
    }

    public function testAllowedMethodsIncludeHeadWhenGetIsMapped(): void
    {
        $this->router->get('/users', 'target');
        $this->router->get('/{any}', 'catch-all');

        try {
            $this->router->match($this->requestGenerator('DELETE', '/users'));
            $this->fail('MethodNotAllowedException expected');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(['GET', 'HEAD'], $e->getAllowedMethods());
        }
    }

    // -------------------------------------------------------------------------
    // URL generation
    // -------------------------------------------------------------------------

    public function testUrlEncodesParameters(): void
    {
        $this->router->get('/tags/{tag}', 'target', ['name' => 'tag']);

        $this->assertSame('/tags/c%2B%2B%20%C3%A9t%C3%A9', $this->router->url('tag', ['tag' => 'c++ été']));
    }

    public function testUrlWithAlternationAndLiteralCharacters(): void
    {
        $this->router->get('/export.{format:(json|xml)}', 'target', ['name' => 'export']);

        $this->assertSame('/export.json', $this->router->url('export', ['format' => 'json']));
        $this->assertSame('', $this->router->url('export', ['format' => 'jsonx']));
        $this->assertSame('', $this->router->url('export', ['format' => 'csv']));
    }

    public function testUrlInsideAGroup(): void
    {
        $this->router->group('/api/{version:i}', function (RouterInterface $router): void {
            $router->get('/users/{id:i}', 'target', ['name' => 'show']);
        }, ['name' => 'api.']);

        $this->assertSame('/api/2/users/42', $this->router->url('api.show', ['version' => '2', 'id' => '42']));
    }

    public function testUrlAfterUnmap(): void
    {
        $this->router->get('/users', 'target', ['name' => 'users']);
        $this->router->unmap('GET', '/users');

        $this->assertSame('', $this->router->url('users'));
    }

    // -------------------------------------------------------------------------
    // Groups
    // -------------------------------------------------------------------------

    public function testGroupPrefixIsRestoredWhenTheCallbackThrows(): void
    {
        try {
            $this->router->group('/admin', function (): void {
                throw new \RuntimeException('boom');
            }, ['name' => 'admin.']);
        } catch (\RuntimeException) {
            // expected
        }

        $this->router->get('/home', 'target', ['name' => 'home']);

        $this->assertSame('/home', $this->router->url('home'));
    }

    public function testUnmapInsideAGroup(): void
    {
        $this->router->group('/admin', function (RouterInterface $router): void {
            $router->get('/users', 'target');
            $router->unmap('GET', '/users');
        });

        $this->assertCount(0, $this->router->getRoutes());
    }
}
