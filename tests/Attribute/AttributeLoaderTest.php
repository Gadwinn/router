<?php

declare(strict_types=1);

namespace G\Router\Tests\Attribute;

use G\Router\Attribute\AttributeLoader;
use G\Router\Exception\RouterException;
use G\Router\Route\RouteInterface;
use G\Router\Router;
use G\Router\Tests\Fixtures\Controller\Admin\SettingsController;
use G\Router\Tests\Fixtures\Controller\{HomeController, UserController};
use G\Router\Tests\Fixtures\PrivateRouteController;
use PHPUnit\Framework\TestCase;

final class AttributeLoaderTest extends TestCase
{
    private const string DIRECTORY = __DIR__ . '/../Fixtures/Controller';
    private const string NAMESPACE = 'G\Router\Tests\Fixtures\Controller';

    private Router $router;
    private AttributeLoader $loader;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->loader = new AttributeLoader($this->router);
    }

    /**
     * @return list<string> "METHOD path" of every route, sorted
     */
    private function routes(): array
    {
        $routes = array_map(
            static fn (RouteInterface $r): string => $r->getMethod() . ' ' . $r->getPath(),
            array_values($this->router->getRoutes())
        );
        sort($routes);

        return $routes;
    }

    public function testGroupPrefixesPathsAndNames(): void
    {
        $this->loader->loadClass(UserController::class);

        $this->assertSame('/users', $this->router->url('users.index'));
        $this->assertSame('/users/42', $this->router->url('users.show', ['id' => 42]));
        $this->assertSame('/users/42/profile', $this->router->url('users.profile', ['id' => 42]));
    }

    public function testTargetIsTheControllerMethod(): void
    {
        $this->loader->loadClass(UserController::class);

        $this->assertSame([UserController::class, 'show'], $this->router->matchByName('users.show')?->getTarget());
        $this->assertSame([UserController::class, 'show'], $this->router->matchByName('users.profile')?->getTarget());
    }

    public function testMethodsAndRepeatedAttributes(): void
    {
        $this->loader->loadClass(UserController::class);

        $this->assertCount(5, $this->router->getRoutes());
        $methods = array_map(static fn (RouteInterface $r): string => $r->getMethod(), $this->router->getRoutes());
        sort($methods);
        $this->assertSame(['GET', 'GET', 'GET', 'PATCH', 'PUT'], array_values($methods));
    }

    public function testClassWithoutGroupAndRouteOptions(): void
    {
        $this->loader->loadClass(HomeController::class);

        $route = $this->router->matchByName('home');
        $this->assertSame([HomeController::class, '__invoke'], $route?->getTarget());
        $this->assertSame('wide', $route->getOption('layout'));
        $this->assertSame('/', $this->router->url('home'));
    }

    public function testGroupDoesNotLeakOnTheNextClass(): void
    {
        $this->loader->loadClass(UserController::class, HomeController::class);

        $this->assertSame('/', $this->router->url('home'));
    }

    public function testLoadDirectoryIsRecursiveAndSkipsWhatIsNotAController(): void
    {
        $this->loader->loadDirectory(self::DIRECTORY, self::NAMESPACE);

        $this->assertCount(7, $this->router->getRoutes());
        $this->assertSame('/admin/settings/security', $this->router->url('admin.security'));
        $this->assertSame([SettingsController::class, 'security'], $this->router->matchByName('admin.security')?->getTarget());
        $this->assertNotContains('GET /abstract', $this->routes());
    }

    public function testLoadDirectoryAcceptsNamespaceWithBackslashes(): void
    {
        $this->loader->loadDirectory(self::DIRECTORY . '/', '\\' . self::NAMESPACE . '\\');

        $this->assertCount(7, $this->router->getRoutes());
    }

    public function testLoadDirectoryWithTheWrongNamespaceMapsNothing(): void
    {
        $this->loader->loadDirectory(self::DIRECTORY, 'Nope');

        $this->assertSame([], $this->router->getRoutes());
    }

    public function testUnknownDirectory(): void
    {
        $this->expectException(RouterException::class);
        $this->loader->loadDirectory(__DIR__ . '/missing', self::NAMESPACE);
    }

    public function testUnknownClass(): void
    {
        $this->expectException(RouterException::class);
        $this->loader->loadClass('G\Router\Tests\Fixtures\Missing'); // @phpstan-ignore argument.type
    }

    public function testRouteOnAPrivateMethod(): void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('PrivateRouteController::hidden()');
        $this->loader->loadClass(PrivateRouteController::class);
    }
}
