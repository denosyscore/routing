<?php

declare(strict_types=1);

namespace Denosys\Routing;

class CachedRouteMatcher implements RouteManagerInterface
{
    public function __construct(
        private RouteManagerInterface $manager,
        private ?Cache $cache = null
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
            return $cached;
        }

        $result = $this->manager->findRoute($method, $path);

        if ($result !== null) {
            $this->cache->set($cacheKey, $result);
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
            return $cached;
        }

        $results = $this->manager->findAllRoutes($method, $path);

        if (!empty($results)) {
            $this->cache->set($cacheKey, $results);
        }

        return $results;
    }
}
