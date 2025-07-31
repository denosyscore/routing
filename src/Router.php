<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Denosys\Routing\Middleware\MiddlewareManager;

class Router implements RouterInterface
{
    use HasRouteMethods;
    use HasMiddleware;

    public static array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

    protected Dispatcher $dispatcher;
    
    protected array $middleware = [];

    public function __construct(
        protected ?ContainerInterface $container = null,
        protected ?RouteCollectionInterface $routeCollection = null,
        protected ?RouteHandlerResolverInterface $routeHandlerResolver = null,
        ?MiddlewareManager $middlewareManager = null
    ) {
        $this->routeHandlerResolver = $routeHandlerResolver ?? new RouteHandlerResolver($this->container);
        $this->routeCollection = $routeCollection ?? new RouteCollection($this->routeHandlerResolver);
        $middlewareManager = $middlewareManager ?? new MiddlewareManager($this->container);
        $this->dispatcher = new Dispatcher(
            routeCollection: $this->routeCollection,
            container: $this->container,
            middlewareManager: $middlewareManager
        );
        
        $this->setMiddlewareManager($middlewareManager);
    }

    public function addRoute(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        $route = $this->routeCollection->add($methods, $pattern, $handler);

        if (method_exists($route, 'setMiddlewareManager')) {
            $route->setMiddlewareManager($this->getMiddlewareManager());
        }

        foreach ($this->middleware as $middlewareItem) {
            $route->middleware($middlewareItem['middleware'], $middlewareItem['priority']);
        }

        $this->middleware = [];

        return $route;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {   
        return $this->dispatcher->dispatch($request);
    }

    public function group(string $prefix, Closure $callback): RouteGroupInterface
    {
        $routeGroup = new RouteGroup($prefix, $this, $this->container);
        $routeGroup->setMiddlewareManager($this->getMiddlewareManager());
        
        foreach ($this->middleware as $middlewareItem) {
            $routeGroup->addGroupMiddleware($middlewareItem['middleware'], $middlewareItem['priority']);
        }
        
        $this->middleware = [];
        
        $callback($routeGroup);

        $routeGroup->markCallbackFinished();

        return $routeGroup;
    }

    public function aliasMiddleware(string $alias, string|MiddlewareInterface $middleware): static
    {
        $this->getMiddlewareManager()->alias($alias, $middleware);
        return $this;
    }

    public function aliasMiddlewares(array $aliases): static
    {
        $this->getMiddlewareManager()->aliasMany($aliases);
        return $this;
    }

    public function getMiddlewareManager(): MiddlewareManager
    {
        return $this->dispatcher->getMiddlewareManager();
    }

    public function middleware(MiddlewareInterface|array|string $middleware, int $priority = 0): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        foreach ($middlewares as $mw) {
            $this->middleware[] = ['middleware' => $mw, 'priority' => $priority];
        }
        return $this;
    }

    public function middlewareWhen(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static
    {
        $shouldExecute = is_callable($condition) ? $condition() : $condition;
        if ($shouldExecute) {
            return $this->middleware($middleware, $priority);
        }
        return $this;
    }

    public function middlewareUnless(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static
    {
        $shouldNotExecute = is_callable($condition) ? $condition() : $condition;
        if (!$shouldNotExecute) {
            return $this->middleware($middleware, $priority);
        }
        return $this;
    }

    public function prependMiddleware(MiddlewareInterface|array|string $middleware, int $priority = 1000): static
    {
        return $this->middleware($middleware, $priority);
    }
}
