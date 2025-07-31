<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Denosys\Routing\Exceptions\NotFoundException;
use Denosys\Routing\Strategy\DefaultInvocationStrategy;
use Denosys\Routing\Strategy\InvocationStrategyInterface;
use Denosys\Routing\Middleware\MiddlewareManager;
use Denosys\Routing\Middleware\MiddlewarePipeline;

class Dispatcher implements DispatcherInterface, RequestHandlerInterface  
{
    use HasMiddleware;

    protected RouteTrie $routeTrie;
    protected bool $isTrieInitialized = false;
    protected $notFoundHandler = null;
    protected $methodNotAllowedHandler = null;

    public function __construct(
        protected RouteCollectionInterface $routeCollection,
        protected ?InvocationStrategyInterface $invocationStrategy = null,
        protected ?ContainerInterface $container = null,
        ?MiddlewareManager $middlewareManager = null,
        ?string $routeCacheFile = null,
        ?string $middlewareCacheFile = null
    ) {
        $this->invocationStrategy = $invocationStrategy ?? new DefaultInvocationStrategy($this->container);
        $this->routeTrie = new RouteTrie($routeCacheFile);
        $manager = $middlewareManager ?? new MiddlewareManager($this->container, $middlewareCacheFile);
        $this->setMiddlewareManager($manager);
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $this->initializeTrie();

        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $routeInfo = $this->routeTrie->findRoute($method, $path);

        if ($routeInfo === null) {
            if ($this->notFoundHandler) {
                return ($this->notFoundHandler)($request);
            }
            throw new NotFoundException(sprintf('No route found for %s %s', $method, $path), 404);
        }

        return $this->handleRoute($request, $routeInfo);
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function setMethodNotAllowedHandler(callable $handler): void
    {
        $this->methodNotAllowedHandler = $handler;
    }

    public function setInvocationStrategy(InvocationStrategyInterface $strategy): void
    {
        $this->invocationStrategy = $strategy;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $routeInfo = $this->routeTrie->findRoute($request->getMethod(), $request->getUri()->getPath());

        if ($routeInfo === null) {
            throw new NotFoundException();
        }

        [$route, $params] = $routeInfo;

        // Add route parameters as request attributes
        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);  
        }

        return $this->invocationStrategy->invoke($route->getHandler(), $request, $params);
    }

    protected function initializeTrie(): void
    {
        if ($this->isTrieInitialized) {
            return;
        }

        foreach ($this->routeCollection->all() as $route) {
            if ($route instanceof HasMiddleware && method_exists($route, 'setMiddlewareManager')) {
                $route->setMiddlewareManager($this->middlewareManager);
            }
            
            foreach ($route->getMethods() as $method) {
                $this->routeTrie->addRoute($method, $route->getPattern(), $route);
            }
        }

        $this->isTrieInitialized = true;
    }

    protected function handleRoute(ServerRequestInterface $request, array $routeInfo): ResponseInterface
    {
        [$route, $params] = $routeInfo;

        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        $middlewareStack = [];
        
        foreach ($this->getMiddlewareStack() as $middlewareItem) {
            $middlewareStack[] = $middlewareItem;
        }
        
        if (method_exists($route, 'getMiddlewareStack')) {
            foreach ($route->getMiddlewareStack() as $middlewareItem) {
                $middlewareStack[] = $middlewareItem;
            }
        }

        // Sort all middleware by priority
        usort($middlewareStack, fn($a, $b) => $b->priority <=> $a->priority);

        // Filter out conditional middleware and resolve to instances
        $resolvedMiddleware = [];
        foreach ($middlewareStack as $item) {
            if ($item->shouldExecute()) {
                $resolvedMiddleware[] = $this->getMiddlewareManager()->resolve($item->middleware);
            }
        }

        $pipeline = new MiddlewarePipeline($resolvedMiddleware);
        return $pipeline->then($this)->handle($request);
    }

    public function getMiddlewareManager(): MiddlewareManager
    {
        return $this->middlewareManager ?? throw new \RuntimeException('MiddlewareManager not initialized');
    }

    public function setMiddlewareAliases(array $aliases): void
    {
        $this->getMiddlewareManager()->aliasMany($aliases);
    }
}
