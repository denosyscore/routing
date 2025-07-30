<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;

trait HasRouteMethods
{
    public function get(string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->addRoute(['GET', 'HEAD'], $pattern, $handler);
    }

    public function post(string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->addRoute('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->addRoute('DELETE', $pattern, $handler);
    }

    public function patch(string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->addRoute('PATCH', $pattern, $handler);
    }

    public function options(string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->addRoute('OPTIONS', $pattern, $handler);
    }

    public function any(string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->addRoute(Router::$methods, $pattern, $handler);
    }

    public function match(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->addRoute(array_map('strtoupper', (array) $methods), $pattern, $handler);
    }

    abstract public function addRoute(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface;
}
