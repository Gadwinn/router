# gadwinn/router

A PSR-15 compliant HTTP router for PHP 8.3+. Pattern-based route matching, named routes, reverse URL generation, route groups, and plug-and-play PSR-15 middleware.

---

## Stack

| Requirement | Version |
|---|---|
| PHP | `^8.3` |
| psr/http-message | `^1.1 \|\| ^2.0` |
| psr/http-server-handler | `^1.0` |
| psr/http-server-middleware | `^1.0` |

---

## Installation

```bash
composer require gadwinn/router
```

---

## Usage

### Basic routing

```php
use G\Router\Router;

$router = new Router();

$router->get('/', [HomeController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->put('/users/{id:i}', [UserController::class, 'update']);
$router->patch('/users/{id:i}', [UserController::class, 'patch']);
$router->delete('/users/{id:i}', [UserController::class, 'destroy']);
$router->any('/ping', fn() => 'pong'); // all registered HTTP methods
```

Dispatch against a PSR-7 `RequestInterface`:

```php
use G\Router\Exception\{NotFoundException, MethodNotAllowedException};

try {
    $route = $router->match($request);

    $target = $route->getTarget();   // callable|array|string
    $params = $route->getParams();   // ['id' => '42']
} catch (NotFoundException $e) {
    // 404
} catch (MethodNotAllowedException $e) {
    // 405 — $e->getAllowedMethods() gives the content of the Allow header
}
```

A `HEAD` request falls back to the `GET` route of the same path when no `HEAD` route is mapped (RFC 9110). `match()` returns a copy of the route carrying the parameters, so the registered routes are never altered.

---

### Route parameters & built-in patterns

Parameters are declared as `{name}` or `{name:alias}` in the path. `{name}` alone matches a single segment (same as `{name:*}`). Everything outside the braces is matched literally (`/file.json` only matches `/file.json`), and paths are case-sensitive. Parameter values are URL-decoded.

| Alias | Regex | Example |
|---|---|---|
| `i` | `\d+` | `{id:i}` — integer |
| `d` | `\d+` | `{page:d}` — digits (alias of `i`) |
| `h` | `[0-9a-fA-F]+` | `{token:h}` — hexadecimal |
| `s` | `[\w\-]+` | `{slug:s}` — slug |
| `*` | `[^\/]+` | `{any:*}` — any single segment |
| `**` | `.+` | `{rest:**}` — trailing segments |

A custom inline regex is also accepted:

```php
$router->get('/articles/{year:[0-9]{4}}/{slug:s}', [ArticleController::class, 'show']);
```

Add reusable pattern aliases at runtime:

```php
$router->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

$router->get('/orders/{id:uuid}', [OrderController::class, 'show']);
```

---

### Named routes & URL generation

```php
$router->get('/users/{id:i}/profile', [UserController::class, 'profile'], ['name' => 'user.profile']);

// Reverse generation
$url = $router->url('user.profile', ['id' => '42']); // '/users/42/profile'
```

Each value must match the pattern of its parameter and is URL-encoded (`'a b'` → `a%20b`); slashes are kept for a trailing parameter (`{rest:**}`). Parameters that are not part of the path are appended as a query string (`url('user.profile', ['id' => '42', 'tab' => 'posts'])` → `/users/42/profile?tab=posts`). A `RouterException` is thrown when the route name is unknown or when a path parameter is missing or does not match its pattern. When several routes share a name, the first one mapped is used.

---

### Route groups

Groups apply a path prefix and, optionally, a name prefix to all routes defined inside the callback.

```php
$router->group('/api/v1', function (Router $r): void {
    $r->get('/users',       [UserController::class, 'index'],   ['name' => 'users.index']);
    $r->post('/users',      [UserController::class, 'store'],   ['name' => 'users.store']);
    $r->get('/users/{id:i}',[UserController::class, 'show'],    ['name' => 'users.show']);
}, ['name' => 'api.v1.']);

// Resulting paths:  /api/v1/users,  /api/v1/users/{id}
// Resulting names:  api.v1.users.index, api.v1.users.store, api.v1.users.show
```

Groups can be nested:

```php
$router->group('/admin', function (Router $r): void {
    $r->group('/settings', function (Router $r): void {
        $r->get('/security', [SecurityController::class, 'index']);
    });
});
// => /admin/settings/security
```

---

### Attributes

Routes can be declared on the controllers instead of being mapped one by one: `#[Group]` on the class (optional) is the group, `#[Route]` on a method is a route of that group.

```php
use G\Router\Attribute\{Group, Route};

#[Group('/users', name: 'users.')]
final class UserController
{
    #[Route('', name: 'index')]                       // GET /users            users.index
    public function index() { /* ... */ }

    #[Route('/{id:i}', name: 'show')]                 // GET /users/{id}       users.show
    public function show(int $id) { /* ... */ }

    #[Route('/{id:i}', methods: ['PUT', 'PATCH'])]    // PUT, PATCH /users/{id}
    public function update(int $id) { /* ... */ }
}
```

```php
use G\Router\Attribute\AttributeLoader;

$loader = new AttributeLoader($router);

$loader->loadDirectory(__DIR__ . '/src/Controller', 'App\Controller'); // every class, sub-directories included
$loader->loadClass(UserController::class, HomeController::class);      // or chosen classes
```

- `#[Group(path, name, options)]` behaves like `group()`: path prefix, name prefix.
- `#[Route(path, methods, name, options)]`: `methods` defaults to `GET`; the attribute is repeatable; the target of the route is `[Controller::class, 'method']`.
- A class without `#[Group]` maps its routes as they are. A `#[Route]` on a method that is not a public instance method throws a `RouterException`.
- `loadDirectory()` expects a PSR-4 layout and ignores abstract classes and files that do not hold a class.

Attributes and `map()` calls can be mixed. The directory is read each time `loadDirectory()` is called.

---

### Base path

When the application is not served at the web root (e.g. `http://localhost/apps/shop/public/`), declare the base path once instead of wrapping every route in a group:

```php
$router->setBasePath('/apps/shop/public');

$router->get('/users/{id:i}', 'user', ['name' => 'user']);

// Matches the request path /apps/shop/public/users/42
// $router->url('user', ['id' => 42]) returns '/apps/shop/public/users/42'
```

The base path is removed from the request path before matching (a request outside of it is a 404) and prepended to generated URLs. Route paths, `getRoutes()` and `unmap()` are not affected.

### Removing routes

```php
// By method + path
$router->unmap('GET', '/users/{id:i}');
$router->unmap(['GET', 'POST'], '/users');

// By name
$router->unmapByName('user.profile');
```

---

### PSR-15 middleware pipeline

Drop both middlewares into any PSR-15 pipeline. `RouterMiddleware` resolves the route and stores it as the `route` request attribute; `RouteResolverMiddleware` guards the downstream pipeline against a missing attribute.

```php
use G\Router\Middleware\{RouterMiddleware, RouteResolverMiddleware};

// Framework-agnostic example (e.g. with league/pipeline or any PSR-15 dispatcher)
$pipeline->pipe(new RouterMiddleware($router));
$pipeline->pipe(new RouteResolverMiddleware());
$pipeline->pipe(new YourDispatcherMiddleware());
```

Retrieve the matched route inside any downstream middleware or handler:

```php
use G\Router\Route\RouteInterface;

$route = $request->getAttribute(RouterMiddleware::ROUTE_ATTRIBUTE_NAME); // 'route', a RouteInterface
$route->getTarget();
$route->getParams();
$route->getMethod();
$route->getOption('name');
```

---

### Custom route class

Any class implementing `RouteInterface` can be substituted:

```php
use G\Router\Router;
use G\Router\Route\RouteInterface;

class AnnotatedRoute implements RouteInterface
{
    // ...
}

$router = new Router(AnnotatedRoute::class);
```

---

### Custom HTTP methods

```php
$router->addAllowedMethod('PURGE');

$router->map('PURGE', '/cache/{key:s}', [CacheController::class, 'purge']);
```

---

### Introspection

```php
$router->getAllowedMethods(); // string[]
$router->getPatterns();       // array<string, string>
$router->getRoutes();         // array<string, RouteInterface>
$router->matchByName('user.profile'); // ?RouteInterface
```
