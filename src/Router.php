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
    use HasMiddleware;

    public static array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

    protected Dispatcher $dispatcher;

    protected array $groups = [];

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

    public function addRoute(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        $route = $this->routeCollection->add($methods, $pattern, $handler);

        foreach ($this->getMiddlewareStack() as $middleware) {
            $route->middleware($middleware);
        }

        return $route;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        foreach ($this->getMiddlewareStack() as $middleware) {
            $this->dispatcher->middleware($middleware);
        }

        return $this->dispatcher->dispatch($request);
    }

    public function group(string $prefix, Closure $callback): RouteGroupInterface
    {
        $routeGroup = new RouteGroup($prefix, $this, $this->container);
        
        // Apply router-level middleware to group
        foreach ($this->getMiddlewareStack() as $middleware) {
            $routeGroup->middleware($middleware);
        }
        
        $callback($routeGroup);

        return $routeGroup;
    }
}
