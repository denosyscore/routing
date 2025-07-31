<?php

declare(strict_types=1);

namespace Denosys\Routing\Middleware;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Denosys\Routing\Cache;

class MiddlewareManager
{
    protected array $aliases = [];
    protected array $resolvedCache = [];
    protected Cache $cache;
    
    public function __construct(protected ?ContainerInterface $container = null, ?string $cacheFile = null)
    {
        $this->cache = new Cache($cacheFile);
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

        // Check cache first
        $cacheKey = "middleware_" . md5($middleware);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && $cached instanceof MiddlewareInterface) {
            return $cached;
        }

        // Parse middleware with parameters
        [$middlewareName, $parameters] = $this->parseMiddleware($middleware);
        
        // Check memory cache for resolved middleware
        $memoryKey = $middlewareName . ':' . serialize($parameters);
        if (isset($this->resolvedCache[$memoryKey])) {
            return $this->resolvedCache[$memoryKey];
        }

        $resolved = $this->resolveMiddleware($middlewareName, $parameters);
        
        // Cache the resolved middleware (only in memory for instances)
        $this->resolvedCache[$memoryKey] = $resolved;
        
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

    public function getCacheFile(): ?string
    {
        return $this->cache->getCacheFile();
    }

    public function isCacheEnabled(): bool
    {
        return $this->cache->isEnabled();
    }

    public function getAliases(): array
    {
        return $this->aliases;
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
                return $this->applyParameters($middleware, $parameters);
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

        // Apply parameters if middleware supports it
        if (method_exists($middleware, 'withParameters')) {
            return $middleware->withParameters($parameters);
        }

        if (method_exists($middleware, 'setParameters')) {
            $middleware->setParameters($parameters);
        }

        return $middleware;
    }
}
