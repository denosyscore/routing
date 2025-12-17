<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router implements RouterInterface
{
    use HasRouteMethods;

    public static array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

    protected DispatcherInterface $dispatcher;

    protected array $pendingMiddleware = [];

    public function __construct(
        protected ?ContainerInterface $container = null,
        protected ?RouteCollectionInterface $routeCollection = null,
        protected ?RouteManagerInterface $routeManager = null,
        ?DispatcherInterface $dispatcher = null
    ) {
        $this->routeCollection = $routeCollection ?? new RouteCollection();
        $this->routeManager = $routeManager ?? new RouteManager();
        $this->dispatcher = $dispatcher ?? Dispatcher::withDefaults(
            routeCollection: $this->routeCollection,
            routeManager: $this->routeManager,
            container: $this->container
        );
    }

    public function addRoute(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        $route = $this->routeCollection->add($methods, $pattern, $handler);

        foreach ($this->pendingMiddleware as $middlewareItem) {
            $route->middleware($middlewareItem);
        }

        $this->pendingMiddleware = [];

        $this->dispatcher->markRoutesDirty();

        return $route;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {   
        return $this->dispatcher->dispatch($request);
    }

    public function group(string $prefix, Closure $callback): RouteGroupInterface
    {
        $routeGroup = new RouteGroup($prefix, $this, $this->container);

        foreach ($this->pendingMiddleware as $middlewareItem) {
            $routeGroup->addGroupMiddleware($middlewareItem);
        }

        $this->pendingMiddleware = [];

        $callback($routeGroup);

        $routeGroup->markCallbackFinished();

        return $routeGroup;
    }

    public function middleware(string|array $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middlewares as $middlewareItem) {
            $this->pendingMiddleware[] = $middlewareItem;
        }

        return $this;
    }

    public function getRouteCollection(): RouteCollectionInterface
    {
        return $this->routeCollection;
    }
}
