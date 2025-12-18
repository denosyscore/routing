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

    protected MiddlewareRegistryInterface $middlewareRegistry;

    protected array $pendingMiddleware = [];

    protected array $globalMiddleware = [];

    public function __construct(
        protected ?ContainerInterface $container = null,
        protected ?RouteCollectionInterface $routeCollection = null,
        protected ?RouteManagerInterface $routeManager = null,
        ?DispatcherInterface $dispatcher = null,
        ?MiddlewareRegistryInterface $middlewareRegistry = null
    ) {
        $this->routeCollection = $routeCollection ?? new RouteCollection();
        $this->routeManager = $routeManager ?? new RouteManager();
        $this->middlewareRegistry = $middlewareRegistry ?? new MiddlewareRegistry();
        $this->dispatcher = $dispatcher ?? Dispatcher::withDefaults(
            routeCollection: $this->routeCollection,
            routeManager: $this->routeManager,
            container: $this->container,
            middlewareRegistry: $this->middlewareRegistry
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
        $this->dispatcher->setGlobalMiddleware($this->globalMiddleware);

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

    /**
     * Add global middleware that applies to all requests.
     *
     * Global middleware runs on every request, wrapping the entire
     * application. It executes before any route-specific middleware.
     *
     * @param string|array|object $middleware Middleware class name(s), alias(es), or instance(s)
     */
    public function use(string|array|object $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middlewares as $middlewareItem) {
            $this->globalMiddleware[] = $middlewareItem;
        }

        return $this;
    }

    public function getRouteCollection(): RouteCollectionInterface
    {
        return $this->routeCollection;
    }

    /**
     * Get the middleware registry.
     */
    public function getMiddlewareRegistry(): MiddlewareRegistryInterface
    {
        return $this->middlewareRegistry;
    }

    /**
     * Register a middleware alias.
     * 
     * @param string $name The alias name (e.g., 'auth')
     * @param string $class The middleware class name
     */
    public function aliasMiddleware(string $name, string $class): static
    {
        $this->middlewareRegistry->alias($name, $class);

        return $this;
    }

    /**
     * Register a middleware group.
     * 
     * @param string $name The group name (e.g., 'web')
     * @param array<string> $middleware Array of middleware names/classes
     */
    public function middlewareGroup(string $name, array $middleware): static
    {
        $this->middlewareRegistry->group($name, $middleware);

        return $this;
    }

    /**
     * Add middleware to the beginning of an existing group.
     */
    public function prependMiddlewareToGroup(string $group, string|array $middleware): static
    {
        $this->middlewareRegistry->prependToGroup($group, $middleware);

        return $this;
    }

    /**
     * Add middleware to the end of an existing group.
     */
    public function appendMiddlewareToGroup(string $group, string|array $middleware): static
    {
        $this->middlewareRegistry->appendToGroup($group, $middleware);

        return $this;
    }
}
