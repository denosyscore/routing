<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Denosys\Routing\Cache\CacheInterface;

readonly class CachedRouteMatcher implements RouteManagerInterface
{
    public function __construct(
        private RouteManagerInterface $manager,
        private ?CacheInterface $cache = null,
        private ?RouteCollectionInterface $routeCollection = null
    ) {
    }

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        $this->manager->addRoute($method, $pattern, $route);
    }

    public function findRoute(string $method, string $path): ?array
    {
        if ($this->cache === null) {
            return $this->manager->findRoute($method, $path);
        }

        $cacheKey = "route_{$method}_{$path}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            $rehydrated = $this->rehydrateRoute($cached);

            if ($rehydrated !== null) {
                return $rehydrated;
            }
        }

        $result = $this->manager->findRoute($method, $path);

        if ($result !== null) {
            $this->cache->set($cacheKey, $this->dehydrateRoute($result));
        }

        return $result;
    }

    public function findAllRoutes(string $method, string $path): array
    {
        if ($this->cache === null) {
            return $this->manager->findAllRoutes($method, $path);
        }

        $cacheKey = "all_routes_{$method}_{$path}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            $rehydrated = $this->rehydrateRouteList($cached);

            if ($rehydrated !== null) {
                return $rehydrated;
            }
        }

        $results = $this->manager->findAllRoutes($method, $path);

        if (!empty($results)) {
            $this->cache->set($cacheKey, $this->dehydrateRouteList($results));
        }

        return $results;
    }

    public function reset(): void
    {
        if (method_exists($this->manager, 'reset')) {
            $this->manager->reset();
        }

        $this->cache?->clear();
    }

    private function dehydrateRoute(array $routeInfo): array
    {
        [$route, $params] = $routeInfo;

        return [
            'id' => $route->getIdentifier(),
            'params' => $params,
        ];
    }

    private function dehydrateRouteList(array $routes): array
    {
        return array_map(fn(array $routeInfo) => $this->dehydrateRoute($routeInfo), $routes);
    }

    private function rehydrateRoute(array $payload): ?array
    {
        if (!isset($payload['id'])) {
            return null;
        }

        $route = $this->routeCollection?->findByIdentifier($payload['id']);

        if ($route === null) {
            return null;
        }

        return [$route, $payload['params'] ?? []];
    }

    private function rehydrateRouteList(array $payload): ?array
    {
        $rehydrated = [];

        foreach ($payload as $item) {
            $routeInfo = $this->rehydrateRoute($item);

            if ($routeInfo === null) {
                return null;
            }

            $rehydrated[] = $routeInfo;
        }

        return $rehydrated;
    }
}
