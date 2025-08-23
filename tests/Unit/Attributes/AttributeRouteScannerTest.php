<?php

declare(strict_types=1);

use Denosys\Routing\Attributes\AttributeRouteScanner;
use Denosys\Routing\Attributes\Get;
use Denosys\Routing\Attributes\Post;
use Denosys\Routing\Attributes\RouteGroup;
use Denosys\Routing\Attributes\Middleware;
use Denosys\Routing\Attributes\Resource;

// Test controller classes for scanning
#[RouteGroup('/admin', name: 'admin', middleware: ['auth'])]
class TestAdminController
{
    #[Get('/dashboard', name: 'dashboard')]
    public function dashboard() {}
    
    #[Post('/settings', name: 'settings.update')]
    public function updateSettings() {}
}

class TestUserController
{
    #[Get('/users', name: 'users.index')]
    public function index() {}
    
    #[Get('/users/{id}', name: 'users.show', where: ['id' => '\d+'])]
    public function show($id) {}
    
    #[Post('/users', name: 'users.store')]
    #[Middleware(['auth', 'validation'])]
    public function store() {}
}

#[Resource('posts')]
class TestPostController
{
    public function index() {}
    public function create() {}
    public function store() {}
    public function show($id) {}
    public function edit($id) {}
    public function update($id) {}
    public function delete($id) {}
}

class TestMixedController
{
    #[Get('/custom/{slug}', where: ['slug' => '[a-z0-9-]+'])]
    #[Middleware('cache', except: ['edit'])]
    public function custom() {}
}

it('can scan controller for route attributes', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass(TestUserController::class);
    
    expect($routes)->toHaveCount(3);
    
    // Test first route
    $route1 = $routes[0];
    expect($route1['methods'])->toBe(['GET', 'HEAD'])
        ->and($route1['path'])->toBe('/users')
        ->and($route1['name'])->toBe('users.index')
        ->and($route1['action'])->toBe([TestUserController::class, 'index'])
        ->and($route1['middleware'])->toBe([])
        ->and($route1['where'])->toBe([]);
    
    // Test second route with constraints
    $route2 = $routes[1];
    expect($route2['methods'])->toBe(['GET', 'HEAD'])
        ->and($route2['path'])->toBe('/users/{id}')
        ->and($route2['name'])->toBe('users.show')
        ->and($route2['where'])->toBe(['id' => '\d+']);
    
    // Test third route with method middleware
    $route3 = $routes[2];
    expect($route3['methods'])->toBe(['POST'])
        ->and($route3['middleware'])->toBe(['auth', 'validation']);
});

it('can scan controller with RouteGroup attribute', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass(TestAdminController::class);
    
    expect($routes)->toHaveCount(2);
    
    // Routes should inherit group prefix, name, and middleware
    $route1 = $routes[0];
    expect($route1['path'])->toBe('/admin/dashboard')
        ->and($route1['name'])->toBe('admin.dashboard')
        ->and($route1['middleware'])->toBe(['auth']);
    
    $route2 = $routes[1];
    expect($route2['path'])->toBe('/admin/settings')
        ->and($route2['name'])->toBe('admin.settings.update')
        ->and($route2['middleware'])->toBe(['auth']);
});

it('can scan controller with Resource attribute', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass(TestPostController::class);
    
    expect($routes)->toHaveCount(7);
    
    // Check a few key routes
    $indexRoute = array_values(array_filter($routes, fn($route) => $route['name'] === 'posts.index'))[0] ?? null;
    expect($indexRoute['methods'])->toBe(['GET', 'HEAD'])
        ->and($indexRoute['path'])->toBe('/posts')
        ->and($indexRoute['action'])->toBe([TestPostController::class, 'index']);
    
    $showRoute = array_values(array_filter($routes, fn($route) => $route['name'] === 'posts.show'))[0] ?? null;
    expect($showRoute['methods'])->toBe(['GET', 'HEAD'])
        ->and($showRoute['path'])->toBe('/posts/{id}')
        ->and($showRoute['action'])->toBe([TestPostController::class, 'show']);
    
    $storeRoute = array_values(array_filter($routes, fn($route) => $route['name'] === 'posts.store'))[0] ?? null;
    expect($storeRoute['methods'])->toBe(['POST'])
        ->and($storeRoute['path'])->toBe('/posts');
});

it('can scan multiple controllers', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClasses([
        TestUserController::class,
        TestAdminController::class
    ]);
    
    expect($routes)->toHaveCount(5); // 3 from TestUserController + 2 from TestAdminController
});

it('can scan directory for controllers', function () {
    $scanner = new AttributeRouteScanner();
    
    $routes = $scanner->scanDirectory(__DIR__ . '/../../fixtures/controllers');
    
    expect($routes)->toBeArray()
        ->and($routes)->toHaveCount(2); // Should find 2 routes from TestController
    
    // Check that routes were found
    $routeNames = array_column($routes, 'name');
    expect($routeNames)->toContain('test.index')
        ->and($routeNames)->toContain('test.store');
});

it('handles middleware constraints correctly', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass(TestMixedController::class);
    
    expect($routes)->toHaveCount(1);
    
    $route = $routes[0];
    expect($route['middleware'])->toBe(['cache'])
        ->and($route['middlewareExcept'])->toBe(['edit'])
        ->and($route['where'])->toBe(['slug' => '[a-z0-9-]+']);
});

it('can filter routes by middleware constraints', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass(TestMixedController::class);
    
    expect($routes)->toHaveCount(1);
    
    $route = $routes[0];
    expect($route['middleware'])->toBe(['cache'])
        ->and($route['middlewareExcept'])->toBe(['edit']);
    
    // The actual filtering logic during dispatch would be handled by the middleware system
});

it('throws exception for invalid controller class', function () {
    $scanner = new AttributeRouteScanner();
    
    expect(fn() => $scanner->scanClass('NonExistentController'))
        ->toThrow(InvalidArgumentException::class, 'Controller class NonExistentController does not exist');
});

it('ignores methods without route attributes', function () {
    $controller = new class {
        #[Get('/test')]
        public function withAttribute() {}
        
        public function withoutAttribute() {}
    };
    
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass($controller::class);
    
    expect($routes)->toHaveCount(1);
});
