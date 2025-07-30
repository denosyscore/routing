<?php

declare(strict_types=1);

namespace Denosys\Routing\Middleware;

use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

class MiddlewareManager
{
    protected array $aliases = [];
    protected array $resolvedCache = [];
    
    public function __construct(protected ?ContainerInterface $container = null)
    {
    }

    public function alias(string $alias, string|MiddlewareInterface $middleware): void
    {
        $this->aliases[$alias] = $middleware;
    }

    public function aliasMany(array $aliases): void
    {
        foreach ($aliases as $alias => $middleware) {
            $this->alias($alias, $middleware);
        }
    }

    public function resolve(MiddlewareInterface|array|string $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_array($middleware)) {
            throw new InvalidArgumentException('Array middleware must be resolved individually');
        }

        // Parse middleware with parameters (e.g., "throttle:60,1")
        [$middlewareName, $parameters] = $this->parseMiddleware($middleware);
        
        // Create cache key for this middleware + parameters combination
        $cacheKey = $middlewareName . ':' . serialize($parameters);
        
        if (isset($this->resolvedCache[$cacheKey])) {
            return $this->resolvedCache[$cacheKey];
        }

        $resolved = $this->resolveMiddleware($middlewareName, $parameters);
        
        $this->resolvedCache[$cacheKey] = $resolved;
        
        return $resolved;
    }

    public function resolveStack(array $middlewares): array
    {
        $resolved = [];
        
        foreach ($middlewares as $middleware) {
            if (is_array($middleware)) {
                $resolved = array_merge($resolved, $this->resolveStack($middleware));
            } else {
                $resolved[] = $this->resolve($middleware);
            }
        }
        
        return $resolved;
    }

    public function createPipeline(array $middlewares): MiddlewarePipeline
    {
        return new MiddlewarePipeline($this->resolveStack($middlewares));
    }

    protected function parseMiddleware(string $middleware): array
    {
        if (!str_contains($middleware, ':')) {
            return [$middleware, []];
        }

        [$name, $paramString] = explode(':', $middleware, 2);
        $parameters = array_map('trim', explode(',', $paramString));
        
        return [$name, $parameters];
    }

    protected function resolveMiddleware(string $middlewareName, array $parameters): MiddlewareInterface
    {
        // Check aliases first
        if (isset($this->aliases[$middlewareName])) {
            $middleware = $this->aliases[$middlewareName];
            if (is_string($middleware)) {
                $middlewareName = $middleware;
            } else {
                return $middleware;
            }
        }

        // Try container resolution
        if ($this->container && $this->container->has($middlewareName)) {
            $instance = $this->container->get($middlewareName);
            if ($instance instanceof MiddlewareInterface) {
                return $this->applyParameters($instance, $parameters);
            }
        }

        // Try class instantiation
        if (class_exists($middlewareName)) {
            $instance = new $middlewareName();
            if ($instance instanceof MiddlewareInterface) {
                return $this->applyParameters($instance, $parameters);
            }
        }

        throw new InvalidArgumentException(sprintf('Unable to resolve middleware: %s', $middlewareName));
    }

    protected function applyParameters(MiddlewareInterface $middleware, array $parameters): MiddlewareInterface
    {
        if (empty($parameters)) {
            return $middleware;
        }

        // If middleware supports parameter injection, apply them
        if (method_exists($middleware, 'withParameters')) {
            return $middleware->withParameters($parameters);
        }

        // For middlewares that accept parameters in constructor, wrap them
        if (method_exists($middleware, 'setParameters')) {
            $middleware->setParameters($parameters);
        }

        return $middleware;
    }

    public function clearCache(): void
    {
        $this->resolvedCache = [];
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }
}
