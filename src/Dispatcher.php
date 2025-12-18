<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Denosys\Routing\Exceptions\NotFoundException;
use Denosys\Routing\RouteHandlerResolverInterface;
use Denosys\Routing\RouteHandlerResolver;
use Denosys\Routing\Exceptions\MethodNotAllowedException;
use Denosys\Routing\Strategy\DefaultInvocationStrategy;
use Denosys\Routing\Strategy\InvocationStrategyInterface;

class Dispatcher implements DispatcherInterface, RequestHandlerInterface
{
    protected bool $isTrieInitialized = false;
    protected bool $routesDirty = true;

    /** @var callable|null */
    protected $notFoundHandler = null;

    /** @var callable|null */
    protected $methodNotAllowedHandler = null;

    /** @var callable|null */
    protected $exceptionHandler = null;

    protected InvocationStrategyInterface $invocationStrategy;
    protected RouteHandlerResolverInterface $routeHandlerResolver;
    protected RequestContextExtractor $contextExtractor;
    protected RouteMatcher $routeMatcher;
    protected MiddlewarePipelineBuilder $middlewarePipelineBuilder;
    protected MiddlewareRegistryInterface $middlewareRegistry;

    /** @var array<string> */
    protected array $globalMiddleware = [];

    public function __construct(
        protected RouteCollectionInterface $routeCollection,
        protected RouteManagerInterface $routeManager,
        InvocationStrategyInterface $invocationStrategy,
        RouteHandlerResolverInterface $routeHandlerResolver,
        protected ?ContainerInterface $container = null,
        ?RequestContextExtractor $contextExtractor = null,
        ?RouteMatcher $routeMatcher = null,
        ?MiddlewarePipelineBuilder $middlewarePipelineBuilder = null,
        ?MiddlewareRegistryInterface $middlewareRegistry = null
    ) {
        $this->invocationStrategy = $invocationStrategy;
        $this->routeHandlerResolver = $routeHandlerResolver;
        $this->contextExtractor = $contextExtractor ?? new RequestContextExtractor();
        $this->routeMatcher = $routeMatcher ?? new RouteMatcher($this->contextExtractor);
        $this->middlewareRegistry = $middlewareRegistry ?? new MiddlewareRegistry();
        $this->middlewarePipelineBuilder = $middlewarePipelineBuilder ?? new MiddlewarePipelineBuilder(
            $this->container,
            $this->middlewareRegistry
        );
    }

    public static function withDefaults(
        RouteCollectionInterface $routeCollection,
        RouteManagerInterface $routeManager,
        ?ContainerInterface $container = null,
        ?InvocationStrategyInterface $invocationStrategy = null,
        ?RouteHandlerResolverInterface $routeHandlerResolver = null,
        ?MiddlewareRegistryInterface $middlewareRegistry = null
    ): self {
        $strategy = $invocationStrategy ?? new DefaultInvocationStrategy($container);
        $resolver = $routeHandlerResolver ?? new RouteHandlerResolver($container);

        return new self(
            $routeCollection,
            $routeManager,
            $strategy,
            $resolver,
            $container,
            null,
            null,
            null,
            $middlewareRegistry
        );
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $this->initializeTrie();

        // Build the core request handler
        $coreHandler = function (ServerRequestInterface $req): ResponseInterface {
            try {
                return $this->processRequest($req);
            } catch (NotFoundException $e) {
                if ($this->notFoundHandler) {
                    return ($this->notFoundHandler)($req);
                }

                throw $e;
            } catch (\Throwable $e) {
                if ($this->exceptionHandler) {
                    return ($this->exceptionHandler)($e, $req);
                }

                throw $e;
            }
        };

        // Wrap with global middleware if any
        if (!empty($this->globalMiddleware)) {
            $pipeline = $this->middlewarePipelineBuilder->buildMiddlewarePipeline(
                $this->globalMiddleware,
                $coreHandler
            );

            return $pipeline($request);
        }

        return $coreHandler($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->dispatch($request);
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

    public function setExceptionHandler(callable $handler): void
    {
        $this->exceptionHandler = $handler;
    }

    public function setRouteManager(RouteManagerInterface $routeManager): void
    {
        $this->routeManager = $routeManager;
        $this->markRoutesDirty();
    }

    public function markRoutesDirty(): void
    {
        $this->routesDirty = true;
        $this->isTrieInitialized = false;

        if (method_exists($this->routeManager, 'reset')) {
            $this->routeManager->reset();
        }
    }

    public function setGlobalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = $middleware;
    }

    protected function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        $requestContext = $this->contextExtractor->extractContext($request);
        $routeInfo = $this->routeMatcher->findMatchingRoute($this->routeManager, $requestContext);

        if ($routeInfo === null) {
            $allowedMethods = $this->routeMatcher->findAllowedMethods(
                $this->routeManager,
                $this->routeCollection,
                $requestContext
            );

            if (!empty($allowedMethods)) {
                if ($this->methodNotAllowedHandler) {
                    return ($this->methodNotAllowedHandler)($request, $allowedMethods);
                }

                throw new MethodNotAllowedException($allowedMethods);
            }

            throw new NotFoundException(
                sprintf('No route found for %s %s', $requestContext['method'], $requestContext['path']),
                404
            );
        }

        return $this->invokeRouteHandler($request, $routeInfo, $requestContext);
    }

    protected function initializeTrie(): void
    {
        if ($this->isTrieInitialized && !$this->routesDirty) {
            return;
        }

        if ($this->routesDirty && method_exists($this->routeManager, 'reset')) {
            $this->routeManager->reset();
        }

        foreach ($this->routeCollection->all() as $route) {
            foreach ($route->getMethods() as $method) {
                $this->routeManager->addRoute($method, $route->getPattern(), $route);
            }
        }

        $this->isTrieInitialized = true;
        $this->routesDirty = false;
    }

    protected function invokeRouteHandler(
        ServerRequestInterface $request,
        array $routeInfo,
        array $context
    ): ResponseInterface {
        /** @var RouteInterface $route */
        [$route, $params] = $routeInfo;

        $params = $this->collectRouteParameters($route, $params, $context);
        $request = $this->addRequestAttributeParams($request, $params);

        $handler = $this->resolveHandler($route);

        $terminal = function (ServerRequestInterface $req) use ($handler, $params): ResponseInterface {
            return $this->invocationStrategy->invoke($handler, $req, $params);
        };

        $exclude = method_exists($route, 'getWithoutMiddleware') ? $route->getWithoutMiddleware() : [];

        $middlewarePipeline = $this->middlewarePipelineBuilder->buildMiddlewarePipeline(
            $route->getMiddleware(),
            $terminal,
            $exclude
        );

        return $middlewarePipeline($request);
    }

    protected function collectRouteParameters(
        RouteInterface $route,
        array $params,
        array $context
    ): array {
        $collectors = [
            'getHostParameters' => fn() => $this->collectParameters(
                $route,
                'getHostParameters',
                $context['host']
            ),
            'getPortParameters' => fn() => $this->collectParameters(
                $route,
                'getPortParameters',
                $this->contextExtractor->buildHostWithPort($context),
                $context['scheme']
            ),
            'getSchemeParameters' => fn() => $this->collectParameters(
                $route,
                'getSchemeParameters',
                $context['scheme']
            ),
        ];

        foreach ($collectors as $collector) {
            $additionalParams = $collector();

            if (!empty($additionalParams)) {
                $params = $this->mergeParameters($params, $additionalParams);
            }
        }

        return $params;
    }

    protected function collectParameters(RouteInterface $route, string $method, ...$args): array
    {
        if (!method_exists($route, $method) || $args[0] === null) {
            return [];
        }

        return $route->$method(...$args);
    }

    protected function addRequestAttributeParams(
        ServerRequestInterface $request,
        array $params
    ): ServerRequestInterface {
        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request;
    }

    protected function mergeParameters(array $params, array $additionalParams): array
    {
        foreach ($additionalParams as $key => $value) {
            if (!array_key_exists($key, $params)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    protected function resolveHandler(RouteInterface $route): callable
    {
        $handler = $route->getHandler();

        return is_callable($handler)
            ? $handler
            : $this->routeHandlerResolver->resolve($handler);
    }
}
