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
    protected bool $isTrieInitialized = false;
    protected $notFoundHandler = null;
    protected $methodNotAllowedHandler = null;

    public function __construct(
        protected RouteCollectionInterface $routeCollection,
        protected RouteManagerInterface $routeManager,
        protected ?InvocationStrategyInterface $invocationStrategy = null,
        protected ?ContainerInterface $container = null
    ) {
        $this->invocationStrategy = $invocationStrategy ?? new DefaultInvocationStrategy($this->container);
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $this->initializeTrie();

        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $host = $this->extractHost($request);
        $scheme = $request->getUri()->getScheme();

        if ($path === '') {
            $path = '/';
        } elseif ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $routeInfo = $this->findMatchingRoute($method, $path, $host, $scheme);

        if ($routeInfo === null) {
            if ($this->notFoundHandler) {
                return ($this->notFoundHandler)($request);
            }

            throw new NotFoundException(sprintf('No route found for %s %s', $method, $path), 404);
        }

        return $this->handleRoute($request, $routeInfo, $host, $scheme);
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
        $host = $this->extractHost($request);
        $scheme = $request->getUri()->getScheme();

        if ($path === '') {
            $path = '/';
        } elseif ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $routeInfo = $this->findMatchingRoute($request->getMethod(), $path, $host, $scheme);

        if ($routeInfo === null) {
            throw new NotFoundException();
        }

        return $this->handleRoute($request, $routeInfo, $host, $scheme);
    }

    protected function initializeTrie(): void
    {
        if ($this->isTrieInitialized) {
            return;
        }

        foreach ($this->routeCollection->all() as $route) {
            foreach ($route->getMethods() as $method) {
                $this->routeManager->addRoute($method, $route->getPattern(), $route);
            }
        }

        $this->isTrieInitialized = true;
    }

    protected function handleRoute(ServerRequestInterface $request, array $routeInfo, ?string $host, ?string $scheme = null): ResponseInterface
    {
        /** @var RouteInterface $route */
        [$route, $params] = $routeInfo;

        if ($host !== null && method_exists($route, 'getHostParameters')) {
            $hostParams = $route->getHostParameters($host);
            $params = array_merge($hostParams, $params);
        }

        if ($host !== null && method_exists($route, 'getPortParameters')) {
            $portParams = $route->getPortParameters($host, $scheme);
            $params = array_merge($portParams, $params);
        }

        if ($scheme !== null && method_exists($route, 'getSchemeParameters')) {
            $schemeParams = $route->getSchemeParameters($scheme);
            $params = array_merge($schemeParams, $params);
        }

        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $this->invocationStrategy->invoke($route->getHandler(), $request, $params);
    }

    protected function findMatchingRoute(string $method, string $path, ?string $host, ?string $scheme = null): ?array
    {
        $allRoutes = $this->routeManager->findAllRoutes($method, $path);

        if (empty($allRoutes)) {
            $routeInfo = $this->routeManager->findRoute($method, $path);

            if ($routeInfo === null) {
                return null;
            }

            [$route, $params] = $routeInfo;

            if (!$this->routeMatchesConditions($route, $host, $scheme)) {
                return null;
            }

            return $routeInfo;
        }

        foreach ($allRoutes as $routeInfo) {
            [$route, $params] = $routeInfo;

            if ($this->routeMatchesConditions($route, $host, $scheme)) {
                return $routeInfo;
            }
        }

        return null;
    }

    protected function routeMatchesConditions(RouteInterface $route, ?string $host, ?string $scheme): bool
    {
        if (method_exists($route, 'matchesHost') && !$route->matchesHost($host)) {
            return false;
        }

        if (method_exists($route, 'matchesPort') && !$route->matchesPort($host, $scheme)) {
            return false;
        }

        if (method_exists($route, 'matchesScheme') && !$route->matchesScheme($scheme)) {
            return false;
        }

        return true;
    }

    protected function extractHost(ServerRequestInterface $request): ?string
    {
        $hostHeaders = $request->getHeader('Host');

        if (empty($hostHeaders)) {
            return null;
        }

        $host = $hostHeaders[0];

        // The Host header may include port (e.g., "example.com:8080")
        // We return the full value so routes can match ports if needed
        return $host;
    }
}
