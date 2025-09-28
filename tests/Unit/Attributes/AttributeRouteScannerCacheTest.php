<?php

declare(strict_types=1);

use Denosys\Routing\Attributes\AttributeRouteScanner;
use Denosys\Routing\Attributes\Get;
use Denosys\Routing\Attributes\Post;

// Test controller for caching tests
class CacheTestController
{
    #[Get('/cached', name: 'cached.index')]
    public function index()
    {
        return ['message' => 'cached'];
    }
    
    #[Post('/cached', name: 'cached.store')]
    public function store()
    {
        return ['message' => 'stored'];
    }
}

beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir() . '/scanner-cache-tests';
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

it('can cache routes from scanner', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass(CacheTestController::class);
    
    expect($routes)->toHaveCount(2);
    
    // Cache the routes
    $result = $scanner->cacheRoutes($routes, $this->cacheFile);
    
    expect($result)->toBeTrue()
        ->and($this->cacheFile)->toBeFile();
});

it('can load cached routes from scanner', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass(CacheTestController::class);
    
    // Cache the routes
    $scanner->cacheRoutes($routes, $this->cacheFile);
    
    // Load from cache
    $loadedRoutes = $scanner->loadCachedRoutes($this->cacheFile);
    
    expect($loadedRoutes)->toBe($routes);
});

it('can manually cache directory routes', function () {
    $scanner = new AttributeRouteScanner();
    $testDir = __DIR__ . '/../../fixtures/controllers';
    
    // Scan directory manually
    $routes = $scanner->scanDirectory($testDir);
    expect($routes)->toBeArray();
    
    // Cache the routes manually
    $result = $scanner->cacheRoutes($routes, $this->cacheFile);
    expect($result)->toBeTrue()
        ->and($this->cacheFile)->toBeFile();
    
    // Load from cache
    $cachedRoutes = $scanner->loadCachedRoutes($this->cacheFile);
    expect($cachedRoutes)->toBe($routes);
});

it('demonstrates manual caching workflow', function () {
    $scanner = new AttributeRouteScanner();
    
    // Step 1: Scan routes (in development or CLI command)
    $routes = $scanner->scanClass(CacheTestController::class);
    expect($routes)->toHaveCount(2);
    
    // Step 2: Cache routes manually (like artisan route:cache)
    $scanner->cacheRoutes($routes, $this->cacheFile);
    expect($this->cacheFile)->toBeFile();
    
    // Step 3: In production, load from cache instead of scanning
    $cachedRoutes = $scanner->loadCachedRoutes($this->cacheFile);
    expect($cachedRoutes)->toBe($routes);
    
    // Step 4: Clear cache manually (like artisan route:clear)
    $scanner->clearCache($this->cacheFile);
    expect($this->cacheFile)->not->toBeFile();
});

it('can clear cache through scanner', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass(CacheTestController::class);
    
    // Create cache
    $scanner->cacheRoutes($routes, $this->cacheFile);
    expect($this->cacheFile)->toBeFile();
    
    // Clear cache
    $result = $scanner->clearCache($this->cacheFile);
    
    expect($result)->toBeTrue()
        ->and($this->cacheFile)->not->toBeFile();
});

it('can validate cache through scanner', function () {
    $scanner = new AttributeRouteScanner();
    $routes = $scanner->scanClass(CacheTestController::class);
    
    // Create cache
    $scanner->cacheRoutes($routes, $this->cacheFile);
    
    // Should be valid with no source files
    expect($scanner->isCacheValid($this->cacheFile))->toBeTrue();
    
    // Should be invalid for non-existent cache
    expect($scanner->isCacheValid('/non/existent/cache.file'))->toBeFalse();
});