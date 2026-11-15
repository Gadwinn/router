<?php

declare(strict_types=1);

namespace G\Router\Tests\Routes;

use PHPUnit\Framework\TestCase;
use G\Router\Tests\UtilsTrait;
use G\Router\Routes\Route;

final class RouteTest extends TestCase
{
    use UtilsTrait;

    protected Route $routeHome;
    protected Route $routeCAP;
    /**
     * GET /
     */
    protected $requestHome;
    /**
     * POST /controller/action/param1/param2
     */
    protected $requestCAP;

    public function setUp(): void
    {
        $this->requestHome = $this->requestGenerator('GET', '/');

        $this->routeHome = new Route(
            'GET',
            '/',
            fn () => 'target',
            ['name' => 'home']
        );

        $this->requestCAP = $this->requestGenerator('POST', '/controller/action/value1/value2');

        $this->routeCAP = new Route(
            'POST',
            '/controller/action/(?P<param1>[^/]+)/(?P<param2>[\w]+)',
            fn () => 'target',
            ['name' => 'cap']
        );
    }

    public function testGet(): void
    {
        $this->assertEquals('GET', $this->routeHome->getMethod());
        $this->assertEquals('/', $this->routeHome->getPath());
        $this->assertIsCallable($this->routeHome->getTarget());
        $this->assertEquals(['name' => 'home'], $this->routeHome->getOptions());
        $this->assertEquals('home', $this->routeHome->getOption('name'));
        $this->assertEquals('no option', $this->routeHome->getOption('not-found', 'no option'));
    }

    public function testParams(): void
    {
        $this->assertEquals([], $this->routeCAP->getParams());
        $this->routeCAP->setParams(['param1' => 'value1', 'param2' => 'value2']);
        $this->assertEquals(['param1' => 'value1', 'param2' => 'value2'], $this->routeCAP->getParams());
    }
}
