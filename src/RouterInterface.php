<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Http\Message\ResponseInterface;
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
    public function middleware(string|array $middleware): static;
    public function dispatch(ServerRequestInterface $request): ResponseInterface;
    public function group(string $prefix, Closure $callback): RouteGroupInterface;
    public function getRouteCollection(): RouteCollectionInterface;
    public function getUrlGenerator(string $baseUrl = ''): UrlGeneratorInterface;
}
