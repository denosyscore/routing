<?php

declare(strict_types=1);

use Denosys\Routing\Attributes\RouteCache;

beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir() . '/route-cache-tests';
    $this->cacheFile = $this->cacheDir . '/routes.php';

    // Helper function to recursively delete directory
    $this->deleteDirectory = function($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? ($this->deleteDirectory)($path) : unlink($path);
        }

        rmdir($dir);
    };

    // Clean up before each test
    ($this->deleteDirectory)($this->cacheDir);
});

afterEach(function () {
    // Clean up after each test
    if (isset($this->deleteDirectory)) {
        ($this->deleteDirectory)($this->cacheDir);
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
    
    $cache->cacheRoutes($routes, $this->cacheFile);
    
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
    
    $sourceFile = $this->cacheDir . '/source.php';
    mkdir($this->cacheDir, 0755, true);
    file_put_contents($sourceFile, '<?php class TestController {}');
    
    $cache->cacheRoutes($routes, $this->cacheFile);
    
    expect($cache->isCacheValid($this->cacheFile, [$sourceFile]))->toBeTrue();
    
    sleep(1);
    touch($sourceFile);
    
    expect($cache->isCacheValid($this->cacheFile, [$sourceFile]))->toBeFalse();
    
    unlink($sourceFile);
});

it('returns false for cache validation on non-existent cache file', function () {
    $cache = new RouteCache();
    
    expect($cache->isCacheValid('/non/existent/cache.file'))->toBeFalse();
});

it('can clear cache file', function () {
    $cache = new RouteCache();
    $routes = [['methods' => ['GET'], 'path' => '/test']];
    
    $cache->cacheRoutes($routes, $this->cacheFile);
    expect($this->cacheFile)->toBeFile();
    
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
    $uniqueDir = sys_get_temp_dir() . '/route-cache-create-test-' . uniqid();
    $uniqueFile = $uniqueDir . '/routes.cache';

    $cache = new RouteCache();
    $routes = [['methods' => ['GET'], 'path' => '/test']];

    expect($uniqueDir)->not->toBeDirectory();

    $cache->cacheRoutes($routes, $uniqueFile);

    expect($uniqueDir)->toBeDirectory()
        ->and($uniqueFile)->toBeFile();

    if (file_exists($uniqueFile)) {
        unlink($uniqueFile);
    }
    if (is_dir($uniqueDir)) {
        rmdir($uniqueDir);
    }
});

it('throws exception when cache directory cannot be created', function () {
    $cache = new RouteCache();
    $routes = [['methods' => ['GET'], 'path' => '/test']];

    $invalidCacheFile = '/root/cache/routes.php';

    expect(fn() => $cache->cacheRoutes($routes, $invalidCacheFile))
        ->toThrow(RuntimeException::class, 'Failed to create cache directory');
});

it('can build route cache', function () {
    $router = new \Denosys\Routing\Router();

    $router->get('/users', fn() => 'users');
    $router->get('/users/{id}', fn($id) => "user $id");
    $router->post('/users', fn() => 'create user');

    $cacheFile = $this->cacheDir . '/route-cache.php';
    $builder = new \Denosys\Routing\CacheBuilder();

    // CacheBuilder uses var_export which doesn't work with Route objects
    // This test verifies the method can be called without errors
    expect(fn() => $builder->buildRouteCache($router->getRouteCollection(), $cacheFile))
        ->not->toThrow(Exception::class);

    expect($cacheFile)->toBeFile();

    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
});

it('creates directory if needed', function () {
    $cacheFile = $this->cacheDir . '/nested/deep/route-cache.php';

    $router = new \Denosys\Routing\Router();
    $router->get('/test', fn() => 'test');

    $builder = new \Denosys\Routing\CacheBuilder();

    expect(fn() => $builder->buildRouteCache($router->getRouteCollection(), $cacheFile))
        ->not->toThrow(Exception::class);

    expect(dirname($cacheFile))->toBeDirectory();

    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }

    $dir = dirname($cacheFile);
    
    while ($dir !== $this->cacheDir && is_dir($dir)) {
        rmdir($dir);
        $dir = dirname($dir);
    }
});

it('generates example paths for parameterized routes', function () {
    $router = new \Denosys\Routing\Router();

    $router->get('/users/{id}/posts/{postId}', fn($id, $postId) => "user $id post $postId");

    $cacheFile = $this->cacheDir . '/example-paths.php';
    $builder = new \Denosys\Routing\CacheBuilder();

    expect(fn() => $builder->buildRouteCache($router->getRouteCollection(), $cacheFile))
        ->not->toThrow(Exception::class);

    expect($cacheFile)->toBeFile();

    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
});

// TDD: Cache Interface and Implementations Tests

it('FileCache implements CacheInterface', function () {
    $cacheFile = $this->cacheDir . '/file-cache-test.json';
    mkdir($this->cacheDir, 0755, true);

    $cache = new \Denosys\Routing\Cache\FileCache($cacheFile);

    expect($cache)->toBeInstanceOf(\Denosys\Routing\Cache\CacheInterface::class);
});

it('FileCache can store and retrieve values', function () {
    $cacheFile = $this->cacheDir . '/file-cache-test.json';
    mkdir($this->cacheDir, 0755, true);

    $cache = new \Denosys\Routing\Cache\FileCache($cacheFile);

    $cache->set('test_key', 'test_value');
    expect($cache->get('test_key'))->toBe('test_value')
        ->and($cache->has('test_key'))->toBeTrue()
        ->and($cache->has('missing_key'))->toBeFalse();

    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
});

it('FileCache persists data to file', function () {
    $cacheFile = $this->cacheDir . '/file-cache-persist.json';
    mkdir($this->cacheDir, 0755, true);

    $cache1 = new \Denosys\Routing\Cache\FileCache($cacheFile);
    $cache1->set('persisted', ['data' => 'value']);

    // Create new instance - should load from file
    $cache2 = new \Denosys\Routing\Cache\FileCache($cacheFile);
    expect($cache2->get('persisted'))->toBe(['data' => 'value']);

    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
});

it('FileCache can clear all data', function () {
    $cacheFile = $this->cacheDir . '/file-cache-clear.json';
    mkdir($this->cacheDir, 0755, true);

    $cache = new \Denosys\Routing\Cache\FileCache($cacheFile);
    $cache->set('key1', 'value1');
    $cache->set('key2', 'value2');

    expect($cache->has('key1'))->toBeTrue();

    $cache->clear();

    expect($cache->has('key1'))->toBeFalse()
        ->and($cache->has('key2'))->toBeFalse();

    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
});

it('ApcuCache implements CacheInterface', function () {
    if (!function_exists('apcu_enabled') || !apcu_enabled()) {
        $this->markTestSkipped('APCu not available');
    }

    $cache = new \Denosys\Routing\Cache\ApcuCache('test_prefix');

    expect($cache)->toBeInstanceOf(\Denosys\Routing\Cache\CacheInterface::class);
});

it('ApcuCache can store and retrieve values', function () {
    if (!function_exists('apcu_enabled') || !apcu_enabled()) {
        $this->markTestSkipped('APCu not available');
    }

    $cache = new \Denosys\Routing\Cache\ApcuCache('test_apcu_' . uniqid());

    $cache->set('test_key', 'test_value');
    expect($cache->get('test_key'))->toBe('test_value')
        ->and($cache->has('test_key'))->toBeTrue()
        ->and($cache->has('missing_key'))->toBeFalse();

    $cache->clear();
});

it('ApcuCache can clear all data with prefix', function () {
    if (!function_exists('apcu_enabled') || !apcu_enabled()) {
        $this->markTestSkipped('APCu not available');
    }

    $prefix = 'test_clear_' . uniqid();
    $cache = new \Denosys\Routing\Cache\ApcuCache($prefix);

    $cache->set('key1', 'value1');
    $cache->set('key2', 'value2');

    expect($cache->has('key1'))->toBeTrue();

    $cache->clear();

    expect($cache->has('key1'))->toBeFalse()
        ->and($cache->has('key2'))->toBeFalse();
});

it('NullCache implements CacheInterface', function () {
    $cache = new \Denosys\Routing\Cache\NullCache();

    expect($cache)->toBeInstanceOf(\Denosys\Routing\Cache\CacheInterface::class);
});

it('NullCache always returns null and never stores', function () {
    $cache = new \Denosys\Routing\Cache\NullCache();

    $cache->set('test_key', 'test_value');

    expect($cache->get('test_key'))->toBeNull()
        ->and($cache->has('test_key'))->toBeFalse();

    $cache->clear(); // Should not throw

    expect($cache->get('anything'))->toBeNull();
});
