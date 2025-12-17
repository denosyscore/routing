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
        private ?ContainerInterface $container = null
    ) {}

    /**
     * Build a middleware pipeline (outermost first) that ultimately invokes the route handler.
     *
     * Middleware can be:
     *  - PSR-15 MiddlewareInterface instances
     *  - callables with signature fn(ServerRequestInterface $req, callable $next): ResponseInterface
     *  - class-strings resolved via the container or direct instantiation
     */
    public function buildMiddlewarePipeline(array $middlewares, callable $terminal): callable
    {
        $next = $terminal;

        foreach (array_reverse($middlewares) as $middleware) {
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
