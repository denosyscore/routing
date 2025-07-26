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
    use HasMiddleware;

    protected RouteTrie $routeTrie;
    protected bool $isTrieInitialized = false;
    protected $notFoundHandler = null;
    protected $methodNotAllowedHandler = null;

    public function __construct(
        protected RouteCollectionInterface $routeCollection,
        protected ?InvocationStrategyInterface $invocationStrategy = null,
        protected ?ContainerInterface $container = null
    ) {
        $this->invocationStrategy = $invocationStrategy ?? new DefaultInvocationStrategy($this->container);
        $this->routeTrie = new RouteTrie();
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $this->initializeTrie();

        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $routeInfo = $this->routeTrie->findRoute($method, $path);

        if ($routeInfo === null) {
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
        if ($middleware = array_shift($this->middlewareStack)) {
            return $middleware->process($request, $this);
        }

        $routeInfo = $this->routeTrie->findRoute($request->getMethod(), $request->getUri()->getPath());

        if ($routeInfo === null) {
            throw new NotFoundException();
        }

        [$route, $params] = $routeInfo;

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

        // Add global middleware to the stack
        $middlewareStack = [];
        foreach ($this->middlewareStack as $middleware) {
            $middlewareStack[] = $this->resolveMiddleware($middleware);
        }

        // Add route-specific middleware to the stack
        foreach ($route->getMiddlewareStack() as $middleware) {
            $middlewareStack[] = $this->resolveMiddleware($middleware);
        }

        // Set the middleware stack for processing
        $this->middlewareStack = $middlewareStack;

        return $this->handle($request);
    }
}
