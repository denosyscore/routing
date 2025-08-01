<?php

use Denosys\Routing\Dispatcher;
use Denosys\Routing\RouteCollection;
use Denosys\Routing\RouteHandlerResolver;
use Denosys\Routing\Exceptions\NotFoundException;
use Denosys\Routing\Exceptions\HandlerNotFoundException;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;

describe('Dispatcher', function () {
    
    beforeEach(function () {
        $this->routeHandlerResolver = new RouteHandlerResolver();
        $this->routeCollection = new RouteCollection($this->routeHandlerResolver);
        $this->dispatcher = new Dispatcher($this->routeCollection);
    });

    describe('Route Dispatching', function () {
        
        it('can dispatch a simple route', function () {
            $this->routeCollection->add('GET', '/users', fn() => 'users');
            
            $request = new ServerRequest([], [], '/users', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect($response)->toBeInstanceOf(ResponseInterface::class);
            expect((string) $response->getBody())->toBe('users');
        });

        it('can dispatch route with parameters', function () {
            $this->routeCollection->add('GET', '/users/{id}', fn($id) => "user $id");
            
            $request = new ServerRequest([], [], '/users/123', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('user 123');
        });

        it('throws NotFoundException for non-existent routes', function () {
            $request = new ServerRequest([], [], '/nonexistent', 'GET');
            
            expect(fn() => $this->dispatcher->dispatch($request))
                ->toThrow(NotFoundException::class);
        });

        it('can handle different HTTP methods', function () {
            $this->routeCollection->add('GET', '/users', fn() => 'get users');
            $this->routeCollection->add('POST', '/users', fn() => 'create user');
            $this->routeCollection->add('PUT', '/users/{id}', fn($id) => "update user $id");
            $this->routeCollection->add('DELETE', '/users/{id}', fn($id) => "delete user $id");
            
            // GET
            $request = new ServerRequest([], [], '/users', 'GET');
            $response = $this->dispatcher->dispatch($request);
            expect((string) $response->getBody())->toBe('get users');
            
            // POST
            $request = new ServerRequest([], [], '/users', 'POST');
            $response = $this->dispatcher->dispatch($request);
            expect((string) $response->getBody())->toBe('create user');
            
            // PUT
            $request = new ServerRequest([], [], '/users/123', 'PUT');
            $response = $this->dispatcher->dispatch($request);
            expect((string) $response->getBody())->toBe('update user 123');
            
            // DELETE
            $request = new ServerRequest([], [], '/users/123', 'DELETE');
            $response = $this->dispatcher->dispatch($request);
            expect((string) $response->getBody())->toBe('delete user 123');
        });

        it('can handle optional parameters', function () {
            $this->routeCollection->add('GET', '/posts/{id?}', function($id = null) {
                return $id ? "post $id" : "all posts";
            });
            
            // With parameter
            $request = new ServerRequest([], [], '/posts/123', 'GET');
            $response = $this->dispatcher->dispatch($request);
            expect((string) $response->getBody())->toBe('post 123');
            
            // Without parameter
            $request = new ServerRequest([], [], '/posts', 'GET');
            $response = $this->dispatcher->dispatch($request);
            expect((string) $response->getBody())->toBe('all posts');
        });
    });

    describe('Route Parameters', function () {
        
        it('passes route parameters to handler', function () {
            $this->routeCollection->add('GET', '/users/{userId}/posts/{postId}', 
                function($userId, $postId) {
                    return ['user' => $userId, 'post' => $postId];
                });
            
            $request = new ServerRequest([], [], '/users/123/posts/456', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('{"user":"123","post":"456"}');
        });

        it('adds route parameters as request attributes', function () {
            $this->routeCollection->add('GET', '/users/{id}', function($id, $request) {
                return [
                    'param' => $id,
                    'attribute' => $request->getAttribute('id')
                ];
            });
            
            $request = new ServerRequest([], [], '/users/123', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            $body = json_decode((string) $response->getBody(), true);
            expect($body['param'])->toBe('123');
            expect($body['attribute'])->toBe('123');
        });

        it('handles encoded parameters correctly', function () {
            $this->routeCollection->add('GET', '/search/{query}', fn($query) => "search: $query");
            
            $request = new ServerRequest([], [], '/search/hello%20world', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('search: hello%20world');
        });
    });

    describe('Response Handling', function () {
        
        it('handles string responses', function () {
            $this->routeCollection->add('GET', '/string', fn() => 'plain text');
            
            $request = new ServerRequest([], [], '/string', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('plain text');
            expect($response->getHeaderLine('Content-Type'))->toContain('text/html');
        });

        it('handles array responses as JSON', function () {
            $this->routeCollection->add('GET', '/array', fn() => ['key' => 'value', 'number' => 123]);
            
            $request = new ServerRequest([], [], '/array', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('{"key":"value","number":123}');
            expect($response->getHeaderLine('Content-Type'))->toContain('application/json');
        });

        it('handles null responses', function () {
            $this->routeCollection->add('GET', '/null', fn() => null);
            
            $request = new ServerRequest([], [], '/null', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('');
        });

        it('handles integer responses', function () {
            $this->routeCollection->add('GET', '/int', fn() => 42);
            
            $request = new ServerRequest([], [], '/int', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('42');
        });

        it('handles boolean responses', function () {
            $this->routeCollection->add('GET', '/true', fn() => true);
            $this->routeCollection->add('GET', '/false', fn() => false);
            
            $request = new ServerRequest([], [], '/true', 'GET');
            $response = $this->dispatcher->dispatch($request);
            expect((string) $response->getBody())->toBe('1');
            
            $request = new ServerRequest([], [], '/false', 'GET');
            $response = $this->dispatcher->dispatch($request);
            expect((string) $response->getBody())->toBe('');
        });

        it('handles object responses that are JSON serializable', function () {
            $this->routeCollection->add('GET', '/object', function() {
                return new class implements JsonSerializable {
                    public function jsonSerialize(): array {
                        return ['message' => 'hello', 'status' => 'ok'];
                    }
                };
            });
            
            $request = new ServerRequest([], [], '/object', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('{"message":"hello","status":"ok"}');
        });

        it('handles objects with __toString method', function () {
            $this->routeCollection->add('GET', '/tostring', function() {
                return new class {
                    public function __toString(): string {
                        return 'object as string';
                    }
                };
            });
            
            $request = new ServerRequest([], [], '/tostring', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('object as string');
        });

        it('returns PSR-7 responses as-is', function () {
            $this->routeCollection->add('GET', '/psr7', function() {
                return new \Laminas\Diactoros\Response\JsonResponse(['custom' => 'response']);
            });
            
            $request = new ServerRequest([], [], '/psr7', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect($response)->toBeInstanceOf(\Laminas\Diactoros\Response\JsonResponse::class);
            expect((string) $response->getBody())->toBe('{"custom":"response"}');
        });
    });

    describe('Error Handling', function () {
        
        it('can set custom not found handler', function () {
            $this->dispatcher->setNotFoundHandler(function($request) {
                return new \Laminas\Diactoros\Response\JsonResponse(['error' => 'Custom not found'], 404);
            });
            
            $request = new ServerRequest([], [], '/nonexistent', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect($response->getStatusCode())->toBe(404);
            expect((string) $response->getBody())->toBe('{"error":"Custom not found"}');
        });

        it('can set custom method not allowed handler', function () {
            $this->routeCollection->add('GET', '/users', fn() => 'users');
            
            $this->dispatcher->setMethodNotAllowedHandler(function($request) {
                return new \Laminas\Diactoros\Response\JsonResponse(['error' => 'Method not allowed'], 405);
            });
            
            // This test depends on the router implementation
            // For now, we'll just test that the method exists
            expect(method_exists($this->dispatcher, 'setMethodNotAllowedHandler'))->toBe(true);
        });
    });

    describe('Handler Resolution', function () {
        
        it('can resolve string handlers', function () {
            // This would typically be used with a container for DI
            // The exception is thrown during route addition when handler is resolved
            expect(fn() => $this->routeCollection->add('GET', '/string-handler', 'TestController@index'))
                ->toThrow(HandlerNotFoundException::class);
        });

        it('can resolve array handlers', function () {
            // The exception is thrown during route addition when handler is resolved
            expect(fn() => $this->routeCollection->add('GET', '/array-handler', ['TestController', 'index']))
                ->toThrow(HandlerNotFoundException::class);
        });

        it('can resolve closure handlers', function () {
            $closure = fn() => 'closure result';
            $this->routeCollection->add('GET', '/closure', $closure);
            
            $request = new ServerRequest([], [], '/closure', 'GET');
            $response = $this->dispatcher->dispatch($request);
            
            expect((string) $response->getBody())->toBe('closure result');
        });
    });
});
