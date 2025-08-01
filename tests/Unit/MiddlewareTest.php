<?php

use Denosys\Routing\Router;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

// Test middleware classes
class TestMiddleware implements MiddlewareInterface
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

class ConditionalMiddleware implements MiddlewareInterface
{
    public static array $executed = [];
    
    public function __construct(private string $name, private bool $shouldExecute = true) {}
    
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if ($this->shouldExecute) {
            self::$executed[] = $this->name;
        }
        return $handler->handle($request);
    }
}

describe('Middleware System', function () {
    
    beforeEach(function () {
        $this->router = new Router();
        TestMiddleware::$executed = [];
        ConditionalMiddleware::$executed = [];
    });

    describe('Router Middleware', function () {
        
        it('applies middleware using middleware()->group() syntax', function () {
            $this->router->middleware(new TestMiddleware('Auth'))
                         ->group('/api', function($group) {
                             $group->get('/users', fn() => 'users');
                         });
            
            $request = new ServerRequest([], [], '/api/users', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe(['Auth']);
        });

        it('applies middleware using middleware()->get() syntax', function () {
            $this->router->middleware(new TestMiddleware('Auth'))
                         ->get('/protected', fn() => 'protected');
            
            $request = new ServerRequest([], [], '/protected', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe(['Auth']);
        });

        it('does not leak middleware to subsequent routes', function () {
            // Route before middleware
            $this->router->get('/public', fn() => 'public');
            
            // Route with middleware
            $this->router->middleware(new TestMiddleware('Auth'))
                         ->get('/protected', fn() => 'protected');
            
            // Route after middleware
            $this->router->get('/public2', fn() => 'public2');
            
            // Test public route (no middleware)
            $request = new ServerRequest([], [], '/public', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
            
            TestMiddleware::$executed = [];
            
            // Test protected route (has middleware)
            $request = new ServerRequest([], [], '/protected', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Auth']);
            
            TestMiddleware::$executed = [];
            
            // Test second public route (no middleware)
            $request = new ServerRequest([], [], '/public2', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
        });

        it('handles multiple middleware on single route', function () {
            $this->router->middleware([
                             new TestMiddleware('Auth'),
                             new TestMiddleware('CORS')
                         ])
                         ->get('/multi', fn() => 'multi');
            
            $request = new ServerRequest([], [], '/multi', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe(['Auth', 'CORS']);
        });
    });

    describe('Group Middleware', function () {
        
        it('applies middleware using group()->middleware() syntax', function () {
            $this->router->group('/admin', function($group) {
                $group->get('/users', fn() => 'admin users');
                $group->get('/posts', fn() => 'admin posts');
            })->middleware(new TestMiddleware('AdminAuth'));
            
            // Test first route in group
            $request = new ServerRequest([], [], '/admin/users', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['AdminAuth']);
            
            TestMiddleware::$executed = [];
            
            // Test second route in group
            $request = new ServerRequest([], [], '/admin/posts', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['AdminAuth']);
        });

        it('does not leak group middleware to other routes', function () {
            // Public route
            $this->router->get('/public', fn() => 'public');
            
            // Admin group with middleware
            $this->router->group('/admin', function($group) {
                $group->get('/dashboard', fn() => 'dashboard');
            })->middleware(new TestMiddleware('AdminAuth'));
            
            // Another public route
            $this->router->get('/public2', fn() => 'public2');
            
            // Test public routes have no middleware
            $request = new ServerRequest([], [], '/public', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
            
            $request = new ServerRequest([], [], '/public2', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
            
            // Test admin route has middleware
            $request = new ServerRequest([], [], '/admin/dashboard', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['AdminAuth']);
        });

        it('applies middleware to nested groups correctly', function () {
            $this->router->middleware(new TestMiddleware('GlobalAuth'))
                         ->group('/api', function($api) {
                             $api->get('/public', fn() => 'public');
                             
                             $api->middleware(new TestMiddleware('AdminAuth'))
                                ->group('/admin', function($admin) {
                                    $admin->get('/users', fn() => 'admin users');
                                });
                         });
            
            // Test main group route
            $request = new ServerRequest([], [], '/api/public', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['GlobalAuth']);
            
            TestMiddleware::$executed = [];
            
            // Test nested group route
            $request = new ServerRequest([], [], '/api/admin/users', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['GlobalAuth', 'AdminAuth']);
        });

        it('handles $group->middleware()->get() syntax correctly', function () {
            $this->router->group('/api', function($group) {
                $group->get('/public', fn() => 'public');
                
                $group->middleware(new TestMiddleware('Auth'))
                      ->get('/protected', fn() => 'protected');
                
                $group->get('/public2', fn() => 'public2');
            });
            
            // Test public routes have no middleware
            $request = new ServerRequest([], [], '/api/public', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
            
            $request = new ServerRequest([], [], '/api/public2', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
            
            // Test protected route has middleware
            $request = new ServerRequest([], [], '/api/protected', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Auth']);
        });
    });

    describe('Route-Level Middleware', function () {
        
        it('applies middleware using get()->middleware() syntax', function () {
            $this->router->get('/protected', fn() => 'protected')
                         ->middleware(new TestMiddleware('Auth'));
            
            $request = new ServerRequest([], [], '/protected', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe(['Auth']);
        });

        it('does not leak route middleware to other routes', function () {
            $this->router->get('/public', fn() => 'public');
            
            $this->router->get('/protected', fn() => 'protected')
                         ->middleware(new TestMiddleware('Auth'));
            
            $this->router->get('/public2', fn() => 'public2');
            
            // Test public routes have no middleware
            $request = new ServerRequest([], [], '/public', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
            
            $request = new ServerRequest([], [], '/public2', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
            
            // Test protected route has middleware
            $request = new ServerRequest([], [], '/protected', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Auth']);
        });

        it('can chain multiple middleware on single route', function () {
            $this->router->get('/protected', fn() => 'protected')
                         ->middleware(new TestMiddleware('Auth'))
                         ->middleware(new TestMiddleware('CORS'));
            
            $request = new ServerRequest([], [], '/protected', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe(['Auth', 'CORS']);
        });
    });

    describe('Middleware Priority', function () {
        
        it('executes middleware in priority order', function () {
            $this->router->get('/test', fn() => 'test')
                         ->middleware(new TestMiddleware('Low'), 0)
                         ->middleware(new TestMiddleware('High'), 100)
                         ->middleware(new TestMiddleware('Medium'), 50);
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $this->router->dispatch($request);
            
            // Higher priority executes first
            expect(TestMiddleware::$executed)->toBe(['High', 'Medium', 'Low']);
        });

        it('prependMiddleware has high priority', function () {
            $this->router->get('/test', fn() => 'test')
                         ->middleware(new TestMiddleware('Normal'))
                         ->prependMiddleware(new TestMiddleware('Prepended'));
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe(['Prepended', 'Normal']);
        });
    });

    describe('Conditional Middleware', function () {
        
        it('applies middlewareWhen when condition is true', function () {
            $this->router->get('/test', fn() => 'test')
                         ->middlewareWhen(true, new TestMiddleware('Conditional'));
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe(['Conditional']);
        });

        it('skips middlewareWhen when condition is false', function () {
            $this->router->get('/test', fn() => 'test')
                         ->middlewareWhen(false, new TestMiddleware('Conditional'));
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe([]);
        });

        it('applies middlewareUnless when condition is false', function () {
            $this->router->get('/test', fn() => 'test')
                         ->middlewareUnless(false, new TestMiddleware('Conditional'));
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe(['Conditional']);
        });

        it('skips middlewareUnless when condition is true', function () {
            $this->router->get('/test', fn() => 'test')
                         ->middlewareUnless(true, new TestMiddleware('Conditional'));
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe([]);
        });

        it('supports callable conditions', function () {
            $condition = fn() => true;
            
            $this->router->get('/test', fn() => 'test')
                         ->middlewareWhen($condition, new TestMiddleware('Conditional'));
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $this->router->dispatch($request);
            
            expect(TestMiddleware::$executed)->toBe(['Conditional']);
        });
    });

    describe('Complex Middleware Scenarios', function () {
        
        it('handles complex nested middleware correctly', function () {
            $this->router->middleware(new TestMiddleware('Global'))
                         ->group('/api', function($api) {
                             $api->get('/public', fn() => 'public');
                             
                             $api->group('/v1', function($v1) {
                                 $v1->get('/users', fn() => 'v1 users');
                                 
                                 $v1->middleware(new TestMiddleware('Admin'))
                                    ->get('/admin', fn() => 'v1 admin');
                             })->middleware(new TestMiddleware('V1Auth'));
                         });
            
            // Test public route in main group
            $request = new ServerRequest([], [], '/api/public', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Global']);
            
            TestMiddleware::$executed = [];
            
            // Test v1 users route
            $request = new ServerRequest([], [], '/api/v1/users', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Global', 'V1Auth']);
            
            TestMiddleware::$executed = [];
            
            // Test v1 admin route
            $request = new ServerRequest([], [], '/api/v1/admin', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Global', 'Admin', 'V1Auth']);
        });

        it('ensures no middleware duplication', function () {
            $this->router->middleware(new TestMiddleware('Auth'))
                         ->group('/api', function($group) {
                             $group->get('/users', fn() => 'users');
                         });
            
            $request = new ServerRequest([], [], '/api/users', 'GET');
            $this->router->dispatch($request);
            
            // Middleware should execute exactly once
            expect(TestMiddleware::$executed)->toBe(['Auth']);
            expect(count(TestMiddleware::$executed))->toBe(1);
        });

        it('handles all syntax combinations correctly', function () {
            // Clean state
            $this->router = new Router();
            TestMiddleware::$executed = [];
            
            // Test all different syntax combinations
            $this->router->get('/route1', fn() => '1'); // No middleware
            
            $this->router->middleware(new TestMiddleware('R2'))
                         ->get('/route2', fn() => '2'); // middleware()->get()
            
            $this->router->get('/route3', fn() => '3')
                         ->middleware(new TestMiddleware('R3')); // get()->middleware()
            
            $this->router->middleware(new TestMiddleware('G1'))
                         ->group('/group1', function($g) {
                             $g->get('/test', fn() => 'g1');
                         }); // middleware()->group()
            
            $this->router->group('/group2', function($g) {
                             $g->get('/test', fn() => 'g2');
                         })
                         ->middleware(new TestMiddleware('G2')); // group()->middleware()
            
            // Test each route
            $testCases = [
                ['/route1', []],
                ['/route2', ['R2']],
                ['/route3', ['R3']],
                ['/group1/test', ['G1']],
                ['/group2/test', ['G2']],
            ];
            
            foreach ($testCases as [$path, $expectedMiddleware]) {
                TestMiddleware::$executed = [];
                $request = new ServerRequest([], [], $path, 'GET');
                $this->router->dispatch($request);
                expect(TestMiddleware::$executed)->toBe($expectedMiddleware, "Failed for path: $path");
            }
        });
    });

    describe('Regression Tests - Fixed Bugs', function () {
        
        it('prevents middleware from executing 3 times in middleware()->group() syntax', function () {
            // This was the original failing case that executed middleware 3 times
            $this->router->middleware(new TestMiddleware('RouterAuth'))
                         ->group('/api', function($group) {
                             $group->get('/users', fn() => ['users' => 'list']);
                         });
            
            $request = new ServerRequest([], [], '/api/users', 'GET');
            $this->router->dispatch($request);
            
            // Should execute exactly once, not 3 times
            expect(TestMiddleware::$executed)->toBe(['RouterAuth']);
            expect(count(TestMiddleware::$executed))->toBe(1);
        });

        it('prevents middleware duplication in complex nested scenarios', function () {
            // This was another failing case with multiple middleware levels
            $this->router->middleware(new TestMiddleware('RouterLevel'))
                         ->group('/api', function($group) {
                             $group->middleware(new TestMiddleware('GroupLevel'))
                                   ->get('/posts/{id}', fn($id) => ['post' => $id])
                                   ->middleware(new TestMiddleware('RouteLevel'));
                         });
            
            $request = new ServerRequest([], [], '/api/posts/123', 'GET');
            $this->router->dispatch($request);
            
            // Each middleware should execute exactly once
            expect(TestMiddleware::$executed)->toBe(['RouterLevel', 'GroupLevel', 'RouteLevel']);
            expect(count(TestMiddleware::$executed))->toBe(3);
        });

        it('applies middleware when using group()->middleware() syntax', function () {
            // This syntax was completely broken before the fix
            $this->router->group('/admin', function($group) {
                $group->get('/users', fn() => 'admin users');
                $group->get('/posts', fn() => 'admin posts');
            })->middleware(new TestMiddleware('AdminAuth'));
            
            // Test first route in group
            $request = new ServerRequest([], [], '/admin/users', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['AdminAuth']);
            
            TestMiddleware::$executed = [];
            
            // Test second route in group  
            $request = new ServerRequest([], [], '/admin/posts', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['AdminAuth']);
        });

        it('prevents middleware from applying to routes defined before middleware call', function () {
            // This was a major bug - middleware was being applied to ALL routes
            
            // Route defined BEFORE middleware call - should NOT have middleware
            $this->router->get('/before', fn() => 'before middleware call');
            
            // Add middleware, then create route
            $this->router->middleware(new TestMiddleware('Auth'))
                         ->get('/after', fn() => 'after middleware call');
            
            // Test route before middleware (should have NO middleware)
            $request = new ServerRequest([], [], '/before', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
            
            TestMiddleware::$executed = [];
            
            // Test route after middleware (should have middleware)
            $request = new ServerRequest([], [], '/after', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Auth']);
        });

        it('prevents middleware from leaking to subsequent groups', function () {
            // Middleware should only apply to the immediate next operation
            
            $this->router->middleware(new TestMiddleware('Group1Auth'))
                         ->group('/group1', function($group) {
                             $group->get('/test', fn() => 'group1');
                         });
            
            // This group should NOT inherit Group1Auth
            $this->router->group('/group2', function($group) {
                $group->get('/test', fn() => 'group2');
            });
            
            // Test group1 (should have middleware)
            $request = new ServerRequest([], [], '/group1/test', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Group1Auth']);
            
            TestMiddleware::$executed = [];
            
            // Test group2 (should NOT have middleware)
            $request = new ServerRequest([], [], '/group2/test', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe([]);
        });

        it('handles the original complex failing scenario correctly', function () {
            // This was the exact scenario from our final test that was failing
            
            $this->router = new Router();
            TestMiddleware::$executed = [];
            
            $this->router->middleware(new TestMiddleware('Auth'))
                         ->group('/api/v1', function($group) {
                             $group->get('/users', fn() => ['api' => 'users']);
                             
                             // Nested group with additional middleware
                             $group->middleware(new TestMiddleware('RateLimit'))
                                   ->group('/admin', function($adminGroup) {
                                       $adminGroup->get('/stats', fn() => ['admin' => 'stats']);
                                   });
                             
                             // Route with specific middleware
                             $group->middleware(new TestMiddleware('Validation'))
                                   ->get('/posts', fn() => ['api' => 'posts']);
                         });
            
            // Test main group route (should have Auth only)
            $request = new ServerRequest([], [], '/api/v1/users', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Auth']);
            
            TestMiddleware::$executed = [];
            
            // Test nested group route (should have Auth + RateLimit)
            $request = new ServerRequest([], [], '/api/v1/admin/stats', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Auth', 'RateLimit', 'Validation']);
            
            TestMiddleware::$executed = [];
            
            // Test route with specific middleware (should have Auth + Validation, NOT RateLimit)
            $request = new ServerRequest([], [], '/api/v1/posts', 'GET');
            $this->router->dispatch($request);
            expect(TestMiddleware::$executed)->toBe(['Auth']);
        });
    });

    describe('Middleware Edge Cases', function () {
        
        it('handles middleware that throws exceptions', function () {
            $exceptionMiddleware = new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                    throw new \RuntimeException('Middleware error');
                }
            };
            
            $this->router->get('/test', fn() => 'test')
                         ->middleware($exceptionMiddleware);
            
            $request = new ServerRequest([], [], '/test', 'GET');
            expect(fn() => $this->router->dispatch($request))
                ->toThrow(\RuntimeException::class, 'Middleware error');
        });

        it('handles middleware that modifies request and response', function () {
            $modifyingMiddleware = new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                    $request = $request->withAttribute('middleware_executed', true);
                    $response = $handler->handle($request);
                    return $response->withHeader('X-Middleware', 'executed');
                }
            };
            
            $this->router->get('/test', function($request) {
                $middlewareExecuted = $request->getAttribute('middleware_executed', false);
                return ['middleware_executed' => $middlewareExecuted];
            })->middleware($modifyingMiddleware);
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);
            
            expect($response->hasHeader('X-Middleware'))->toBe(true);
            expect($response->getHeaderLine('X-Middleware'))->toBe('executed');
            
            $data = json_decode((string) $response->getBody(), true);
            expect($data['middleware_executed'])->toBe(true);
        });

        it('handles empty middleware arrays', function () {
            $this->router->get('/test', fn() => 'test')
                         ->middleware([]);
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('test');
        });

        it('handles middleware with extreme priorities', function () {
            $this->router->get('/test', fn() => 'test')
                         ->middleware(new TestMiddleware('Max'), PHP_INT_MAX)
                         ->middleware(new TestMiddleware('Min'), PHP_INT_MIN)
                         ->middleware(new TestMiddleware('Zero'), 0);
            
            $request = new ServerRequest([], [], '/test', 'GET');
            $this->router->dispatch($request);
            
            // Should execute in priority order: Max, Zero, Min
            expect(TestMiddleware::$executed)->toBe(['Max', 'Zero', 'Min']);
        });
    });
});
