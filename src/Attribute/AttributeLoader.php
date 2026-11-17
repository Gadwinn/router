<?php

declare(strict_types=1);

namespace G\Router\Attribute;

use FilesystemIterator;
use G\Router\Exception\RouterException;
use G\Router\RouterInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

/**
 * Maps the routes declared with the Group (class) and Route (method) attributes.
 * The target of each route is [Controller::class, 'method'].
 */
final class AttributeLoader
{
    private const string OPTION_NAME = 'name';
    private const string PHP_EXTENSION = 'php';

    public function __construct(private readonly RouterInterface $router)
    {
    }

    /**
     * Map the routes of the given controller classes.
     *
     * @param class-string ...$classes
     *
     * @return $this
     * @throws RouterException When a class does not exist, or a Route attribute is set on a
     *                         method that is not a public instance method.
     */
    public function loadClass(string ...$classes): static
    {
        foreach ($classes as $class) {
            if (!class_exists($class)) {
                throw new RouterException("Controller class '$class' not found");
            }

            $this->load(new ReflectionClass($class));
        }

        return $this;
    }

    /**
     * Map the routes of every class of a directory laid out as PSR-4 (sub-directories
     * included), in alphabetical order of the files. Files that do not hold a class of
     * the expected name, and abstract classes, are ignored.
     *
     * @param string $directory Directory of the controllers (e.g. __DIR__ . '/../src/Controller').
     * @param string $namespace Namespace matching the directory (e.g. 'App\Controller').
     *
     * @return $this
     * @throws RouterException When the directory does not exist, or a Route attribute is set
     *                         on a method that is not a public instance method.
     */
    public function loadDirectory(string $directory, string $namespace): static
    {
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            throw new RouterException("Controller directory '$directory' not found");
        }

        $namespace = trim($namespace, '\\');
        $classes = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== self::PHP_EXTENSION) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.' . self::PHP_EXTENSION));
            $classes[] = ltrim($namespace . '\\' . strtr($relative, '/' . DIRECTORY_SEPARATOR, '\\\\'), '\\');
        }

        sort($classes);
        foreach ($classes as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (!$reflection->isAbstract()) {
                $this->load($reflection);
            }
        }

        return $this;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @throws RouterException
     */
    private function load(ReflectionClass $class): void
    {
        $routes = [];
        foreach ($class->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $this->assertCallable($method, $class);
                $routes[] = [$attribute->newInstance(), $method->getName()];
            }
        }

        if ($routes === []) {
            return;
        }

        $map = function (RouterInterface $router) use ($routes, $class): void {
            foreach ($routes as [$route, $method]) {
                $options = $route->options;
                if ($route->name !== '') {
                    $options[self::OPTION_NAME] = $route->name;
                }

                $router->map($route->methods, $route->path, [$class->getName(), $method], $options);
            }
        };

        $group = ($class->getAttributes(Group::class)[0] ?? null)?->newInstance();
        if ($group === null) {
            $map($this->router);

            return;
        }

        $options = $group->options;
        if ($group->name !== '') {
            $options[self::OPTION_NAME] = $group->name;
        }

        $this->router->group($group->path, $map, $options);
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @throws RouterException
     */
    private function assertCallable(ReflectionMethod $method, ReflectionClass $class): void
    {
        if (!$method->isPublic() || $method->isStatic() || $method->isAbstract()) {
            throw new RouterException(
                "The Route attribute of {$class->getName()}::{$method->getName()}() must be set on a"
                    . ' public instance method'
            );
        }
    }
}
