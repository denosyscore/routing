<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Denosys\Routing\Attributes\AttributeRouteScanner;

/**
 * Handles loading routes from PHP attributes.
 * Separates attribute route loading concerns from the Router class.
 */
class AttributeRouteLoader
{
    private ?string $defaultCachePath = null;

    public function __construct(
        private Router $router,
        private ?AttributeRouteScanner $scanner = null
    ) {
        $this->scanner = $scanner ?? new AttributeRouteScanner();
    }

    public function setDefaultCachePath(string $path): void
    {
        $this->defaultCachePath = $path;
    }

    public function loadFromClasses(array $classes, ?string $cachePath = null): void
    {
        $cacheFile = $cachePath ?? $this->defaultCachePath;

        if ($cacheFile && file_exists($cacheFile)) {
            $this->loadFromCache($cacheFile);
            return;
        }

        foreach ($classes as $class) {
            $routes = $this->scanner->scanClass($class);
            $this->registerRoutes($routes);
        }
    }

    public function loadFromDirectory(string $directory, ?string $cachePath = null): void
    {
        $cacheFile = $cachePath ?? $this->defaultCachePath;

        if ($cacheFile && file_exists($cacheFile)) {
            $this->loadFromCache($cacheFile);
            return;
        }

        $routes = $this->scanner->scanDirectory($directory);
        $this->registerRoutes($routes);
    }

    public function loadFromCache(string $cachePath): void
    {
        $routes = $this->scanner->loadCachedRoutes($cachePath);

        if ($routes === null) {
            throw new \RuntimeException("Failed to load routes from cache file: {$cachePath}");
        }

        $this->registerRoutes($routes);
    }

    public function cacheClasses(array $classes, ?string $cachePath = null): void
    {
        $cacheFile = $cachePath ?? $this->defaultCachePath;

        if (!$cacheFile) {
            throw new \InvalidArgumentException('Cache file path must be provided');
        }

        $allRoutes = [];

        foreach ($classes as $class) {
            $routes = $this->scanner->scanClass($class);
            $allRoutes = array_merge($allRoutes, $routes);
        }

        $this->scanner->cacheRoutes($allRoutes, $cacheFile);
    }

    public function cacheDirectory(string $directory, ?string $cachePath = null): void
    {
        $cacheFile = $cachePath ?? $this->defaultCachePath;

        if (!$cacheFile) {
            throw new \InvalidArgumentException('Cache file path must be provided');
        }

        $routes = $this->scanner->scanDirectory($directory);
        $this->scanner->cacheRoutes($routes, $cacheFile);
    }

    public function clearCache(?string $cachePath = null): void
    {
        $cacheFile = $cachePath ?? $this->defaultCachePath;

        if (!$cacheFile) {
            throw new \InvalidArgumentException('Cache file path must be provided');
        }

        $this->scanner->clearCache($cacheFile);
    }

    private function registerRoutes(array $routes): void
    {
        foreach ($routes as $routeData) {
            $route = $this->router->addRoute(
                $routeData['methods'],
                $routeData['path'],
                $routeData['action']
            );

            if ($routeData['name']) {
                $route->name($routeData['name']);
            }

            foreach ($routeData['where'] as $param => $pattern) {
                $route->where($param, $pattern);
            }

            foreach ($routeData['middleware'] as $middleware) {
                $route->middleware($middleware);
            }
        }
    }
}
