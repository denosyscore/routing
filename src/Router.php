<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router implements RouterInterface
{
    public static array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

    protected Dispatcher $dispatcher;

    public function __construct(
        protected ?ContainerInterface $container = null,
        protected ?RouteCollectionInterface $routeCollection = null,
        protected ?RouteHandlerResolverInterface $routeHandlerResolver = null
    ) {
        $this->routeHandlerResolver = $routeHandlerResolver ?? new RouteHandlerResolver($this->container);
        $this->routeCollection = $routeCollection ?? new RouteCollection($this->routeHandlerResolver);
        $this->dispatcher = new Dispatcher(
            routeCollection: $this->routeCollection,
            container: $this->container
        );
    }

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
        return $this->addRoute(self::$methods, $pattern, $handler);
    }

    public function match(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->addRoute(array_map('strtoupper', (array) $methods), $pattern, $handler);
    }

    public function addRoute(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        return $this->routeCollection->add($methods, $pattern, $handler);
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        return $this->dispatcher->dispatch($request);
    }
}
