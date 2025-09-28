<?php

declare(strict_types=1);

use Denosys\Routing\Attributes\RouteCache;

beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir() . '/route-cache-tests';
    $this->cacheFile = $this->cacheDir . '/routes.cache';
    
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

it('can cache routes to file', function () {
    $cache = new RouteCache();
    $routes = [
        [
            'methods' => ['GET', 'HEAD'],
            'path' => '/users',
            'name' => 'users.index',
            'action' => ['UserController', 'index'],
            'middleware' => [],
            'where' => []
        ]
    ];
    
    $result = $cache->cacheRoutes($routes, $this->cacheFile);
    
    expect($result)->toBeTrue()
        ->and($this->cacheFile)->toBeFile();
    
    // Verify cache file contains expected data
    $cacheData = include $this->cacheFile;
    expect($cacheData)->toBeArray()
        ->and($cacheData['routes'])->toBe($routes)
        ->and($cacheData['timestamp'])->toBeInt()
        ->and($cacheData['php_version'])->toBe(PHP_VERSION);
});

it('can load cached routes from file', function () {
    $cache = new RouteCache();
    $routes = [
        [
            'methods' => ['POST'],
            'path' => '/users',
            'name' => 'users.store',
            'action' => ['UserController', 'store'],
            'middleware' => ['auth'],
            'where' => []
        ]
    ];
    
    // Cache the routes first
    $cache->cacheRoutes($routes, $this->cacheFile);
    
    // Load from cache
    $loadedRoutes = $cache->loadCachedRoutes($this->cacheFile);
    
    expect($loadedRoutes)->toBe($routes);
});

it('returns null when loading from non-existent cache file', function () {
    $cache = new RouteCache();
    $loadedRoutes = $cache->loadCachedRoutes('/non/existent/cache.file');
    
    expect($loadedRoutes)->toBeNull();
});

it('returns null when loading from corrupted cache file', function () {
    $cache = new RouteCache();
    
    // Create a corrupted cache file
    if (!is_dir($this->cacheDir)) {
        mkdir($this->cacheDir, 0755, true);
    }
    file_put_contents($this->cacheFile, '<?php return "invalid data";');
    
    $loadedRoutes = $cache->loadCachedRoutes($this->cacheFile);
    
    expect($loadedRoutes)->toBeNull();
});

it('can validate cache against source files', function () {
    $cache = new RouteCache();
    $routes = [['methods' => ['GET'], 'path' => '/test']];
    
    // Create a temporary source file
    $sourceFile = $this->cacheDir . '/source.php';
    mkdir($this->cacheDir, 0755, true);
    file_put_contents($sourceFile, '<?php class TestController {}');
    
    // Cache routes
    $cache->cacheRoutes($routes, $this->cacheFile);
    
    // Cache should be valid immediately
    expect($cache->isCacheValid($this->cacheFile, [$sourceFile]))->toBeTrue();
    
    // Modify source file to be newer than cache
    sleep(1);
    touch($sourceFile);
    
    // Cache should now be invalid
    expect($cache->isCacheValid($this->cacheFile, [$sourceFile]))->toBeFalse();
    
    // Clean up
    unlink($sourceFile);
});

it('returns false for cache validation on non-existent cache file', function () {
    $cache = new RouteCache();
    
    expect($cache->isCacheValid('/non/existent/cache.file'))->toBeFalse();
});

it('can clear cache file', function () {
    $cache = new RouteCache();
    $routes = [['methods' => ['GET'], 'path' => '/test']];
    
    // Create cache
    $cache->cacheRoutes($routes, $this->cacheFile);
    expect($this->cacheFile)->toBeFile();
    
    // Clear cache
    $result = $cache->clearCache($this->cacheFile);
    
    expect($result)->toBeTrue()
        ->and($this->cacheFile)->not->toBeFile();
});

it('returns true when clearing non-existent cache file', function () {
    $cache = new RouteCache();
    
    $result = $cache->clearCache('/non/existent/cache.file');
    
    expect($result)->toBeTrue();
});

it('creates cache directory if it does not exist', function () {
    $cache = new RouteCache();
    $routes = [['methods' => ['GET'], 'path' => '/test']];
    
    // Ensure directory doesn't exist
    expect($this->cacheDir)->not->toBeDirectory();
    
    // Cache routes (should create directory)
    $cache->cacheRoutes($routes, $this->cacheFile);
    
    expect($this->cacheDir)->toBeDirectory()
        ->and($this->cacheFile)->toBeFile();
});

it('throws exception when cache directory cannot be created', function () {
    $cache = new RouteCache();
    $routes = [['methods' => ['GET'], 'path' => '/test']];
    
    // Use a path that would require root permissions (more realistic scenario)
    $invalidCacheFile = '/root/cache/routes.cache';
    
    expect(fn() => $cache->cacheRoutes($routes, $invalidCacheFile))
        ->toThrow(RuntimeException::class, 'Failed to create cache directory');
});