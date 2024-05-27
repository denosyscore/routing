<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Denosys\Routing\Exceptions\NotFoundException;
use Denosys\Routing\Strategy\DefaultInvocationStrategy;
use Denosys\Routing\Strategy\InvocationStrategyInterface;
use Denosys\Routing\Exceptions\MethodNotAllowedException;

class Dispatcher implements DispatcherInterface, RequestHandlerInterface
{
    protected RouteTrie $routeTrie;

    protected array $middlewareQueue = [];

    protected $notFoundHandler = null;

    protected $methodNotAllowedHandler = null;

    protected bool $isTrieInitialized = false;

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

        $routeInfo = $this->match($request);

        if ($routeInfo === null) {
            throw new NotFoundException();
        }

        [$route, $params] = $routeInfo;

        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $this->invocationStrategy->invoke($route->getHandler(), $request, $params);
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

    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewareQueue[] = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = array_shift($this->middlewareQueue);
        if ($middleware === null) {
            return $this->dispatch($request);
        }

        return $middleware->process($request, $this);
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

        $this->routeTrie->compileDynamicRoutes();
        $this->isTrieInitialized = true;
    }

    protected function match(ServerRequestInterface $request): ?array
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $routeInfo = $this->routeTrie->findRoute($method, $path);

        if ($routeInfo !== null) {
            return $routeInfo;
        }

        $allowedMethods = [];

        foreach (Router::$methods as $methodToCheck) {
            if ($methodToCheck !== $method) {
                if ($this->routeTrie->findRoute($methodToCheck, $path) !== null) {
                    $allowedMethods[] = $methodToCheck;
                }
            }
        }

        if (!empty($allowedMethods)) {
            if ($this->methodNotAllowedHandler) {
                return call_user_func($this->methodNotAllowedHandler, $request, $allowedMethods);
            }
            throw new MethodNotAllowedException($allowedMethods);
        }

        return null;
    }
}
