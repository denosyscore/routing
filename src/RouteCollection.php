<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;

class RouteCollection implements RouteCollectionInterface
{
    protected int $routeCounter = 0;

    protected array $routes = [];

    public function __construct(protected RouteHandlerResolverInterface $routeHandlerResolver)
    {
    }

    public function add(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        $route = $this->createRoute($methods, $pattern, $handler);
        $this->routes[$route->getIdentifier()] = $route;

        $this->routeCounter++;

        return $route;
    }

    public function all(): array
    {
        return $this->routes;
    }

    public function get(string $method, string $path): ?RouteInterface
    {
        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $route;
            }
        }
        return null;
    }

    protected function createRoute(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        return new Route(
            $methods,
            $pattern,
            $handler,
            $this->routeHandlerResolver,
            $this->routeCounter
        );
    }
}
