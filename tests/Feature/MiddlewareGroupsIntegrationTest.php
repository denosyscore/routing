<?php

declare(strict_types=1);

use Denosys\Routing\Router;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

// Test middleware classes
class AddHeaderMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $headerName,
        private string $headerValue
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader($this->headerName, $this->headerValue);
    }
}

class FirstMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader('X-First', 'true');
    }
}

class SecondMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader('X-Second', 'true');
    }
}

class ThirdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader('X-Third', 'true');
    }
}

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader('X-Auth', 'authenticated');
    }
}

class AdminMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader('X-Admin', 'admin');
    }
}

describe('Middleware Groups Integration', function () {
    beforeEach(function () {
        $this->router = new Router();
    });

    describe('Alias Registration', function () {
        it('can register middleware alias and use it on route', function () {
            $this->router->aliasMiddleware('first', FirstMiddleware::class);

            $this->router->get('/test', fn() => 'Hello')
                ->middleware('first');

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
        });

        it('can register multiple aliases', function () {
            $this->router->aliasMiddleware('first', FirstMiddleware::class);
            $this->router->aliasMiddleware('second', SecondMiddleware::class);

            $this->router->get('/test', fn() => 'Hello')
                ->middleware(['first', 'second']);

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
        });
    });

    describe('Group Registration', function () {
        it('can register middleware group and use it on route', function () {
            $this->router->middlewareGroup('web', [
                FirstMiddleware::class,
                SecondMiddleware::class,
            ]);

            $this->router->get('/test', fn() => 'Hello')
                ->middleware('web');

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
        });

        it('can use aliases within groups', function () {
            $this->router->aliasMiddleware('first', FirstMiddleware::class);
            $this->router->aliasMiddleware('second', SecondMiddleware::class);
            $this->router->middlewareGroup('web', ['first', 'second']);

            $this->router->get('/test', fn() => 'Hello')
                ->middleware('web');

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
        });

        it('can nest groups within groups', function () {
            $this->router->aliasMiddleware('first', FirstMiddleware::class);
            $this->router->aliasMiddleware('second', SecondMiddleware::class);
            $this->router->aliasMiddleware('third', ThirdMiddleware::class);

            $this->router->middlewareGroup('base', ['first', 'second']);
            $this->router->middlewareGroup('extended', ['base', 'third']);

            $this->router->get('/test', fn() => 'Hello')
                ->middleware('extended');

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
            expect($response->getHeader('X-Third'))->toBe(['true']);
        });
    });

    describe('Group Modification', function () {
        it('can prepend middleware to existing group', function () {
            $this->router->aliasMiddleware('first', FirstMiddleware::class);
            $this->router->aliasMiddleware('second', SecondMiddleware::class);

            $this->router->middlewareGroup('web', ['second']);
            $this->router->prependMiddlewareToGroup('web', 'first');

            $this->router->get('/test', fn() => 'Hello')
                ->middleware('web');

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
        });

        it('can append middleware to existing group', function () {
            $this->router->aliasMiddleware('first', FirstMiddleware::class);
            $this->router->aliasMiddleware('second', SecondMiddleware::class);

            $this->router->middlewareGroup('web', ['first']);
            $this->router->appendMiddlewareToGroup('web', 'second');

            $this->router->get('/test', fn() => 'Hello')
                ->middleware('web');

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
        });
    });

    describe('Route Groups with Middleware Groups', function () {
        it('can apply middleware group to route group', function () {
            $this->router->aliasMiddleware('auth', AuthMiddleware::class);
            $this->router->middlewareGroup('admin', [AuthMiddleware::class, AdminMiddleware::class]);

            $this->router->middleware('admin')->group('/admin', function ($group) {
                $group->get('/dashboard', fn() => 'Dashboard');
            });

            $request = new ServerRequest([], [], '/admin/dashboard', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-Auth'))->toBe(['authenticated']);
            expect($response->getHeader('X-Admin'))->toBe(['admin']);
        });
    });

    describe('Mixed Middleware', function () {
        it('can mix aliases, groups, and direct classes', function () {
            $this->router->aliasMiddleware('first', FirstMiddleware::class);
            $this->router->middlewareGroup('web', [SecondMiddleware::class]);

            $this->router->get('/test', fn() => 'Hello')
                ->middleware(['first', 'web', ThirdMiddleware::class]);

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
            expect($response->getHeader('X-Third'))->toBe(['true']);
        });
    });

    describe('Registry Access', function () {
        it('can access middleware registry directly', function () {
            $this->router->aliasMiddleware('auth', AuthMiddleware::class);

            $registry = $this->router->getMiddlewareRegistry();

            expect($registry->hasAlias('auth'))->toBeTrue();
            expect($registry->getAlias('auth'))->toBe(AuthMiddleware::class);
        });

        it('registry is shared between router and dispatcher', function () {
            $this->router->aliasMiddleware('first', FirstMiddleware::class);
            $this->router->middlewareGroup('web', ['first']);

            // Route uses the group
            $this->router->get('/test', fn() => 'Hello')
                ->middleware('web');

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            // Middleware should have been applied
            expect($response->getHeader('X-First'))->toBe(['true']);
        });
    });

    describe('Fluent Interface', function () {
        it('supports fluent configuration', function () {
            $router = (new Router())
                ->aliasMiddleware('first', FirstMiddleware::class)
                ->aliasMiddleware('second', SecondMiddleware::class)
                ->middlewareGroup('web', ['first', 'second']);

            $router->get('/test', fn() => 'Hello')
                ->middleware('web');

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
        });
    });

    describe('Global Middleware', function () {
        it('applies global middleware to all routes', function () {
            $this->router->use(FirstMiddleware::class);

            // Create multiple routes AFTER adding global middleware
            $this->router->get('/first', fn() => 'First');
            $this->router->get('/second', fn() => 'Second');
            $this->router->get('/third', fn() => 'Third');

            // All routes should have the global middleware applied
            $request1 = new ServerRequest([], [], '/first', 'GET');
            $response1 = $this->router->dispatch($request1);
            expect($response1->getHeader('X-First'))->toBe(['true']);

            $request2 = new ServerRequest([], [], '/second', 'GET');
            $response2 = $this->router->dispatch($request2);
            expect($response2->getHeader('X-First'))->toBe(['true']);

            $request3 = new ServerRequest([], [], '/third', 'GET');
            $response3 = $this->router->dispatch($request3);
            expect($response3->getHeader('X-First'))->toBe(['true']);
        });

        it('applies multiple global middleware to all routes', function () {
            $this->router->use(FirstMiddleware::class);
            $this->router->use(SecondMiddleware::class);

            $this->router->get('/test', fn() => 'Test');
            $this->router->get('/other', fn() => 'Other');

            $request1 = new ServerRequest([], [], '/test', 'GET');
            $response1 = $this->router->dispatch($request1);
            expect($response1->getHeader('X-First'))->toBe(['true']);
            expect($response1->getHeader('X-Second'))->toBe(['true']);

            $request2 = new ServerRequest([], [], '/other', 'GET');
            $response2 = $this->router->dispatch($request2);
            expect($response2->getHeader('X-First'))->toBe(['true']);
            expect($response2->getHeader('X-Second'))->toBe(['true']);
        });

        it('applies global middleware to route groups', function () {
            $this->router->use(FirstMiddleware::class);

            $this->router->group('/api', function ($group) {
                $group->get('/users', fn() => 'Users');
                $group->get('/posts', fn() => 'Posts');
            });

            $request1 = new ServerRequest([], [], '/api/users', 'GET');
            $response1 = $this->router->dispatch($request1);
            expect($response1->getHeader('X-First'))->toBe(['true']);

            $request2 = new ServerRequest([], [], '/api/posts', 'GET');
            $response2 = $this->router->dispatch($request2);
            expect($response2->getHeader('X-First'))->toBe(['true']);
        });

        it('combines global middleware with route-specific middleware', function () {
            $this->router->use(FirstMiddleware::class);

            $this->router->get('/test', fn() => 'Test')
                ->middleware(SecondMiddleware::class);

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            // Both global and route-specific middleware should be applied
            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
        });

        it('supports array syntax for adding multiple global middleware', function () {
            $this->router->use([FirstMiddleware::class, SecondMiddleware::class]);

            $this->router->get('/test', fn() => 'Test');

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);

            expect($response->getHeader('X-First'))->toBe(['true']);
            expect($response->getHeader('X-Second'))->toBe(['true']);
        });

        it('applies global middleware to routes defined before use() call', function () {
            // Define routes BEFORE adding global middleware
            $this->router->get('/first', fn() => 'First');
            $this->router->get('/second', fn() => 'Second');

            // Add global middleware AFTER routes
            $this->router->use(FirstMiddleware::class);

            // Global middleware should still apply (dispatch-time)
            $request1 = new ServerRequest([], [], '/first', 'GET');
            $response1 = $this->router->dispatch($request1);
            expect($response1->getHeader('X-First'))->toBe(['true']);

            $request2 = new ServerRequest([], [], '/second', 'GET');
            $response2 = $this->router->dispatch($request2);
            expect($response2->getHeader('X-First'))->toBe(['true']);
        });

        it('executes global middleware before route-specific middleware', function () {
            // Track middleware execution order
            $order = [];

            $globalMiddleware = new class($order) implements MiddlewareInterface {
                private array $order;
                public function __construct(array &$order) { $this->order = &$order; }
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                    $this->order[] = 'global';
                    return $handler->handle($request);
                }
            };

            $routeMiddleware = new class($order) implements MiddlewareInterface {
                private array $order;
                public function __construct(array &$order) { $this->order = &$order; }
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                    $this->order[] = 'route';
                    return $handler->handle($request);
                }
            };

            $this->router->use($globalMiddleware);
            $this->router->get('/test', fn() => 'Test')->middleware($routeMiddleware);

            $request = new ServerRequest([], [], '/test', 'GET');
            $this->router->dispatch($request);

            expect($order)->toBe(['global', 'route']);
        });
    });
});
