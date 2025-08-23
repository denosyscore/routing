<?php

declare(strict_types=1);

use Denosys\Routing\Attributes\RouteGroup;
use Denosys\Routing\Attributes\Middleware;
use Denosys\Routing\Attributes\Get;
use Denosys\Routing\Attributes\Post;

it('can create RouteGroup attribute', function () {
    $group = new RouteGroup(
        prefix: '/admin',
        name: 'admin',
        middleware: ['auth', 'admin']
    );
    
    expect($group->getPrefix())->toBe('/admin')
        ->and($group->getName())->toBe('admin')
        ->and($group->getMiddleware())->toBe(['auth', 'admin']);
});

it('can create RouteGroup attribute with minimal parameters', function () {
    $group = new RouteGroup('/api');
    
    expect($group->getPrefix())->toBe('/api')
        ->and($group->getName())->toBeNull()
        ->and($group->getMiddleware())->toBe([]);
});

it('can create Middleware attribute with single middleware', function () {
    $middleware = new Middleware('auth');
    
    expect($middleware->getMiddleware())->toBe(['auth'])
        ->and($middleware->getOnly())->toBe([])
        ->and($middleware->getExcept())->toBe([]);
});

it('can create Middleware attribute with multiple middleware', function () {
    $middleware = new Middleware(['auth', 'admin']);
    
    expect($middleware->getMiddleware())->toBe(['auth', 'admin']);
});

it('can create Middleware attribute with only constraint', function () {
    $middleware = new Middleware('auth', only: ['store', 'update']);
    
    expect($middleware->getMiddleware())->toBe(['auth'])
        ->and($middleware->getOnly())->toBe(['store', 'update'])
        ->and($middleware->getExcept())->toBe([]);
});

it('can create Middleware attribute with except constraint', function () {
    $middleware = new Middleware('auth', except: ['index', 'show']);
    
    expect($middleware->getMiddleware())->toBe(['auth'])
        ->and($middleware->getOnly())->toBe([])
        ->and($middleware->getExcept())->toBe(['index', 'show']);
});

it('can combine RouteGroup and method attributes', function () {
    // Create a test controller that combines RouteGroup with method attributes
    #[RouteGroup('/admin', name: 'admin', middleware: ['auth'])]
    class TestCombinedController {
        #[Get('/dashboard', name: 'dashboard')]
        public function dashboard() {
            return ['page' => 'dashboard'];
        }
        
        #[Post('/settings', name: 'settings.update')]
        #[Middleware('validation')]
        public function updateSettings() {
            return ['message' => 'Settings updated'];
        }
    }
    
    $scanner = new \Denosys\Routing\Attributes\AttributeRouteScanner();
    $routes = $scanner->scanClass(TestCombinedController::class);
    
    expect($routes)->toHaveCount(2);
    
    // Test first route: RouteGroup + Get combination
    $dashboardRoute = $routes[0];
    expect($dashboardRoute['path'])->toBe('/admin/dashboard')
        ->and($dashboardRoute['name'])->toBe('admin.dashboard')
        ->and($dashboardRoute['methods'])->toBe(['GET', 'HEAD'])
        ->and($dashboardRoute['middleware'])->toBe(['auth']);
    
    // Test second route: RouteGroup + Post + additional Middleware combination
    $settingsRoute = $routes[1];
    expect($settingsRoute['path'])->toBe('/admin/settings')
        ->and($settingsRoute['name'])->toBe('admin.settings.update')
        ->and($settingsRoute['methods'])->toBe(['POST'])
        ->and($settingsRoute['middleware'])->toBe(['auth', 'validation']);
});
