<?php

use Denosys\Routing\Router;

beforeEach(function () {
    $this->router = new Router();
});

it('stores middleware on routes as metadata only', function () {
    $route = $this->router->get('/test', fn() => 'test')
                          ->middleware('auth');

    expect($route->getMiddleware())->toBe(['auth']);
});

it('stores multiple middleware in order', function () {
    $route = $this->router->get('/test', fn() => 'test')
                          ->middleware(['auth', 'cors', 'throttle']);

    expect($route->getMiddleware())->toBe(['auth', 'cors', 'throttle']);
});

it('stores chained middleware calls', function () {
    $route = $this->router->get('/test', fn() => 'test')
                          ->middleware('auth')
                          ->middleware('cors');

    expect($route->getMiddleware())->toBe(['auth', 'cors']);
});

it('applies router-level middleware to next route only', function () {
    $route1 = $this->router->get('/public', fn() => 'public');

    $route2 = $this->router->middleware('auth')
                           ->get('/protected', fn() => 'protected');

    $route3 = $this->router->get('/public2', fn() => 'public2');

    expect($route1->getMiddleware())->toBe([]);
    expect($route2->getMiddleware())->toBe(['auth']);
    expect($route3->getMiddleware())->toBe([]);
});

it('applies router-level middleware array to next route', function () {
    $route = $this->router->middleware(['auth', 'cors'])
                          ->get('/test', fn() => 'test');

    expect($route->getMiddleware())->toBe(['auth', 'cors']);
});

it('clears middleware after applying to route', function () {
    $route1 = $this->router->middleware('auth')
                           ->get('/route1', fn() => 'route1');

    $route2 = $this->router->get('/route2', fn() => 'route2');

    expect($route1->getMiddleware())->toBe(['auth']);
    expect($route2->getMiddleware())->toBe([]);
});

it('supports hasMiddleware check', function () {
    $route = $this->router->get('/test', fn() => 'test')
                          ->middleware(['auth', 'cors']);

    expect($route->hasMiddleware('auth'))->toBe(true);
    expect($route->hasMiddleware('cors'))->toBe(true);
    expect($route->hasMiddleware('throttle'))->toBe(false);
});

it('supports clearMiddleware', function () {
    $route = $this->router->get('/test', fn() => 'test')
                          ->middleware(['auth', 'cors'])
                          ->clearMiddleware();

    expect($route->getMiddleware())->toBe([]);
});

it('stores group middleware on all routes in group', function () {
    $routes = [];
    $this->router->group('/api', function($group) use (&$routes) {
        $routes[] = $group->get('/users', fn() => 'users');
        $routes[] = $group->get('/posts', fn() => 'posts');
    })->middleware('auth');

    expect($routes[0]->getMiddleware())->toBe(['auth']);
    expect($routes[1]->getMiddleware())->toBe(['auth']);
});

it('stores middleware from router->middleware()->group() correctly', function () {
    $routes = [];
    $this->router->middleware('auth')
                 ->group('/api', function($group) use (&$routes) {
                     $routes[] = $group->get('/users', fn() => 'users');
                 });

    expect($routes[0]->getMiddleware())->toBe(['auth']);
});

it('inherits group middleware in nested groups', function () {
    $routes = [];
    $this->router->middleware('global')
                 ->group('/api', function($api) use (&$routes) {
                     $routes[] = $api->get('/public', fn() => 'public');

                     $api->group('/admin', function($admin) use (&$routes) {
                         $routes[] = $admin->get('/users', fn() => 'users');
                     })->middleware('admin');
                 });

    expect($routes[0]->getMiddleware())->toBe(['global']);
    expect($routes[1]->getMiddleware())->toBe(['global', 'admin']);
});

it('stores route-specific middleware within group', function () {
    $routes = [];
    $this->router->group('/api', function($group) use (&$routes) {
        $routes[] = $group->get('/public', fn() => 'public');

        $routes[] = $group->get('/protected', fn() => 'protected')
                          ->middleware('auth');
    })->middleware('cors');

    expect($routes[0]->getMiddleware())->toBe(['cors']);
    expect($routes[1]->getMiddleware())->toBe(['auth', 'cors']);
});

it('router dispatch does not require PSR-15 middleware instances', function () {
    $this->router->get('/test', fn() => 'response')
                 ->middleware('non_existent_middleware');

    $request = new \Laminas\Diactoros\ServerRequest([], [], '/test', 'GET');
    $response = $this->router->dispatch($request);

    expect((string) $response->getBody())->toBe('response');
});

it('middleware stored as strings not instances', function () {
    $route = $this->router->get('/test', fn() => 'test')
                          ->middleware('auth');

    $middleware = $route->getMiddleware();
    expect($middleware)->toBeArray();
    expect($middleware[0])->toBeString();
    expect($middleware[0])->toBe('auth');
});

it('preserves middleware order as defined', function () {
    $route = $this->router->get('/test', fn() => 'test')
                          ->middleware('first')
                          ->middleware('second')
                          ->middleware('third');

    expect($route->getMiddleware())->toBe(['first', 'second', 'third']);
});

it('preserves middleware through route collection', function () {
    $this->router->get('/test', fn() => 'test')
                 ->middleware(['auth', 'cors']);

    $routes = $this->router->getRouteCollection()->all();
    $route = reset($routes);

    expect($route->getMiddleware())->toBe(['auth', 'cors']);
});
