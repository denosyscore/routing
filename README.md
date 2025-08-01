# Denosys Routing

A highly efficient and flexible routing package for PHP, designed to support modern web applications with minimal overhead.

## Features

- **Fast Route Matching**: Utilizes a trie data structure for efficient route matching.
- **Flexible Handlers**: Supports various types of handlers (closures, arrays, strings).
- **PSR-7 Compliant**: Compatible with PSR-7 HTTP message interfaces.
- **PSR-15 Middleware Support**: Allows middleware to be attached to routes.
- **Customizable Invocation Strategies**: Define how route handlers are invoked.
- **Dependency Injection**: Integrates with PSR-11 containers for automatic dependency resolution.
- **Dynamic and Static Routes**: Easily define and handle both dynamic and static routes.

## Requirements

- PHP 8.2 or later

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
