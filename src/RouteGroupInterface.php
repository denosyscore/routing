<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Http\Server\MiddlewareInterface;

interface RouteGroupInterface
{
    public function get(string $pattern, Closure|array|string $handler): RouteInterface;
    public function post(string $pattern, Closure|array|string $handler): RouteInterface;
    public function put(string $pattern, Closure|array|string $handler): RouteInterface;
    public function delete(string $pattern, Closure|array|string $handler): RouteInterface;
    public function patch(string $pattern, Closure|array|string $handler): RouteInterface;
    public function options(string $pattern, Closure|array|string $handler): RouteInterface;
    public function any(string $pattern, Closure|array|string $handler): RouteInterface;
    public function match(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface;
    
    public function group(string $prefix, Closure $callback): self;
    public function middleware(MiddlewareInterface|array|string $middleware): static;
    public function getMiddlewareStack(): array;
    
    // Naming and namespacing
    public function name(string $name): static;
    public function namespace(string $namespace): static;
    
    // Constraints
    public function domain(string $domain): static;
    public function where(string $parameter, string $pattern): static;
    public function whereIn(string $parameter, array $values): static;
    public function whereNumber(string $parameter): static;
    public function whereAlpha(string $parameter): static;
    public function whereAlphaNumeric(string $parameter): static;
    
    // Conditional registration
    public function when(bool|Closure $condition, Closure $callback): static;
    public function unless(bool|Closure $condition, Closure $callback): static;
}
