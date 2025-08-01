<?php

use Denosys\Routing\Route;
use Denosys\Routing\RouteHandlerResolver;
use Denosys\Routing\Exceptions\HandlerNotFoundException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// Simple test middleware for route tests
class RouteTestMiddleware implements MiddlewareInterface
{
    public static array $executed = [];
    
    public function __construct(private string $name) {}
    
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        self::$executed[] = $this->name;
        return $handler->handle($request);
    }
}

describe('Route', function () {
    
    beforeEach(function () {
        $this->resolver = new RouteHandlerResolver();
        RouteTestMiddleware::$executed = [];
    });

    describe('Route Creation', function () {
        
        it('can create a route with single method', function () {
            $route = new Route('GET', '/users', fn() => 'users', $this->resolver);
            
            expect($route->getMethods())->toBe(['GET', 'HEAD']);
            expect($route->getPattern())->toBe('/users');
        });

        it('can create a route with multiple methods', function () {
            $route = new Route(['GET', 'POST'], '/users', fn() => 'users', $this->resolver);
            
            expect($route->getMethods())->toBe(['GET', 'POST', 'HEAD']);
        });

        it('generates unique identifiers for routes', function () {
            $route1 = new Route('GET', '/users', fn() => 'users', $this->resolver, 1);
            $route2 = new Route('GET', '/posts', fn() => 'posts', $this->resolver, 2);
            
            expect($route1->getIdentifier())->not->toBe($route2->getIdentifier());
            expect($route1->getIdentifier())->toBe('_route_1');
            expect($route2->getIdentifier())->toBe('_route_2');
        });

        it('accepts different handler types', function () {
            $closureRoute = new Route('GET', '/closure', fn() => 'closure', $this->resolver);
            
            // String and array handlers will throw exceptions for non-existent controllers
            expect(fn() => new Route('GET', '/string', 'Controller@method', $this->resolver))
                ->toThrow(HandlerNotFoundException::class);
            
            expect(fn() => new Route('GET', '/array', ['Controller', 'method'], $this->resolver))
                ->toThrow(HandlerNotFoundException::class);
            
            expect($closureRoute)->toBeInstanceOf(Route::class);
        });
    });

    describe('Route Matching', function () {
        
        it('matches exact paths', function () {
            $route = new Route('GET', '/users', fn() => 'users', $this->resolver);
            
            expect($route->matches('GET', '/users'))->toBe(true);
            expect($route->matches('GET', '/users/'))->toBe(false);
            expect($route->matches('GET', '/user'))->toBe(false);
            expect($route->matches('POST', '/users'))->toBe(false);
        });

        it('matches paths with parameters', function () {
            $route = new Route('GET', '/users/{id}', fn($id) => "user $id", $this->resolver);
            
            expect($route->matches('GET', '/users/123'))->toBe(true);
            expect($route->matches('GET', '/users/abc'))->toBe(true);
            expect($route->matches('GET', '/users'))->toBe(false);
            expect($route->matches('GET', '/users/123/posts'))->toBe(false);
        });

        it('matches paths with multiple parameters', function () {
            $route = new Route('GET', '/users/{userId}/posts/{postId}', 
                fn($userId, $postId) => "user $userId post $postId", $this->resolver);
            
            expect($route->matches('GET', '/users/123/posts/456'))->toBe(true);
            expect($route->matches('GET', '/users/123/posts'))->toBe(false);
            expect($route->matches('GET', '/users/posts/456'))->toBe(false);
        });

        // Note: Optional parameter syntax {id?} is not currently supported in the regex implementation

        it('extracts parameters correctly', function () {
            $route = new Route('GET', '/users/{userId}/posts/{postId}', 
                fn($userId, $postId) => "user $userId post $postId", $this->resolver);
            
            $params = $route->getParameters('/users/123/posts/456');
            expect($params)->toBe(['userId' => '123', 'postId' => '456']);
        });

        it('handles encoded parameters', function () {
            $route = new Route('GET', '/search/{query}', fn($query) => "search: $query", $this->resolver);
            
            $params = $route->getParameters('/search/hello%20world');
            expect($params)->toBe(['query' => 'hello%20world']);
        });
    });

    describe('Route Constraints', function () {
        
        it('can apply where constraints', function () {
            $route = new Route('GET', '/users/{id}', fn($id) => "user $id", $this->resolver);
            $route->where('id', '\d+');
            
            expect($route->matches('GET', '/users/123'))->toBe(true);
            expect($route->matches('GET', '/users/abc'))->toBe(false);
        });

        it('can apply whereNumber constraints', function () {
            $route = new Route('GET', '/posts/{id}', fn($id) => "post $id", $this->resolver);
            $route->whereNumber('id');
            
            expect($route->matches('GET', '/posts/123'))->toBe(true);
            expect($route->matches('GET', '/posts/abc'))->toBe(false);
        });

        it('can apply whereAlpha constraints', function () {
            $route = new Route('GET', '/categories/{name}', fn($name) => "category $name", $this->resolver);
            $route->whereAlpha('name');
            
            expect($route->matches('GET', '/categories/books'))->toBe(true);
            expect($route->matches('GET', '/categories/123'))->toBe(false);
            expect($route->matches('GET', '/categories/books123'))->toBe(false);
        });

        it('can apply whereAlphaNumeric constraints', function () {
            $route = new Route('GET', '/slugs/{slug}', fn($slug) => "slug $slug", $this->resolver);
            $route->whereAlphaNumeric('slug');
            
            expect($route->matches('GET', '/slugs/abc123'))->toBe(true);
            expect($route->matches('GET', '/slugs/abc'))->toBe(true);
            expect($route->matches('GET', '/slugs/123'))->toBe(true);
            expect($route->matches('GET', '/slugs/abc-123'))->toBe(false);
        });

        it('can apply whereIn constraints', function () {
            $route = new Route('GET', '/status/{type}', fn($type) => "status $type", $this->resolver);
            $route->whereIn('type', ['active', 'inactive', 'pending']);
            
            expect($route->matches('GET', '/status/active'))->toBe(true);
            expect($route->matches('GET', '/status/inactive'))->toBe(true);
            expect($route->matches('GET', '/status/pending'))->toBe(true);
            expect($route->matches('GET', '/status/unknown'))->toBe(false);
        });

        it('can apply multiple constraints', function () {
            $route = new Route('GET', '/users/{id}/posts/{slug}', 
                fn($id, $slug) => "user $id post $slug", $this->resolver);
            $route->whereNumber('id')->whereAlphaNumeric('slug');
            
            expect($route->matches('GET', '/users/123/posts/hello'))->toBe(true);
            expect($route->matches('GET', '/users/abc/posts/hello'))->toBe(false);
            expect($route->matches('GET', '/users/123/posts/hello-world'))->toBe(false);
        });

        it('returns constraints', function () {
            $route = new Route('GET', '/users/{id}', fn($id) => "user $id", $this->resolver);
            $route->where('id', '\d+');
            
            $constraints = $route->getConstraints();
            expect($constraints)->toBe(['id' => '\d+']);
        });
    });

    describe('Route Naming', function () {
        
        it('can name routes', function () {
            $route = new Route('GET', '/users', fn() => 'users', $this->resolver);
            $route->name('users.index');
            
            expect($route->getName())->toBe('users.index');
        });

        it('can chain naming with other methods', function () {
            $route = new Route('GET', '/users/{id}', fn($id) => "user $id", $this->resolver);
            $result = $route->name('users.show')->whereNumber('id');
            
            expect($result)->toBe($route); // Fluent interface
            expect($route->getName())->toBe('users.show');
            expect($route->getConstraints())->toBe(['id' => '\\d+']);
        });
    });

    describe('Route Middleware', function () {
        
        it('can add middleware to routes', function () {
            $route = new Route('GET', '/protected', fn() => 'protected', $this->resolver);
            $middleware = new RouteTestMiddleware('Auth');
            
            $result = $route->middleware($middleware);
            
            expect($result)->toBe($route); // Fluent interface
            expect(iterator_to_array($route->getMiddlewareStack()))->toHaveCount(1);
        });

        it('can add multiple middleware', function () {
            $route = new Route('GET', '/protected', fn() => 'protected', $this->resolver);
            $auth = new RouteTestMiddleware('Auth');
            $cors = new RouteTestMiddleware('CORS');
            
            $route->middleware([$auth, $cors]);
            
            expect(iterator_to_array($route->getMiddlewareStack()))->toHaveCount(2);
        });

        it('can chain middleware calls', function () {
            $route = new Route('GET', '/protected', fn() => 'protected', $this->resolver);
            $auth = new RouteTestMiddleware('Auth');
            $cors = new RouteTestMiddleware('CORS');
            
            $route->middleware($auth)->middleware($cors);
            
            expect(iterator_to_array($route->getMiddlewareStack()))->toHaveCount(2);
        });

        it('can add conditional middleware', function () {
            $route = new Route('GET', '/test', fn() => 'test', $this->resolver);
            $middleware = new RouteTestMiddleware('Conditional');
            
            $route->middlewareWhen(true, $middleware);
            $route->middlewareUnless(false, $middleware);
            
            expect(iterator_to_array($route->getMiddlewareStack()))->toHaveCount(2);
        });

        it('skips conditional middleware when condition is not met', function () {
            $route = new Route('GET', '/test', fn() => 'test', $this->resolver);
            $middleware = new RouteTestMiddleware('Conditional');
            
            $route->middlewareWhen(false, $middleware);
            $route->middlewareUnless(true, $middleware);
            
            expect(iterator_to_array($route->getMiddlewareStack()))->toHaveCount(0);
        });

        it('can prepend middleware', function () {
            $route = new Route('GET', '/test', fn() => 'test', $this->resolver);
            $normal = new RouteTestMiddleware('Normal');
            $prepended = new RouteTestMiddleware('Prepended');
            
            $route->middleware($normal)->prependMiddleware($prepended);
            
            $middlewareStack = iterator_to_array($route->getMiddlewareStack());
            expect($middlewareStack)->toHaveCount(2);
            
            // Check that prepended middleware has higher priority
            $priorities = array_map(fn($item) => $item->priority, $middlewareStack);
            expect(max($priorities))->toBe(1000); // Default prepend priority
        });

        it('handles middleware priority correctly', function () {
            $route = new Route('GET', '/test', fn() => 'test', $this->resolver);
            
            $route->middleware(new RouteTestMiddleware('Low'), 0);
            $route->middleware(new RouteTestMiddleware('High'), 100);
            $route->middleware(new RouteTestMiddleware('Medium'), 50);
            
            $middlewareStack = iterator_to_array($route->getMiddlewareStack());
            expect($middlewareStack)->toHaveCount(3);
            
            // Verify priorities are stored correctly
            $priorities = array_map(fn($item) => $item->priority, $middlewareStack);
            expect($priorities)->toContain(0, 50, 100);
        });
    });

    describe('Route Method Chaining', function () {
        
        it('supports full fluent interface', function () {
            $route = new Route('GET', '/users/{id}', fn($id) => "user $id", $this->resolver);
            $middleware = new RouteTestMiddleware('Auth');
            
            $result = $route
                ->name('users.show')
                ->whereNumber('id')
                ->middleware($middleware);
            
            expect($result)->toBe($route);
            expect($route->getName())->toBe('users.show');
            expect($route->getConstraints())->toBe(['id' => '\\d+']);
            expect(iterator_to_array($route->getMiddlewareStack()))->toHaveCount(1);
        });

        it('can chain in any order', function () {
            $route = new Route('GET', '/posts/{id}', fn($id) => "post $id", $this->resolver);
            $middleware = new RouteTestMiddleware('Auth');
            
            $result = $route
                ->middleware($middleware)
                ->whereNumber('id')
                ->name('posts.show');
            
            expect($result)->toBe($route);
            expect($route->getName())->toBe('posts.show');
            expect($route->getConstraints())->toBe(['id' => '\\d+']);
            expect(iterator_to_array($route->getMiddlewareStack()))->toHaveCount(1);
        });
    });

    describe('Edge Cases', function () {
        
        it('handles empty path', function () {
            $route = new Route('GET', '', fn() => 'root', $this->resolver);
            
            expect($route->matches('GET', ''))->toBe(true);
            expect($route->matches('GET', '/'))->toBe(false);
        });

        it('handles root path', function () {
            $route = new Route('GET', '/', fn() => 'root', $this->resolver);
            
            expect($route->matches('GET', '/'))->toBe(true);
            expect($route->matches('GET', ''))->toBe(false);
        });

        it('handles paths with trailing slashes', function () {
            $route = new Route('GET', '/users/', fn() => 'users', $this->resolver);
            
            expect($route->matches('GET', '/users/'))->toBe(true);
            expect($route->matches('GET', '/users'))->toBe(false);
        });

        it('handles special characters in parameters', function () {
            $route = new Route('GET', '/files/{filename}', fn($filename) => "file $filename", $this->resolver);
            
            expect($route->matches('GET', '/files/test.txt'))->toBe(true);
            expect($route->matches('GET', '/files/my-file_v2.pdf'))->toBe(true);
            
            $params = $route->getParameters('/files/test.txt');
            expect($params)->toBe(['filename' => 'test.txt']);
        });

        it('handles case sensitivity', function () {
            $route = new Route('GET', '/Users', fn() => 'users', $this->resolver);
            
            expect($route->matches('GET', '/Users'))->toBe(true);
            expect($route->matches('GET', '/users'))->toBe(false);
            expect($route->matches('get', '/Users'))->toBe(false);
        });

        it('handles complex parameter patterns', function () {
            $route = new Route('GET', '/api/{version}/{type}/{id}', 
                fn($version, $type, $id) => "api $version $type $id", $this->resolver);
            
            expect($route->matches('GET', '/api/v1/users/123'))->toBe(true);
            
            $params = $route->getParameters('/api/v1/users/123');
            expect($params)->toBe([
                'version' => 'v1',
                'type' => 'users',
                'id' => '123'
            ]);
        });
    });
});
