<?php

declare(strict_types=1);

namespace Denosys\Routing;

class RouteTrie
{
    private array $staticRoutes = [];
    private array $dynamicRoutes = [];

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        if (!str_contains($pattern, '{')) {
            $this->staticRoutes[$method][$pattern] = $route;
        } else {
            $this->dynamicRoutes[$method][] = ['pattern' => $pattern, 'route' => $route];
        }
    }

    public function compileDynamicRoutes(): void
    {
        foreach ($this->dynamicRoutes as $method => $routes) {
            $patterns = [];
            foreach ($routes as $routeInfo) {
                $pattern = RegexHelper::patternToRegex($routeInfo['pattern']);
                // Remove the surrounding delimiters and anchors added by patternToRegex
                $pattern = substr($pattern, 2, -2);
                $patterns[] = "(?P<{$routeInfo['route']->getIdentifier()}>{$pattern})";
            }
            $this->dynamicRoutes[$method]['compiled'] = '#^(' . implode('|', $patterns) . ')$#';
        }
    }

    public function findRoute(string $method, string $path): ?array
    {
        // Check static routes first
        if (isset($this->staticRoutes[$method][$path])) {
            return [$this->staticRoutes[$method][$path], []];
        }

        // Check dynamic routes
        if (isset($this->dynamicRoutes[$method]['compiled'])) {
            $regex = $this->dynamicRoutes[$method]['compiled'];
            if (preg_match($regex, $path, $matches)) {
                foreach ($matches as $key => $value) {
                    // Ensure the key is a string and starts with '_route_'
                    if (is_string($key) && str_starts_with($key, '_route_')) {
                        $routeId = $key;
                        foreach ($this->dynamicRoutes[$method] as $routeInfo) {
                            if ($routeInfo['route']->getIdentifier() === $routeId) {
                                $params = RegexHelper::extractParameters($routeInfo['pattern'], $path);
                                return [$routeInfo['route'], $params];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }
}
