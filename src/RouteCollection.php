<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Denosys\Routing\Factories\RouteFactory;

class RouteCollection implements RouteCollectionInterface
{
    protected array $routes = [];

    public function __construct(
        protected ?RouteFactory $routeFactory = null
    ) {
        $this->routeFactory = $routeFactory ?? new RouteFactory();
    }

    public function add(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        $route = $this->createRoute($methods, $pattern, $handler);

        $this->routes[$route->getIdentifier()] = $route;

        return $route;
    }

    public function all(): array
    {
        return $this->routes;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function get(string $method, string $pattern): ?RouteInterface
    {
        foreach ($this->routes as $route) {
            if ($route->matches($method, $pattern)) {
                return $route;
            }
        }

        return null;
    }

    public function findByName(string $name): ?RouteInterface
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }

        return null;
    }

    public function getNamedRoutes(): array
    {
        $namedRoutes = [];

        foreach ($this->routes as $route) {
            $name = $route->getName();

            if ($name !== null) {
                $namedRoutes[$name] = $route;
            }
        }
        
        return $namedRoutes;
    }

    public function findByIdentifier(string $identifier): ?RouteInterface
    {
        return $this->routes[$identifier] ?? null;
    }

    protected function createRoute(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        // Normalize pattern
        if ($pattern === '' || $pattern === '/') {
            $pattern = '/';
        } elseif ($pattern !== '/' && str_ends_with($pattern, '/')) {
            $pattern = rtrim($pattern, '/');
        }

        return $this->routeFactory->create($methods, $pattern, $handler);
    }
}
