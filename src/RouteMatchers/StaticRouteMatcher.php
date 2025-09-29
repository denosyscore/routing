<?php

declare(strict_types=1);

namespace Denosys\Routing\RouteMatchers;

use Denosys\Routing\RouteInterface;
use Denosys\Routing\RouteParser\RouteParser;

class StaticRouteMatcher implements RouteMatcherInterface
{
    private array $routes = [];
    private int $hits = 0;
    private RouteParser $parser;

    public function __construct(?RouteParser $parser = null)
    {
        $this->parser = $parser ?? new RouteParser();
    }

    public function canMatch(string $pattern): bool
    {
        return $this->parser->isStaticRoute($pattern);
    }

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        if (!isset($this->routes[$method][$pattern])) {
            $this->routes[$method][$pattern] = [];
        }
        
        $this->routes[$method][$pattern][] = $route;
    }

    public function findRoute(string $method, string $path): ?array
    {
        if (isset($this->routes[$method][$path])) {
            $this->hits++;

            $routes = $this->routes[$method][$path];

            return [$routes[count($routes) - 1], []];
        }

        return null;
    }

    public function findAllRoutes(string $method, string $path): array
    {
        if (isset($this->routes[$method][$path])) {
            $this->hits++;

            $routes = array_reverse($this->routes[$method][$path]);

            return array_map(fn($route) => [$route, []], $routes);
        }

        return [];
    }

    public function getType(): string
    {
        return 'static';
    }

    public function getStats(): array
    {
        return [
            'type' => $this->getType(),
            'hits' => $this->hits,
            'routes_count' => array_sum(array_map('count', $this->routes))
        ];
    }

    public function resetStats(): void
    {
        $this->hits = 0;
    }
}
