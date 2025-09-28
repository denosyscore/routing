<?php

use Denosys\Routing\Router;
use Denosys\Routing\Exceptions\NotFoundException;
use Laminas\Diactoros\ServerRequest;
use Psr\Container\ContainerInterface;

// Mock container for testing DI
class MockContainer implements ContainerInterface
{
    private array $services = [];
    
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new \RuntimeException("Service $id not found");
        }
        return $this->services[$id];
    }
    
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
    
    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }
}

// Test controllers for integration testing
class UserController
{
    public function index(): array
    {
        return ['users' => ['John', 'Jane']];
    }
    
    public function show(string $id): array
    {
        return ['user' => ['id' => $id, 'name' => "User $id"]];
    }
    
    public function store(): array
    {
        return ['message' => 'User created', 'id' => 123];
    }
}

class PostController
{
    public function __construct(private UserController $userController) {}
    
    public function index(): array
    {
        return ['posts' => ['Post 1', 'Post 2']];
    }
    
    public function show(string $id): array
    {
        return ['post' => ['id' => $id, 'title' => "Post $id"]];
    }
}

describe('Integration Tests', function () {
    
    beforeEach(function () {
        $this->container = new MockContainer();
        $this->container->set(UserController::class, new UserController());
        $this->container->set(PostController::class, new PostController($this->container->get(UserController::class)));
        
        $this->router = new Router($this->container);
    });

    describe('Real-World API Scenarios', function () {
        
        it('can build a complete REST API', function () {
            // Build a complete REST API
            $this->router->group('/api/v1', function($api) {
                // Public routes
                $api->get('/status', fn() => ['status' => 'ok', 'version' => '1.0']);
                
                // CORS-enabled routes
                $api->group('/public', function($public) {
                    $public->get('/posts', [PostController::class, 'index']);
                    $public->get('/posts/{id}', [PostController::class, 'show']);
                });
                
                // Protected routes requiring authentication
                $api->group('/users', function($users) {
                    $users->get('/', [UserController::class, 'index']);
                    $users->get('/{id}', [UserController::class, 'show']);
                    $users->post('/', [UserController::class, 'store']);
                });
            });
            
            // Test public status endpoint
            $request = new ServerRequest([], [], '/api/v1/status', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            
            expect($response->getStatusCode())->toBe(200);
            expect($data['status'])->toBe('ok');
            expect($data['version'])->toBe('1.0');
        });

        it('handles complex nested API with versioning', function () {
            // API with versioning and feature flags
            $isV2Enabled = true;
            
            $this->router->group('/api', function($api) use ($isV2Enabled) {
                // V1 API
                $api->group('/v1', function($v1) {
                    $v1->get('/users', fn() => ['version' => 'v1', 'users' => ['legacy data']]);
                });
                
                // V2 API (conditionally enabled)
                $api->when($isV2Enabled, function($api) {
                    $api->group('/v2', function($v2) {
                        $v2->group('/users', function($users) {
                               $users->get('/', fn() => ['version' => 'v2', 'users' => ['enhanced data']]);
                               $users->get('/{id}', fn($id) => ['version' => 'v2', 'user' => $id]);
                           });
                    });
                });
                
                // Latest API (always points to newest version)
                $api->group('/latest', function($latest) {
                    $latest->get('/users', fn() => ['version' => 'latest', 'users' => ['current data']]);
                });
            });
            
            // Test V1
            $request = new ServerRequest([], [], '/api/v1/users', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            expect($data['version'])->toBe('v1');
            
            // Test V2
            $request = new ServerRequest([], [], '/api/v2/users', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            expect($data['version'])->toBe('v2');
            
            // Test V2 with parameter
            $request = new ServerRequest([], [], '/api/v2/users/123', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            expect($data['user'])->toBe('123');
            
            // Test latest
            $request = new ServerRequest([], [], '/api/latest/users', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            expect($data['version'])->toBe('latest');
        });

        it('handles file serving with proper constraints', function () {
            $this->router->group('/files', function($files) {
                // Images
                $files->get('/images/{filename}', fn($filename) => ['type' => 'image', 'file' => $filename])
                      ->where('filename', '[^/]+\.(jpg|jpeg|png|gif|webp)');
                
                // Documents
                $files->get('/docs/{filename}', fn($filename) => ['type' => 'document', 'file' => $filename])
                      ->where('filename', '[^/]+\.(pdf|doc|docx|txt)');
                
                // Downloads with ID
                $files->get('/download/{id}', fn($id) => ['type' => 'download', 'id' => $id])
                      ->whereNumber('id');
            });
            
            // Test image file
            $request = new ServerRequest([], [], '/files/images/photo.jpg', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            expect($data['type'])->toBe('image');
            expect($data['file'])->toBe('photo.jpg');
            
            // Test document file
            $request = new ServerRequest([], [], '/files/docs/manual.pdf', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            expect($data['type'])->toBe('document');
            expect($data['file'])->toBe('manual.pdf');
            
            // Test download with ID
            $request = new ServerRequest([], [], '/files/download/12345', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            expect($data['type'])->toBe('download');
            expect($data['id'])->toBe('12345');
            
            $request = new ServerRequest([], [], '/files/images/photo.exe', 'GET');
            expect(fn() => $this->router->dispatch($request))
                ->toThrow(NotFoundException::class);
            
            $request = new ServerRequest([], [], '/files/download/abc', 'GET');
            expect(fn() => $this->router->dispatch($request))
                ->toThrow(NotFoundException::class);
        });
    });

    describe('Dependency Injection Integration', function () {
        
        it('resolves controller dependencies from container', function () {
            $this->router->get('/posts', [PostController::class, 'index']);
            $this->router->get('/posts/{id}', [PostController::class, 'show']);
            
            $request = new ServerRequest([], [], '/posts', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            
            expect($data['posts'])->toBe(['Post 1', 'Post 2']);
            
            $request = new ServerRequest([], [], '/posts/123', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            
            expect($data['post']['id'])->toBe('123');
            expect($data['post']['title'])->toBe('Post 123');
        });

        it('handles missing dependencies gracefully', function () {
            // Exception is thrown during route registration when handler is resolved
            expect(fn() => $this->router->get('/missing', ['\NonExistentController', 'index']))
                ->toThrow(Denosys\Routing\Exceptions\HandlerNotFoundException::class);
        });
    });

    describe('Performance and Scalability', function () {
        
        it('handles high route count efficiently', function () {
            $startTime = microtime(true);
            
            // Register 1000 routes across different groups
            for ($i = 0; $i < 100; $i++) {
                $this->router->group("/group$i", function($group) use ($i) {
                    for ($j = 0; $j < 10; $j++) {
                        $group->get("/route$j", fn() => "group$i-route$j");
                    }
                });
            }
            
            $registrationTime = microtime(true) - $startTime;
            
            // Test routing to different routes
            $testRoutes = [
                '/group0/route0',
                '/group50/route5',
                '/group99/route9'
            ];
            
            $dispatchStartTime = microtime(true);
            
            foreach ($testRoutes as $path) {
                $request = new ServerRequest([], [], $path, 'GET');
                $response = $this->router->dispatch($request);
                expect($response->getStatusCode())->toBe(200);
            }
            
            $dispatchTime = microtime(true) - $dispatchStartTime;
            
            // Performance assertions (these are rough benchmarks)
            expect($registrationTime)->toBeLessThan(1.0); // Should register 1000 routes in under 1 second
            expect($dispatchTime)->toBeLessThan(0.1);     // Should dispatch 3 routes in under 100ms
        });
    });

    describe('Real-World Error Scenarios', function () {

        it('handles partial route matches correctly', function () {
            $this->router->get('/users/{id}/posts/{postId}', fn($id, $postId) => "user $id post $postId");
            $this->router->get('/users/{id}', fn($id) => "user $id");
            
            // Test full match
            $request = new ServerRequest([], [], '/users/123/posts/456', 'GET');
            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('user 123 post 456');
            
            // Test partial match (should match the shorter route)
            $request = new ServerRequest([], [], '/users/123', 'GET');
            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('user 123');
            
            // Test no match
            $request = new ServerRequest([], [], '/users', 'GET');
            expect(fn() => $this->router->dispatch($request))
                ->toThrow(Denosys\Routing\Exceptions\NotFoundException::class);
        });
    });

    describe('Full Application Simulation', function () {
        
        it('simulates a complete web application with admin panel', function () {
            // Public website
            $this->router->get('/', fn() => ['page' => 'home']);
            $this->router->get('/about', fn() => ['page' => 'about']);
            $this->router->get('/contact', fn() => ['page' => 'contact']);
            
            // Blog
            $this->router->group('/blog', function($blog) {
                $blog->get('/', fn() => ['posts' => ['Blog post 1', 'Blog post 2']]);
                $blog->get('/{slug}', fn($slug) => ['post' => $slug]);
            });
            
            // API
            $this->router->group('/api', function($api) {
                $api->get('/posts', fn() => ['api_posts' => ['API Post 1', 'API Post 2']]);
                
                $api->group('/admin', function($admin) {
                    $admin->get('/users', [UserController::class, 'index']);
                    $admin->post('/users', [UserController::class, 'store']);
                });
            });
            
            // Admin panel
            $this->router->group('/admin', function($admin) {
                $admin->get('/dashboard', fn($request) => [
                    'page' => 'dashboard',
                    'user' => $request->getAttribute('user_id')
                ]);
            });
            
            // Test public pages
            $request = new ServerRequest([], [], '/', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            expect($data['page'])->toBe('home');
            
            // Test blog
            $request = new ServerRequest([], [], '/blog/my-first-post', 'GET');
            $response = $this->router->dispatch($request);
            $data = json_decode((string) $response->getBody(), true);
            expect($data['post'])->toBe('my-first-post');
        });
    });
});
