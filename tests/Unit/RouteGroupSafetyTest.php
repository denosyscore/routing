<?php

use Denosys\Routing\Router;
use Laminas\Diactoros\ServerRequest;

function routeByPattern(Router $router, string $pattern): \Denosys\Routing\RouteInterface
{
    foreach ($router->getRouteCollection()->all() as $route) {
        if ($route->getPattern() === $pattern) {
            return $route;
        }
    }

    throw new RuntimeException("No route {$pattern}");
}

describe('Route group safety', function () {

    it('applies middleware declared inside a group to every route in the group', function () {
        $router = new Router();
        $router->group('/office', function ($group) {
            $group->get('/before', fn () => 'before');
            $group->middleware('auth');
            $group->get('/x', fn () => 'x');
            $group->get('/y', fn () => 'y');
        });

        foreach (['/office/before', '/office/x', '/office/y'] as $pattern) {
            expect(routeByPattern($router, $pattern)->getMiddleware())->toContain('auth');
        }
    });

    it('applies middleware chained after a group to routes in nested groups', function () {
        $router = new Router();
        $router->group('/admin', function ($admin) {
            $admin->get('/', fn () => 'dashboard');
            $admin->group('/users', function ($users) {
                $users->get('/list', fn () => 'list');
            });
        })->middleware('auth');

        expect(routeByPattern($router, '/admin')->getMiddleware())->toContain('auth');
        expect(routeByPattern($router, '/admin/users/list')->getMiddleware())->toContain('auth');
    });

    it('applies a name prefix chained after a group to its routes', function () {
        $router = new Router();
        $router->group('/account', function ($group) {
            $group->get('/a', fn () => 'a')->name('a');
        })->name('account.');

        expect(routeByPattern($router, '/account/a')->getName())->toBe('account.a');
    });

    it('returns the nested group so chained options apply to it, not the parent', function () {
        $router = new Router();
        $router->group('/admin', function ($admin) {
            $admin->get('/open', fn () => 'open');
            $admin->group('/secure', function ($secure) {
                $secure->get('/vault', fn () => 'vault');
            })->middleware('auth');
        });

        expect(routeByPattern($router, '/admin/secure/vault')->getMiddleware())->toContain('auth');
        expect(routeByPattern($router, '/admin/open')->getMiddleware())->not->toContain('auth');
    });

    it('keeps route-level middleware on the route it was declared on', function () {
        $router = new Router();
        $router->group('/shop', function ($group) {
            $group->get('/cart', fn () => 'cart')->middleware('session');
            $group->get('/catalog', fn () => 'catalog');
        });

        expect(routeByPattern($router, '/shop/cart')->getMiddleware())->toContain('session');
        expect(routeByPattern($router, '/shop/catalog')->getMiddleware())->not->toContain('session');
    });
});

describe('Trie routes with parameters and wildcards', function () {

    it('serves a route that combines an unconstrained parameter with a wildcard', function () {
        $router = new Router();
        $router->get('/home', fn () => 'home');
        $router->get('/files/{disk}/*', fn (string $disk) => "disk {$disk}");

        $home = $router->dispatch(new ServerRequest([], [], '/home', 'GET'));
        $file = $router->dispatch(new ServerRequest([], [], '/files/s3/a/b', 'GET'));

        expect((string) $home->getBody())->toBe('home');
        expect((string) $file->getBody())->toBe('disk s3');
    });
});
