# Denosys Routing

A highly efficient and flexible routing package for PHP, designed to support modern web applications with minimal overhead.

## Features

-   **Fast Route Matching**: Utilizes a trie data structure for efficient route matching.
-   **Flexible Handlers**: Supports various types of handlers (closures, arrays, strings).
-   **PSR-7 Compliant**: Compatible with PSR-7 HTTP message interfaces.
-   **Middleware Metadata**: Store middleware requirements as route metadata.
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

```php
// Create a new router instance
$router = new Denosys\Routing\Router();

// Define a route
$router->get('/', function (): ResponseInterface {
    $response = new Laminas\Diactoros\Response();
    $response->getBody()->write('Hello, World!');
    return $response;
});

// Create Request
$request = Laminas\Diactoros\ServerRequestFactory::fromGlobals();

// Dispatch the request
$response = $router->dispatch($request);

// Output the response
echo $response->getBody();
```

## Middleware as Metadata

**Important**: The Router stores middleware as **metadata only** and does not execute middleware. Middleware execution should be handled by your HTTP kernel or application layer.

### Adding Middleware to Routes

```php
// Store middleware metadata on route
$router->get('/protected', 'ProtectedController@index')
       ->middleware('auth'); // Stored as metadata: ['auth']

// Or use fluent syntax
$router->middleware('auth')
       ->get('/dashboard', 'DashboardController@index');

// Multiple middleware
$router->get('/api/users', 'UserController@index')
       ->middleware(['auth', 'cors', 'throttle']);
```

### Retrieving Middleware Metadata

```php
$routes = $router->getRouteCollection()->all();
$route = reset($routes); // First route (keys are like '_route_0', not numeric)
$middleware = $route->getMiddleware(); // ['auth', 'cors', 'throttle']
```

### Executing Middleware (Your Responsibility)

The Router does not execute middleware. You should implement an HTTP kernel:

```php
class HttpKernel
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 1. Match route
        $route = $this->router->match($request);

        // 2. Get middleware from route metadata
        $middlewareNames = $route->getMiddleware();

        // 3. Resolve middleware (your implementation)
        $middlewareStack = $this->resolveMiddleware($middlewareNames);

        // 4. Execute middleware pipeline (your implementation)
        $pipeline = new MiddlewarePipeline($middlewareStack);
        return $pipeline->handle($request, fn() => $this->invokeHandler($route));
    }

    private function resolveMiddleware(array $names): array
    {
        // Your middleware resolution logic
        return array_map(
            fn($name) => $this->container->get($this->aliases[$name] ?? $name),
            $names
        );
    }
}
```

See `DESIGN.md` and `MIGRATION.md` for more details.

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
