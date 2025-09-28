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

class Dispatcher implements DispatcherInterface, RequestHandlerInterface
{

    protected RouteTrie $routeTrie;
    protected bool $isTrieInitialized = false;
    protected $notFoundHandler = null;
    protected $methodNotAllowedHandler = null;

    public function __construct(
        protected RouteCollectionInterface $routeCollection,
        protected ?InvocationStrategyInterface $invocationStrategy = null,
        protected ?ContainerInterface $container = null,
        ?string $routeCacheFile = null
    ) {
        $this->invocationStrategy = $invocationStrategy ?? new DefaultInvocationStrategy($this->container);
        $this->routeTrie = new RouteTrie($routeCacheFile);
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $this->initializeTrie();

        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        if ($path === '') {
            $path = '/';
        } elseif ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        
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
        $path = $request->getUri()->getPath();
        
        if ($path === '') {
            $path = '/';
        } elseif ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        
        $routeInfo = $this->routeTrie->findRoute($request->getMethod(), $path);

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

        return $this->invocationStrategy->invoke($route->getHandler(), $request, $params);
    }

    public function getPerformanceStats(): array
    {
        return $this->routeTrie->getPerformanceStats();
    }
}
