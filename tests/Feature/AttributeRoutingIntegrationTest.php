<?php

declare(strict_types=1);

use Denosys\Routing\Router;
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
    
    // Register routes from controller attributes
    $router->loadAttributeRoutes([ApiUserController::class]);
    
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
    
    // Register resource routes
    $router->loadAttributeRoutes([ProductController::class]);
    
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
    $router->loadAttributeRoutes([ApiUserController::class]);
    
    $GLOBALS['router'] = $router;
    
    expect(route('api.v1.users.index'))->toBe('/api/v1/users')
        ->and(route('api.v1.users.show', ['id' => 123]))->toBe('/api/v1/users/123');
});

it('respects route constraints from attributes', function () {
    $router = new Router();
    $router->loadAttributeRoutes([ApiUserController::class]);
    
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
    $router->loadAttributeRoutes([ApiUserController::class]);
    
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
    
    // Scan directory for controller classes with attributes
    $router->loadAttributeRoutesFromDirectory(__DIR__ . '/../fixtures/controllers');
    
    // Test that routes were registered
    expect($router->getRouteCollection()->count())->toBe(2);
    
    // Test that we can dispatch to the discovered routes
    $request = createRequest('GET', '/test');
    $response = $router->dispatch($request);
    expect($response->getStatusCode())->toBe(200);
});

it('handles middleware from attributes correctly', function () {
    $router = new Router();
    
    // Create a simple test middleware that modifies the response
    $testMiddleware = new class implements \Psr\Http\Server\MiddlewareInterface {
        public function process(
            \Psr\Http\Message\ServerRequestInterface $request,
            \Psr\Http\Server\RequestHandlerInterface $handler
        ): \Psr\Http\Message\ResponseInterface {
            $response = $handler->handle($request);
            return $response->withHeader('X-Test-Middleware', 'applied');
        }
    };
    
    // Register the middleware with the router
    $router->aliasMiddleware('cache', $testMiddleware);
    
    // Load routes from a controller that has middleware
    $router->loadAttributeRoutes([IntegrationTestMixedController::class]);
    
    // Test that the middleware is applied when dispatching the request
    $request = createRequest('GET', '/custom/test-slug');
    $response = $router->dispatch($request);
    
    // Verify the middleware was executed
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('X-Test-Middleware'))->toBe('applied');
});

it('properly handles middleware constraints from attributes', function () {
    // Create a controller with middleware that has except constraints
    class TestConstraintController {
        #[Get('/admin/{action}', name: 'admin.action', where: ['action' => '[a-z]+'])]
        #[Middleware('test-auth', except: ['login'])]
        public function action($action) {
            return ['action' => $action, 'message' => 'Admin action executed'];
        }
    }
    
    $router = new Router();
    
    // Create a test auth middleware that adds a header
    $authMiddleware = new class implements \Psr\Http\Server\MiddlewareInterface {
        public function process(
            \Psr\Http\Message\ServerRequestInterface $request,
            \Psr\Http\Server\RequestHandlerInterface $handler
        ): \Psr\Http\Message\ResponseInterface {
            $response = $handler->handle($request);
            return $response->withHeader('X-Auth', 'authenticated');
        }
    };
    
    // Register the middleware
    $router->aliasMiddleware('test-auth', $authMiddleware);
    $router->loadAttributeRoutes([TestConstraintController::class]);
    
    // Test that route is registered and middleware constraints are properly stored
    $routes = $router->getRouteCollection()->all();
    expect($routes)->not()->toBeEmpty();
    
    // Test dispatching the route
    $request = createRequest('GET', '/admin/dashboard');
    $response = $router->dispatch($request);
    
    expect($response->getStatusCode())->toBe(200);
    
    $body = json_decode((string) $response->getBody(), true);
    expect($body['action'])->toBe('dashboard')
        ->and($body['message'])->toBe('Admin action executed')
        ->and($response->getHeaderLine('X-Auth'))->toBe('authenticated');
});
