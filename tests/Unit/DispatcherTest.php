<?php

use DI\Container;
use GuzzleHttp\Psr7\Response;
use Denosys\Routing\Dispatcher;
use Denosys\Routing\RouteManager;
use Denosys\Routing\RouteCollection;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Denosys\Routing\RouteHandlerResolver;
use Psr\Http\Message\ServerRequestInterface;
use Denosys\Routing\Exceptions\NotFoundException;
use Denosys\Routing\Exceptions\HandlerNotFoundException;
use Denosys\Routing\Strategy\InvocationStrategyInterface;
use Denosys\Routing\RouteHandlerResolverInterface;

describe('Dispatcher', function () {

    beforeEach(function () {
        $this->container = new Container();
        $this->routeHandlerResolver = new RouteHandlerResolver($this->container);
        $this->routeCollection = new RouteCollection();
        $this->routeManager = new RouteManager();
        $this->dispatcher = Dispatcher::withDefaults(
            $this->routeCollection,
            $this->routeManager,
            $this->container,
            routeHandlerResolver: $this->routeHandlerResolver
        );
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

            $this->dispatcher->setMethodNotAllowedHandler(function($request, array $allowed) {
                sort($allowed);

                return new \Laminas\Diactoros\Response\JsonResponse([
                    'error' => 'Method not allowed',
                    'allowed' => $allowed,
                ], 405);
            });

            $request = new ServerRequest([], [], '/users', 'POST');
            $response = $this->dispatcher->dispatch($request);

            expect($response->getStatusCode())->toBe(405);
            expect((string) $response->getBody())->toBe('{"error":"Method not allowed","allowed":["GET","HEAD"]}');
        });

        it('throws MethodNotAllowedException when route exists for other methods', function () {
            $this->routeCollection->add('GET', '/users', fn() => 'users');

            $request = new ServerRequest([], [], '/users', 'PUT');

            expect(fn() => $this->dispatcher->dispatch($request))
                ->toThrow(\Denosys\Routing\Exceptions\MethodNotAllowedException::class);
        });

        it('can set custom invocation strategy', function () {
            $customStrategy = new class implements InvocationStrategyInterface {
                public function invoke(
                    callable $handler,
                    ServerRequestInterface $request,
                    array $routeArguments
                ): ResponseInterface {
                    return new Response(body: 'Custom Invocation');
                }
            };

            $this->dispatcher->setInvocationStrategy($customStrategy);

            $this->routeCollection->add('GET', '/test', function () {
                // Does nothing...
            });

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('Custom Invocation');
        });

        it('handle method works as PSR-15 RequestHandler', function () {
            $this->routeCollection->add('GET', '/handler-test', fn() => 'handled');

            // Initialize the trie first using dispatch
            $request = new ServerRequest([], [], '/handler-test', 'GET');
            $response = $this->dispatcher->dispatch($request);

            // Now handle() should work with initialized trie
            $response2 = $this->dispatcher->handle($request);

            expect($response2)->toBeInstanceOf(ResponseInterface::class);
            expect((string) $response2->getBody())->toBe('handled');
        });

        it('handle method throws NotFoundException for invalid routes', function () {
            $request = new ServerRequest([], [], '/invalid-handler', 'GET');

            expect(fn() => $this->dispatcher->handle($request))
                ->toThrow(NotFoundException::class);
        });
    });

    describe('Handler Resolution', function () {

        it('can resolve string handlers', function () {
            $this->routeCollection->add('GET', '/string-handler', 'TestController@index');

            $request = new ServerRequest([], [], '/string-handler', 'GET');

            expect(fn() => $this->dispatcher->dispatch($request))
                ->toThrow(HandlerNotFoundException::class);
        });

        it('can resolve array handlers', function () {
            $this->routeCollection->add('GET', '/array-handler', ['TestController', 'index']);

            $request = new ServerRequest([], [], '/array-handler', 'GET');

            expect(fn() => $this->dispatcher->dispatch($request))
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

    describe('Dependency Injection', function () {

        it('uses injected strategy and resolver from constructor', function () {
            $customStrategy = new class implements InvocationStrategyInterface {
                public function invoke(
                    callable $handler,
                    ServerRequestInterface $request,
                    array $routeArguments
                ): ResponseInterface {
                    return new Response(body: 'resolved-via-custom');
                }
            };

            $customResolver = new class implements RouteHandlerResolverInterface {
                public int $calls = 0;

                public function resolve(\Closure|array|string $handler): callable
                {
                    $this->calls++;
                    return fn() => 'ignored';
                }
            };

            $dispatcher = new Dispatcher(
                $this->routeCollection,
                $this->routeManager,
                $customStrategy,
                $customResolver,
                $this->container
            );

            $this->routeCollection->add('GET', '/custom', 'custom-handler');

            $response = $dispatcher->dispatch(new ServerRequest([], [], '/custom', 'GET'));

            expect((string) $response->getBody())->toBe('resolved-via-custom');
            expect($customResolver->calls)->toBe(1);
        });
    });

    describe('TrieNode', function () {

        it('creates static child nodes', function () {
            $node = new \Denosys\Routing\TrieNode();
            $node->staticChildren['users'] = new \Denosys\Routing\TrieNode();

            expect($node->findChild('users'))->toBeInstanceOf(\Denosys\Routing\TrieNode::class)
                ->and($node->findChild('posts'))->toBeNull();
        });

        it('creates parameter node with constraint', function () {
            $node = new \Denosys\Routing\TrieNode();
            $node->parameterNode = new \Denosys\Routing\TrieNode('id', '\\d+');

            expect($node->findChild('123'))->toBeInstanceOf(\Denosys\Routing\TrieNode::class)
                ->and($node->findChild('abc'))->toBeNull();
        });

        it('creates wildcard node', function () {
            $node = new \Denosys\Routing\TrieNode();
            $node->wildcardNode = new \Denosys\Routing\TrieNode('rest', null, false, true);

            expect($node->findChild('anything'))->toBeInstanceOf(\Denosys\Routing\TrieNode::class);
        });

        it('matches constraint correctly', function () {
            $numericNode = new \Denosys\Routing\TrieNode('id', '\\d+');
            expect($numericNode->matchesConstraint('123'))->toBeTrue()
                ->and($numericNode->matchesConstraint('abc'))->toBeFalse();

            $alphaNode = new \Denosys\Routing\TrieNode('name', '[a-z]+');
            expect($alphaNode->matchesConstraint('john'))->toBeTrue()
                ->and($alphaNode->matchesConstraint('123'))->toBeFalse();
        });

        it('handles optional parameters', function () {
            $node = new \Denosys\Routing\TrieNode('page', '\\d+', true);
            expect($node->isOptional)->toBeTrue();
        });

        it('handles wildcard parameters', function () {
            $node = new \Denosys\Routing\TrieNode('path', null, false, true);
            expect($node->isWildcard)->toBeTrue();
        });

        it('caches compiled constraints', function () {
            $node1 = new \Denosys\Routing\TrieNode('id1', '\\d+');
            $node2 = new \Denosys\Routing\TrieNode('id2', '\\d+');

            // Both should use the same compiled constraint from cache
            expect($node1->matchesConstraint('123'))->toBeTrue()
                ->and($node2->matchesConstraint('456'))->toBeTrue();
        });
    });

    describe('CacheBuilder', function () {

        it('can build route cache', function () {
            $cacheFile = sys_get_temp_dir() . '/test-cache-' . uniqid() . '.php';
            $builder = new \Denosys\Routing\CacheBuilder();

            $this->routeCollection->add('GET', '/cached', fn() => 'cached');
            $this->routeCollection->add('POST', '/cached', fn() => 'post');

            // Test that buildRouteCache doesn't throw exceptions
            expect(fn() => $builder->buildRouteCache($this->routeCollection, $cacheFile))
                ->not->toThrow(Exception::class);

            expect($cacheFile)->toBeFile();

            // Clean up
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        });
    });

    describe('Constraint Enforcement', function () {

        it('should match valid numeric parameter', function () {
            $route = $this->routeCollection->add('GET', '/users/{id}', function($id) {
                return "User: $id";
            });
            $route->whereNumber('id');

            $request = new ServerRequest([], [], '/users/123', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('User: 123');
        });

        it('should throw 404 for non-numeric parameter', function () {
            $route = $this->routeCollection->add('GET', '/users/{id}', function($id) {
                return "User: $id";
            });
            $route->whereNumber('id');

            $request = new ServerRequest([], [], '/users/john', 'GET');

            expect(fn() => $this->dispatcher->dispatch($request))
                ->toThrow(NotFoundException::class);
        });

        it('should throw 404 for alphanumeric string when number expected', function () {
            $route = $this->routeCollection->add('GET', '/users/{id}', function($id) {
                return "User: $id";
            });
            $route->whereNumber('id');

            $request = new ServerRequest([], [], '/users/123abc', 'GET');

            expect(fn() => $this->dispatcher->dispatch($request))
                ->toThrow(NotFoundException::class);
        });

        it('should match valid alphabetic parameter', function () {
            $route = $this->routeCollection->add('GET', '/categories/{name}', function($name) {
                return "Category: $name";
            });
            $route->whereAlpha('name');

            $request = new ServerRequest([], [], '/categories/technology', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('Category: technology');
        });

        it('should throw 404 for numeric parameter when alpha expected', function () {
            $route = $this->routeCollection->add('GET', '/categories/{name}', function($name) {
                return "Category: $name";
            });
            $route->whereAlpha('name');

            $request = new ServerRequest([], [], '/categories/123', 'GET');

            expect(fn() => $this->dispatcher->dispatch($request))
                ->toThrow(NotFoundException::class);
        });

        it('should match parameter in allowed values', function () {
            $route = $this->routeCollection->add('GET', '/posts/{status}', function($status) {
                return "Posts: $status";
            });
            $route->whereIn('status', ['draft', 'published', 'archived']);

            $request = new ServerRequest([], [], '/posts/published', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('Posts: published');
        });

        it('should throw 404 for parameter not in allowed values', function () {
            $route = $this->routeCollection->add('GET', '/posts/{status}', function($status) {
                return "Posts: $status";
            });
            $route->whereIn('status', ['draft', 'published', 'archived']);

            $request = new ServerRequest([], [], '/posts/deleted', 'GET');

            expect(fn() => $this->dispatcher->dispatch($request))
                ->toThrow(NotFoundException::class);
        });

        it('should enforce multiple constraints', function () {
            $route = $this->routeCollection->add('GET', '/users/{id}/posts/{slug}', function($id, $slug) {
                return "User $id, Post: $slug";
            });
            $route->whereNumber('id')->whereAlphaNumeric('slug');

            $request = new ServerRequest([], [], '/users/123/posts/mypost123', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('User 123, Post: mypost123');
        });

        it('should throw 404 if any constraint fails', function () {
            $route = $this->routeCollection->add('GET', '/users/{id}/posts/{slug}', function($id, $slug) {
                return "User $id, Post: $slug";
            });
            $route->whereNumber('id')->whereAlphaNumeric('slug');

            $request = new ServerRequest([], [], '/users/john/posts/mypost123', 'GET');

            expect(fn() => $this->dispatcher->dispatch($request))
                ->toThrow(NotFoundException::class);
        });
    });

    describe('Exception Handling', function () {

        it('NotFoundException includes method and path in message', function () {
            $request = new ServerRequest([], [], '/nonexistent', 'GET');

            try {
                $this->dispatcher->dispatch($request);
                expect(true)->toBeFalse(); // Should not reach here
            } catch (NotFoundException $e) {
                expect($e->getMessage())->toContain('GET')
                    ->and($e->getMessage())->toContain('/nonexistent')
                    ->and($e->getCode())->toBe(404);
            }
        });

        it('NotFoundException can be caught and handled', function () {
            $request = new ServerRequest([], [], '/missing', 'POST');

            $exceptionCaught = false;
            try {
                $this->dispatcher->dispatch($request);
            } catch (NotFoundException $e) {
                $exceptionCaught = true;
            }

            expect($exceptionCaught)->toBeTrue();
        });
    });

    describe('Path Normalization', function () {

        it('normalizes empty path to root', function () {
            $this->routeCollection->add('GET', '/', fn() => 'root');

            $request = new ServerRequest([], [], '', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('root');
        });

        it('removes trailing slashes from paths', function () {
            $this->routeCollection->add('GET', '/users', fn() => 'users');

            $request = new ServerRequest([], [], '/users/', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('users');
        });

        it('preserves root path trailing slash', function () {
            $this->routeCollection->add('GET', '/', fn() => 'root');

            $request = new ServerRequest([], [], '/', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('root');
        });
    });

    describe('HTTP Exceptions', function () {

        it('BadRequestException has correct status code and message', function () {
            $exception = new \Denosys\Routing\Exceptions\BadRequestException('Bad input');
            expect($exception->getStatusCode())->toBe(400)
                ->and($exception->getMessage())->toBe('Bad input')
                ->and($exception->getReasonPhrase())->toBe('Bad Request');
        });

        it('UnauthorizedException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\UnauthorizedException();
            expect($exception->getStatusCode())->toBe(401)
                ->and($exception->getReasonPhrase())->toBe('Unauthorized');
        });

        it('ForbiddenException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\ForbiddenException('Access denied');
            expect($exception->getStatusCode())->toBe(403)
                ->and($exception->getMessage())->toBe('Access denied');
        });

        it('MethodNotAllowedException stores allowed methods', function () {
            $exception = new \Denosys\Routing\Exceptions\MethodNotAllowedException(['GET', 'POST']);
            expect($exception->getStatusCode())->toBe(405)
                ->and($exception->getAllowedMethods())->toBe(['GET', 'POST'])
                ->and($exception->getReasonPhrase())->toBe('Method Not Allowed');
        });

        it('NotAcceptableException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\NotAcceptableException();
            expect($exception->getStatusCode())->toBe(406)
                ->and($exception->getReasonPhrase())->toBe('Not Acceptable');
        });

        it('ConflictException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\ConflictException('Resource conflict');
            expect($exception->getStatusCode())->toBe(409);
        });

        it('GoneException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\GoneException();
            expect($exception->getStatusCode())->toBe(410)
                ->and($exception->getReasonPhrase())->toBe('Gone');
        });

        it('LengthRequiredException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\LengthRequiredException();
            expect($exception->getStatusCode())->toBe(411)
                ->and($exception->getReasonPhrase())->toBe('Length Required');
        });

        it('PreconditionFailedException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\PreconditionFailedException();
            expect($exception->getStatusCode())->toBe(412)
                ->and($exception->getReasonPhrase())->toBe('Precondition Failed');
        });

        it('UnsupportedMediaTypeException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\UnsupportedMediaTypeException();
            expect($exception->getStatusCode())->toBe(415)
                ->and($exception->getReasonPhrase())->toBe('Unsupported Media Type');
        });

        it('ExpectationFailedException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\ExpectationFailedException();
            expect($exception->getStatusCode())->toBe(417)
                ->and($exception->getReasonPhrase())->toBe('Expectation Failed');
        });

        it('ImATeapotException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\ImATeapotException();
            expect($exception->getStatusCode())->toBe(418)
                ->and($exception->getReasonPhrase())->toBe('I\'m a teapot');
        });

        it('UnprocessableContentException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\UnprocessableContentException();
            expect($exception->getStatusCode())->toBe(422)
                ->and($exception->getReasonPhrase())->toBe('Unprocessable Content');
        });

        it('PreconditionRequiredException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\PreconditionRequiredException();
            expect($exception->getStatusCode())->toBe(428)
                ->and($exception->getReasonPhrase())->toBe('Precondition Required');
        });

        it('TooManyRequestsException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\TooManyRequestsException();
            expect($exception->getStatusCode())->toBe(429)
                ->and($exception->getReasonPhrase())->toBe('Too Many Requests');
        });

        it('UnavailableForLegalReasonsException has correct status code', function () {
            $exception = new \Denosys\Routing\Exceptions\UnavailableForLegalReasonsException();
            expect($exception->getStatusCode())->toBe(451)
                ->and($exception->getReasonPhrase())->toBe('Unavailable For Legal Reasons');
        });

        it('exceptions can chain previous exceptions', function () {
            $previous = new \Exception('Previous error');
            $exception = new \Denosys\Routing\Exceptions\BadRequestException('Current error', 0, $previous);
            expect($exception->getPrevious())->toBe($previous);
        });

        it('HttpStatus enum provides reason phrases', function () {
            expect(\Denosys\Routing\Exceptions\HttpStatus::NOT_FOUND->getReasonPhrase())->toBe('Not Found')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::BAD_REQUEST->getReasonPhrase())->toBe('Bad Request')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::UNAUTHORIZED->getReasonPhrase())->toBe('Unauthorized')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::FORBIDDEN->getReasonPhrase())->toBe('Forbidden')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::METHOD_NOT_ALLOWED->getReasonPhrase())->toBe('Method Not Allowed')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::NOT_ACCEPTABLE->getReasonPhrase())->toBe('Not Acceptable')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::CONFLICT->getReasonPhrase())->toBe('Conflict')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::GONE->getReasonPhrase())->toBe('Gone')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::LENGTH_REQUIRED->getReasonPhrase())->toBe('Length Required')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::PRECONDITION_FAILED->getReasonPhrase())->toBe('Precondition Failed')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::UNSUPPORTED_MEDIA_TYPE->getReasonPhrase())->toBe('Unsupported Media Type')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::EXPECTATION_FAILED->getReasonPhrase())->toBe('Expectation Failed')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::IM_A_TEAPOT->getReasonPhrase())->toBe('I\'m a teapot')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::UNPROCESSABLE_CONTENT->getReasonPhrase())->toBe('Unprocessable Content')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::PRECONDITION_REQUIRED->getReasonPhrase())->toBe('Precondition Required')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::TOO_MANY_REQUESTS->getReasonPhrase())->toBe('Too Many Requests')
                ->and(\Denosys\Routing\Exceptions\HttpStatus::UNAVAILABLE_FOR_LEGAL_REASONS->getReasonPhrase())->toBe('Unavailable For Legal Reasons');
        });
    });

    describe('DefaultInvocationStrategy', function () {

        it('supports FromRoute attribute for parameter binding', function () {
            $this->routeCollection->add('GET', '/users/{userId}', function(
                #[\Denosys\Routing\Attributes\FromRoute('userId')] string $id
            ) {
                return "User ID: $id";
            });

            $request = new ServerRequest([], [], '/users/123', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('User ID: 123');
        });

        it('uses parameter defaults when route parameters missing', function () {
            $this->routeCollection->add('GET', '/test', function($missing = 'default') {
                return $missing;
            });

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('default');
        });

        it('allows nullable parameters when not provided', function () {
            $this->routeCollection->add('GET', '/test/{id}', function($id, ?string $optional = null) {
                return $optional ?? "id-$id-null";
            });

            $request = new ServerRequest([], [], '/test/123', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('id-123-null');
        });

        it('throws InvalidHandlerException for unresolvable parameters', function () {
            $this->routeCollection->add('GET', '/test/{id}', function($id, string $required) {
                return $required;
            });

            $request = new ServerRequest([], [], '/test/123', 'GET');

            expect(fn() => $this->dispatcher->dispatch($request))
                ->toThrow(\Denosys\Routing\Exceptions\InvalidHandlerException::class);
        });

        it('caches parameter resolvers for performance', function () {
            $handler = function($id) { return "id: $id"; };
            $this->routeCollection->add('GET', '/test/{id}', $handler);

            // First call builds cache
            $request1 = new ServerRequest([], [], '/test/123', 'GET');
            $response1 = $this->dispatcher->dispatch($request1);
            expect((string) $response1->getBody())->toBe('id: 123');

            // Second call uses cached resolvers
            $request2 = new ServerRequest([], [], '/test/456', 'GET');
            $response2 = $this->dispatcher->dispatch($request2);
            expect((string) $response2->getBody())->toBe('id: 456');
        });

        it('converts JsonSerializable to JSON response', function () {
            $this->routeCollection->add('GET', '/test', function() {
                return new class implements JsonSerializable {
                    public function jsonSerialize(): array {
                        return ['status' => 'ok'];
                    }
                };
            });

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('{"status":"ok"}')
                ->and($response->getHeaderLine('Content-Type'))->toContain('application/json');
        });

        it('converts objects with toArray method to JSON response', function () {
            $this->routeCollection->add('GET', '/test', function() {
                return new class {
                    public function toArray(): array {
                        return ['data' => 'value'];
                    }
                };
            });

            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->dispatcher->dispatch($request);

            expect((string) $response->getBody())->toBe('{"data":"value"}')
                ->and($response->getHeaderLine('Content-Type'))->toContain('application/json');
        });
    });
});
