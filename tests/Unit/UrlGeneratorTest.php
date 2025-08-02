<?php

use Denosys\Routing\Router;
use Denosys\Routing\UrlGenerator;
use Denosys\Routing\Exceptions\RouteNotFoundException;

describe('UrlGenerator', function () {
    
    beforeEach(function () {
        $this->router = new Router();
        $this->urlGenerator = new UrlGenerator($this->router->getRouteCollection());
    });

    describe('Basic URL Generation', function () {
        
        it('can generate URL for named route without parameters', function () {
            $this->router->get('/users', fn() => 'users')->name('users.index');
            
            $url = $this->urlGenerator->route('users.index');
            
            expect($url)->toBe('/users');
        });

        it('can generate URL for named route with single parameter', function () {
            $this->router->get('/users/{id}', fn($id) => "user $id")->name('users.show');
            
            $url = $this->urlGenerator->route('users.show', ['id' => 123]);
            
            expect($url)->toBe('/users/123');
        });

        it('can generate URL for named route with multiple parameters', function () {
            $this->router->get('/users/{userId}/posts/{postId}', fn($userId, $postId) => 'post')
                         ->name('users.posts.show');
            
            $url = $this->urlGenerator->route('users.posts.show', [
                'userId' => 123,
                'postId' => 456
            ]);
            
            expect($url)->toBe('/users/123/posts/456');
        });

        it('throws exception for non-existent route name', function () {
            expect(fn() => $this->urlGenerator->route('non.existent'))
                ->toThrow(RouteNotFoundException::class, "Route [non.existent] not found");
        });

        it('can check if route exists', function () {
            $this->router->get('/users', fn() => 'users')->name('users.index');
            
            expect($this->urlGenerator->hasRoute('users.index'))->toBe(true);
            expect($this->urlGenerator->hasRoute('non.existent'))->toBe(false);
        });
    });

    describe('URL Generation with Base URL', function () {
        
        it('can generate URL with base URL', function () {
            $urlGenerator = new UrlGenerator(
                $this->router->getRouteCollection(),
                'https://example.com'
            );
            
            $this->router->get('/users/{id}', fn($id) => "user $id")->name('users.show');
            
            $url = $urlGenerator->route('users.show', ['id' => 123]);
            
            expect($url)->toBe('https://example.com/users/123');
        });

        it('handles base URL with trailing slash correctly', function () {
            $urlGenerator = new UrlGenerator(
                $this->router->getRouteCollection(),
                'https://example.com/'
            );
            
            $this->router->get('/users', fn() => 'users')->name('users.index');
            
            $url = $urlGenerator->route('users.index');
            
            expect($url)->toBe('https://example.com/users');
        });
    });

    describe('Route Groups and Naming', function () {
        
        it('can generate URLs for routes in groups with name prefix', function () {
            $this->router->group('/api', function($group) {
                $group->name('api')->group('/v1', function($v1) {
                    $v1->get('/users', fn() => 'users')->name('users.index');
                    $v1->get('/users/{id}', fn($id) => "user $id")->name('users.show');
                });
            });

            $url = $this->urlGenerator->route('api.users.index');
            expect($url)->toBe('/api/v1/users');

            $url = $this->urlGenerator->route('api.users.show', ['id' => 123]);
            expect($url)->toBe('/api/v1/users/123');
        });
    });

    describe('Parameter Validation', function () {
        
        it('throws exception when required parameter is missing', function () {
            $this->router->get('/users/{id}', fn($id) => "user $id")->name('users.show');

            expect(fn() => $this->urlGenerator->route('users.show'))
                ->toThrow(InvalidArgumentException::class, "Missing required parameter [id] for route [users.show]");
        });

        it('ignores extra parameters not in route pattern', function () {
            $this->router->get('/users', fn() => 'users')->name('users.index');

            $url = $this->urlGenerator->route('users.index', ['extra' => 'param']);

            expect($url)->toBe('/users');
        });
    });

    describe('Route Helper Function', function () {
        
        it('can use global route helper function', function () {
            $this->router->get('/users/{id}', fn($id) => "user $id")->name('users.show');
            
            // Set up global router for helper function
            $GLOBALS['router'] = $this->router;
            
            $url = route('users.show', ['id' => 123]);
            
            expect($url)->toBe('/users/123');
        });

        it('can use route helper with no parameters', function () {
            $this->router->get('/dashboard', fn() => 'dashboard')->name('dashboard');
            
            $GLOBALS['router'] = $this->router;
            
            $url = route('dashboard');
            
            expect($url)->toBe('/dashboard');
        });
    });

    describe('Edge Cases', function () {
        
        it('handles routes with optional parameters', function () {
            $this->router->get('/posts/{slug?}', fn($slug = null) => 'posts')->name('posts.show');
            
            // With parameter
            $url = $this->urlGenerator->route('posts.show', ['slug' => 'my-post']);
            expect($url)->toBe('/posts/my-post');
            
            // Without parameter
            $url = $this->urlGenerator->route('posts.show');
            expect($url)->toBe('/posts/');
        });

        it('handles special characters in parameters', function () {
            $this->router->get('/search/{query}', fn($query) => 'search')->name('search');
            
            $url = $this->urlGenerator->route('search', ['query' => 'hello world!']);
            
            // Should URL encode special characters
            expect($url)->toBe('/search/hello%20world%21');
        });

        it('handles numeric route parameters', function () {
            $this->router->get('/users/{id}', fn($id) => "user $id")
                         ->name('users.show')
                         ->whereNumber('id');

            $url = $this->urlGenerator->route('users.show', ['id' => 123]);

            expect($url)->toBe('/users/123');
        });
    });
});
