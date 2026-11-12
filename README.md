# Denosys Routing

> **Read-only split.** This package is developed in [denosyscore/framework](https://github.com/denosyscore/framework) under `packages/routing`. Open issues and pull requests there.

A highly efficient and flexible routing package for PHP, designed to support modern web applications with minimal overhead.

## Features

-   **Fast Route Matching**: Utilizes a trie data structure for efficient route matching.
-   **Flexible Handlers**: Supports various types of handlers (closures, arrays, strings).
-   **PSR-7/PSR-15 Compliant**: Compatible with PSR-7 HTTP messages and PSR-15 middleware.
-   **Global Middleware**: Application-wide middleware via `$router->use()`.
-   **Middleware Groups & Aliases**: Reusable middleware configurations with named groups.
-   **Customizable Invocation Strategies**: Define how route handlers are invoked.
-   **Dependency Injection**: Integrates with PSR-11 containers for automatic dependency resolution.
-   **Dynamic and Static Routes**: Easily define and handle both dynamic and static routes.
-   **Attribute-Based Routing**: Define routes using PHP 8 attributes on controller methods.

## Requirements

-   PHP 8.2 or later

## Usage

Install the package using Composer:

```bash
composer require denosyscore/routing
```

Install a PSR-7 implementation, such as Laminas Diactoros:

```bash
composer require laminas/laminas-diactoros
```

Here's a simple example of how to define routes and handle requests:

<!-- quickstart requires: laminas/laminas-diactoros -->
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Denosys\Routing\Router;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$router = new Router();

$router->get('/', function (): ResponseInterface {
    $response = new Response();
    $response->getBody()->write('Hello, World!');
    return $response;
});

// Route parameters are passed to the handler by name.
$router->get('/books/{id}', function (ServerRequestInterface $request, string $id): ResponseInterface {
    $response = new Response();
    $response->getBody()->write("Book {$id}");
    return $response;
})->name('books.show');

// In a web server, build the request with ServerRequestFactory::fromGlobals().
echo $router->dispatch(new ServerRequest([], [], 'https://example.test/', 'GET'))->getBody(), "\n";
// Hello, World!
echo $router->dispatch(new ServerRequest([], [], 'https://example.test/books/42', 'GET'))->getBody(), "\n";
// Book 42
```

## Middleware

The Router supports PSR-15 middleware with built-in execution via the Dispatcher.

### Global Middleware

Use `$router->use()` to register middleware that runs on **every request**:

```php
// Global middleware runs on ALL routes
$router->use(LoggingMiddleware::class);
$router->use(CorsMiddleware::class);

// Supports arrays
$router->use([ErrorHandlerMiddleware::class, SessionMiddleware::class]);

// Routes defined before or after - doesn't matter
$router->get('/users', 'UserController@index');
$router->get('/posts', 'PostController@index');
```

Global middleware:
- Executes before any route-specific middleware (outermost layer)
- Applied at dispatch time, so order of `use()` vs route definitions doesn't matter
- Supports class strings, aliases, or middleware instances

### Route Middleware

Add middleware to specific routes:

```php
// Single middleware
$router->get('/admin', 'AdminController@index')
       ->middleware('auth');

// Multiple middleware
$router->get('/api/users', 'UserController@index')
       ->middleware(['auth', 'throttle']);

// Chained middleware (applies to next route only)
$router->middleware('auth')
       ->get('/dashboard', 'DashboardController@index');
```

### Middleware Groups and Aliases

Register reusable middleware configurations:

```php
// Register aliases
$router->aliasMiddleware('auth', AuthMiddleware::class);
$router->aliasMiddleware('throttle', ThrottleMiddleware::class);

// Register groups (can reference aliases or other groups)
$router->middlewareGroup('web', ['session', 'csrf', 'cookies']);
$router->middlewareGroup('api', ['throttle', 'auth']);

// Use aliases/groups on routes
$router->get('/dashboard', 'DashboardController@index')
       ->middleware('web');

$router->get('/api/users', 'UserController@index')
       ->middleware('api');

// Modify existing groups
$router->prependMiddlewareToGroup('web', 'logging');
$router->appendMiddlewareToGroup('api', 'cors');
```

### Route Groups with Middleware

Apply middleware to all routes in a group:

```php
$router->middleware('auth')->group('/admin', function ($group) {
    $group->get('/dashboard', 'AdminController@dashboard');
    $group->get('/users', 'AdminController@users');
});
```

### Excluding Middleware

Use `withoutMiddleware()` to exclude specific middleware from a route:

```php
// Exclude from route middleware
$router->get('/test', 'TestController@index')
       ->middleware(['auth', 'logging', 'throttle'])
       ->withoutMiddleware('logging');

// Exclude inherited group middleware
$router->middleware(['auth', 'admin'])->group('/admin', function ($group) {
    $group->get('/dashboard', 'AdminController@dashboard');  // Has auth + admin
    $group->get('/public', 'AdminController@public')
          ->withoutMiddleware('auth');                       // Only has admin
});

// Exclude multiple middleware
$route->withoutMiddleware(['logging', 'throttle']);
```

### Retrieving Middleware Metadata

```php
$routes = $router->getRouteCollection()->all();
$route = reset($routes);
$middleware = $route->getMiddleware(); // ['auth', 'throttle']
```

## Adding Routes

You can add routes using various HTTP methods:

```php
$router->get('/user/{id}', 'UserController@show');
$router->post('/user', 'UserController@store');
$router->put('/user/{id}', 'UserController@update');
$router->delete('/user/{id}', 'UserController@destroy');
$router->patch('/user/{id}', 'UserController@patch');
$router->options('/user', 'UserController@options');
$router->any('/any-method', 'AnyController@handle');
```

### Full documentation coming soon...

## Contributing

Please see [CONTRIBUTING](https://github.com/denosyscore/routing/blob/main/CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](https://github.com/denosyscore/routing/blob/main/LICENSE.md) for more information.
