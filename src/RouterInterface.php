<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface
{
    public function get(string $pattern, Closure|array|string $handler): RouteInterface;
    public function post(string $pattern, Closure|array|string $handler): RouteInterface;
    public function put(string $pattern, Closure|array|string $handler): RouteInterface;
    public function delete(string $pattern, Closure|array|string $handler): RouteInterface;
    public function patch(string $pattern, Closure|array|string $handler): RouteInterface;
    public function options(string $pattern, Closure|array|string $handler): RouteInterface;
    public function any(string $pattern, Closure|array|string $handler): RouteInterface;
    public function match(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface;
    
    // Middleware methods
    public function middleware(MiddlewareInterface|array|string $middleware, int $priority = 0): static;
    public function middlewareWhen(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static;
    public function middlewareUnless(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static;
    public function prependMiddleware(MiddlewareInterface|array|string $middleware, int $priority = 1000): static;
    public function skipMiddleware(string $middlewareClass): static;
    public function dispatch(ServerRequestInterface $request): ResponseInterface;
    public function group(string $prefix, Closure $callback): RouteGroupInterface;
}
