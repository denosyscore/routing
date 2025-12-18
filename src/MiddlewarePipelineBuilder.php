<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Builds middleware pipeline for route execution.
 * Handles middleware resolution and PSR-15 integration.
 */
class MiddlewarePipelineBuilder
{
    public function __construct(
        private ?ContainerInterface $container = null,
        private ?MiddlewareRegistryInterface $middlewareRegistry = null
    ) {}

    /**
     * Build a middleware pipeline (outermost first) that ultimately invokes the route handler.
     *
     * Middleware can be:
     *  - PSR-15 MiddlewareInterface instances
     *  - callables with signature fn(ServerRequestInterface $req, callable $next): ResponseInterface
     *  - Named middleware aliases or groups (resolved via registry)
     *  - class-strings resolved via the container or direct instantiation
     *
     * @param array $middlewares The middleware to include in the pipeline
     * @param callable $terminal The final handler to invoke
     * @param array $exclude Middleware to exclude from the pipeline (aliases or class names)
     */
    public function buildMiddlewarePipeline(array $middlewares, callable $terminal, array $exclude = []): callable
    {
        // First, expand any named groups/aliases through the registry
        $expandedMiddlewares = $this->expandMiddleware($middlewares);

        // Expand excluded middleware as well (resolve aliases to class names)
        $expandedExclude = $this->expandMiddleware($exclude);

        // Filter out excluded middleware
        $filteredMiddlewares = $this->filterExcluded($expandedMiddlewares, $expandedExclude);

        $next = $terminal;

        foreach (array_reverse($filteredMiddlewares) as $middleware) {
            $resolved = $this->resolveMiddleware($middleware);

            $next = function (ServerRequestInterface $request) use ($resolved, $next): ResponseInterface {
                if ($resolved instanceof MiddlewareInterface) {
                    return $resolved->process($request, $this->asRequestHandler($next));
                }

                return $resolved($request, $next);
            };
        }

        return $next;
    }

    /**
     * Filter out excluded middleware from the list.
     */
    protected function filterExcluded(array $middlewares, array $exclude): array
    {
        if (empty($exclude)) {
            return $middlewares;
        }

        return array_values(array_filter($middlewares, function ($middleware) use ($exclude) {
            return !in_array($middleware, $exclude, true);
        }));
    }

    /**
     * Expand middleware names through the registry.
     * 
     * @param array<mixed> $middlewares
     * @return array<mixed>
     */
    protected function expandMiddleware(array $middlewares): array
    {
        if ($this->middlewareRegistry === null) {
            return $middlewares;
        }

        $expanded = [];

        foreach ($middlewares as $middleware) {
            // Only expand string middleware (not objects or callables)
            if (is_string($middleware)) {
                $resolved = $this->middlewareRegistry->resolve($middleware);
                $expanded = array_merge($expanded, $resolved);
            } else {
                $expanded[] = $middleware;
            }
        }

        return $expanded;
    }

    /**
     * Resolve middleware to callable or MiddlewareInterface instance.
     * Unresolvable middleware is treated as metadata-only (no-op).
     */
    protected function resolveMiddleware(mixed $middleware): callable|MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface || is_callable($middleware)) {
            return $middleware;
        }

        if (is_string($middleware) && class_exists($middleware)) {
            $instance = null;

            if ($this->container && $this->container->has($middleware)) {
                $instance = $this->container->get($middleware);
            } else {
                $instance = new $middleware();
            }

            if ($instance instanceof MiddlewareInterface || is_callable($instance)) {
                return $instance;
            }
        }

        // Unresolvable middleware is treated as metadata-only; no-op wrapper preserves pipeline execution
        return static function (ServerRequestInterface $request, callable $next): ResponseInterface {
            return $next($request);
        };
    }

    /**
     * Wrap a callable in a PSR-15 RequestHandlerInterface.
     */
    protected function asRequestHandler(callable $next): RequestHandlerInterface
    {
        return new class($next) implements RequestHandlerInterface {
            public function __construct(private $next) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->next)($request);
            }
        };
    }
}
