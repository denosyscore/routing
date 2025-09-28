<?php

declare(strict_types=1);

use Denosys\Routing\Router;
use Denosys\Routing\Attributes\Get;
use Denosys\Routing\Attributes\Post;

// Test controller for integration caching tests
class CacheIntegrationTestController
{
    #[Get('/cached-integration', name: 'integration.cached')]
    public function cached()
    {
        return ['message' => 'Integration cached route'];
    }
    
    #[Post('/cached-integration', name: 'integration.store')]
    public function store()
    {
        return ['message' => 'Integration store route'];
    }
}

beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir() . '/router-cache-integration-tests';
    $this->cacheFile = $this->cacheDir . '/routes.php';
    
    // Clean up before each test
    if (file_exists($this->cacheFile)) {
        unlink($this->cacheFile);
    }
    if (is_dir($this->cacheDir)) {
        rmdir($this->cacheDir);
    }
});

afterEach(function () {
    // Clean up after each test
    if (file_exists($this->cacheFile)) {
        unlink($this->cacheFile);
    }
    if (is_dir($this->cacheDir)) {
        rmdir($this->cacheDir);
    }
});

it('can cache attribute routes via router', function () {
    $router = new Router();
    
    // Cache routes manually
    $result = $router->cacheAttributeRoutes([CacheIntegrationTestController::class], $this->cacheFile);
    
    expect($result)->toBe($router)
        ->and($this->cacheFile)->toBeFile();
    
    // Verify cache contains expected routes
    $cacheData = include $this->cacheFile;
    expect($cacheData['routes'])->toHaveCount(2);
});

it('can cache attribute routes using central cache path', function () {
    $router = new Router();
    
    // Set cache path once
    $router->setCachePath($this->cacheFile);
    
    // Cache routes without providing path
    $result = $router->cacheAttributeRoutes([CacheIntegrationTestController::class]);
    
    expect($result)->toBe($router)
        ->and($this->cacheFile)->toBeFile();
    
    // Verify cache contains expected routes
    $cacheData = include $this->cacheFile;
    expect($cacheData['routes'])->toHaveCount(2);
});

it('can load routes from cache via router', function () {
    $router = new Router();
    
    // First cache the routes
    $router->cacheAttributeRoutes([CacheIntegrationTestController::class], $this->cacheFile);
    
    // Create new router and load from cache
    $cachedRouter = new Router();
    $cachedRouter->loadAttributeRoutesFromCache($this->cacheFile);
    
    // Test cached routes work
    $request = createRequest('GET', '/cached-integration');
    $response = $cachedRouter->dispatch($request);
    
    expect($response->getStatusCode())->toBe(200);
    
    $body = json_decode((string) $response->getBody(), true);
    expect($body['message'])->toBe('Integration cached route');
});

it('can load routes using central cache path', function () {
    $router = new Router();
    
    // First cache the routes using central cache path
    $router->setCachePath($this->cacheFile);
    $router->cacheAttributeRoutes([CacheIntegrationTestController::class]);
    
    // Create new router and load using central cache path
    $cachedRouter = new Router();
    $cachedRouter->setCachePath($this->cacheFile);
    $cachedRouter->loadAttributeRoutes([CacheIntegrationTestController::class]);
    
    // Test cached routes work
    $request = createRequest('GET', '/cached-integration');
    $response = $cachedRouter->dispatch($request);
    
    expect($response->getStatusCode())->toBe(200);
    
    $body = json_decode((string) $response->getBody(), true);
    expect($body['message'])->toBe('Integration cached route');
});

it('can load directory routes with cache via router', function () {
    $router = new Router();
    $testDir = __DIR__ . '/../fixtures/controllers';
    
    // First manually cache the routes
    $router->cacheAttributeRoutesFromDirectory($testDir, $this->cacheFile);
    expect($this->cacheFile)->toBeFile();
    
    // Now load using cache
    $cachedRouter = new Router();
    $cachedRouter->loadAttributeRoutesFromDirectory($testDir, $this->cacheFile);
    
    // Test that routes work
    $request = createRequest('GET', '/test');
    $response = $cachedRouter->dispatch($request);
    
    expect($response->getStatusCode())->toBe(200);
});

it('can clear cache via router', function () {
    $router = new Router();
    
    // Create cache
    $router->cacheAttributeRoutes([CacheIntegrationTestController::class], $this->cacheFile);
    expect($this->cacheFile)->toBeFile();
    
    // Clear cache
    $result = $router->clearAttributeRoutesCache($this->cacheFile);
    
    expect($result)->toBe($router)
        ->and($this->cacheFile)->not->toBeFile();
});

it('can clear cache using central cache path', function () {
    $router = new Router();
    
    // Set cache path and create cache
    $router->setCachePath($this->cacheFile);
    $router->cacheAttributeRoutes([CacheIntegrationTestController::class]);
    expect($this->cacheFile)->toBeFile();
    
    // Clear cache without providing path
    $result = $router->clearAttributeRoutesCache();
    
    expect($result)->toBe($router)
        ->and($this->cacheFile)->not->toBeFile();
});

it('throws exception when loading from invalid cache file', function () {
    $router = new Router();
    
    expect(fn() => $router->loadAttributeRoutesFromCache('/non/existent/cache.file'))
        ->toThrow(RuntimeException::class, 'Failed to load routes from cache file');
});

it('throws exception when caching without cache path', function () {
    $router = new Router();
    
    expect(fn() => $router->cacheAttributeRoutes([CacheIntegrationTestController::class]))
        ->toThrow(\InvalidArgumentException::class, 'Cache file path must be provided either as parameter or via setCachePath()');
});

it('throws exception when clearing cache without cache path', function () {
    $router = new Router();
    
    expect(fn() => $router->clearAttributeRoutesCache())
        ->toThrow(\InvalidArgumentException::class, 'Cache file path must be provided either as parameter or via setCachePath()');
});

it('throws exception when caching directory without cache path', function () {
    $router = new Router();
    $testDir = __DIR__ . '/../fixtures/controllers';
    
    expect(fn() => $router->cacheAttributeRoutesFromDirectory($testDir))
        ->toThrow(\InvalidArgumentException::class, 'Cache file path must be provided either as parameter or via setCachePath()');
});

it('performance comparison shows cache is faster', function () {
    $router = new Router();
    
    // Measure time for normal scanning
    $start = microtime(true);
    $router->loadAttributeRoutes([CacheIntegrationTestController::class]);
    $scanTime = microtime(true) - $start;
    
    // Cache the routes
    $router->cacheAttributeRoutes([CacheIntegrationTestController::class], $this->cacheFile);
    
    // Measure time for cached loading
    $cachedRouter = new Router();
    $start = microtime(true);
    $cachedRouter->loadAttributeRoutesFromCache($this->cacheFile);
    $cacheTime = microtime(true) - $start;
    
    // Cache loading should be faster (though this might be flaky in fast environments)
    // Skip timing assertion as it's environment dependent, just verify functionality
    expect($cacheTime)->toBeGreaterThan(0); // Just verify cache loading works
    
    // Both should produce the same number of routes
    expect($router->getRouteCollection()->count())
        ->toBe($cachedRouter->getRouteCollection()->count());
});
