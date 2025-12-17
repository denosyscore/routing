<?php

declare(strict_types=1);

use Denosys\Routing\Router;
use Denosys\Routing\AttributeRouteLoader;
use Denosys\Routing\Attributes\Get;
use Denosys\Routing\Attributes\Post;
use Denosys\Routing\Attributes\RouteGroup;
use Denosys\Routing\Attributes\Resource;
use Denosys\Routing\Attributes\Middleware;

class IntegrationTestMixedController
{
    #[Get('/custom/{slug}', where: ['slug' => '[a-z0-9-]+'])]
    #[Middleware('cache', except: ['edit'])]
    public function custom() {
        return ['message' => 'Custom route'];
    }
}

#[RouteGroup('/api/v1', name: 'api.v1')]
class ApiUserController
{
    #[Get('/users', name: 'users.index')]
    public function index()
    {
        return createJsonResponse(['users' => []]);
    }
    
    #[Get('/users/{id}', name: 'users.show', where: ['id' => '\d+'])]
    public function show($id)
    {
        return createJsonResponse(['user' => ['id' => $id]]);
    }
    
    #[Post('/users', name: 'users.store')]
    public function store()
    {
        return createJsonResponse(['user' => ['id' => 123]], 201);
    }
}

#[Resource('products', only: ['index', 'show'])]
class ProductController
{
    public function index()
    {
        return createJsonResponse(['products' => []]);
    }
    
    public function show($id)
    {
        return createJsonResponse(['product' => ['id' => $id]]);
    }
}

it('can register routes from attributes and dispatch requests', function () {
    $router = new Router();
    $loader = new AttributeRouteLoader($router);

    // Register routes from controller attributes
    $loader->loadFromClasses([ApiUserController::class]);

    // Test GET /api/v1/users
    $request = createRequest('GET', '/api/v1/users');
    $response = $router->dispatch($request);

    expect($response->getStatusCode())->toBe(200);

    // Test GET /api/v1/users/123
    $request = createRequest('GET', '/api/v1/users/123');
    $response = $router->dispatch($request);

    expect($response->getStatusCode())->toBe(200);

    // Test POST /api/v1/users
    $request = createRequest('POST', '/api/v1/users');
    $response = $router->dispatch($request);

    expect($response->getStatusCode())->toBe(201);
});

it('can register resource routes and dispatch requests', function () {
    $router = new Router();
    $loader = new AttributeRouteLoader($router);

    // Register resource routes
    $loader->loadFromClasses([ProductController::class]);

    // Test GET /products (index)
    $request = createRequest('GET', '/products');
    $response = $router->dispatch($request);

    expect($response->getStatusCode())->toBe(200);

    // Test GET /products/123 (show)
    $request = createRequest('GET', '/products/123');
    $response = $router->dispatch($request);

    expect($response->getStatusCode())->toBe(200);
});

it('can generate URLs from named attribute routes', function () {
    $router = new Router();
    $loader = new AttributeRouteLoader($router);
    $loader->loadFromClasses([ApiUserController::class]);

    $GLOBALS['router'] = $router;

    expect(route('api.v1.users.index'))->toBe('/api/v1/users')
        ->and(route('api.v1.users.show', ['id' => 123]))->toBe('/api/v1/users/123');
});

it('respects route constraints from attributes', function () {
    $router = new Router();
    $loader = new AttributeRouteLoader($router);
    $loader->loadFromClasses([ApiUserController::class]);

    // Valid numeric ID should work
    $request = createRequest('GET', '/api/v1/users/123');
    $response = $router->dispatch($request);
    expect($response->getStatusCode())->toBe(200);

    // Invalid non-numeric ID should return 404
    $request = createRequest('GET', '/api/v1/users/abc');
    expect(fn() => $router->dispatch($request))
        ->toThrow(Denosys\Routing\Exceptions\NotFoundException::class);
});

it('can mix attribute routes with traditional routes', function () {
    $router = new Router();

    // Traditional route
    $router->get('/traditional', fn() => createJsonResponse(['type' => 'traditional']));

    // Attribute routes
    $loader = new AttributeRouteLoader($router);
    $loader->loadFromClasses([ApiUserController::class]);

    // Test traditional route
    $request = createRequest('GET', '/traditional');
    $response = $router->dispatch($request);
    expect($response->getStatusCode())->toBe(200);

    // Test attribute route
    $request = createRequest('GET', '/api/v1/users');
    $response = $router->dispatch($request);
    expect($response->getStatusCode())->toBe(200);
});

it('can scan directory for controllers and register routes', function () {
    $router = new Router();
    $loader = new AttributeRouteLoader($router);

    // Scan directory for controller classes with attributes
    $loader->loadFromDirectory(__DIR__ . '/../fixtures/controllers');

    // Test that routes were registered
    expect($router->getRouteCollection()->count())->toBe(2);

    // Test that we can dispatch to the discovered routes
    $request = createRequest('GET', '/test');
    $response = $router->dispatch($request);
    expect($response->getStatusCode())->toBe(200);
});
