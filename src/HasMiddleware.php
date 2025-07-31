<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Http\Server\MiddlewareInterface;
use Denosys\Routing\Middleware\MiddlewareItem;
use Denosys\Routing\Middleware\MiddlewareManager;

trait HasMiddleware
{
    protected array $middlewareStack = [];
    protected ?MiddlewareManager $middlewareManager = null;

    public function middleware(MiddlewareInterface|array|string $middleware, int $priority = 0): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middlewares as $mw) {
            $this->middlewareStack[] = MiddlewareItem::create($mw, $priority);
        }

        return $this;
    }

    public function middlewareWhen(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static
    {
        $shouldExecute = is_callable($condition) ? $condition : fn() => $condition;
        $middlewares = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middlewares as $mw) {
            $this->middlewareStack[] = MiddlewareItem::create($mw, $priority, $shouldExecute);
        }

        return $this;
    }

    public function middlewareUnless(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static
    {
        $shouldSkip = is_callable($condition) ? $condition : fn() => $condition;
        $shouldExecute = fn() => !$shouldSkip();

        return $this->middlewareWhen($shouldExecute, $middleware, $priority);
    }

    public function prependMiddleware(MiddlewareInterface|array|string $middleware, int $priority = 1000): static
    {
        return $this->middleware($middleware, $priority);
    }

    public function skipMiddleware(string $middlewareClass): static
    {
        $this->middlewareStack = array_filter($this->middlewareStack, function(MiddlewareItem $item) use ($middlewareClass) {
            return $item->middleware !== $middlewareClass;
        });

        return $this;
    }

    public function getMiddlewareStack(): array
    {
        // Sort by priority (higher priority first)
        $stack = $this->middlewareStack;
        usort($stack, fn(MiddlewareItem $a, MiddlewareItem $b) => $b->priority <=> $a->priority);
        
        // Filter out conditional middleware that shouldn't execute
        return array_filter($stack, fn(MiddlewareItem $item) => $item->shouldExecute());
    }

    public function getResolvedMiddlewareStack(): array
    {
        if (!$this->middlewareManager) {
            throw new \RuntimeException('MiddlewareManager not set. Call setMiddlewareManager() first.');
        }

        $resolved = [];
        foreach ($this->getMiddlewareStack() as $item) {
            $resolved[] = $this->middlewareManager->resolve($item->middleware);
        }

        return $resolved;
    }

    public function setMiddlewareManager(MiddlewareManager $manager): static
    {
        $this->middlewareManager = $manager;
        return $this;
    }

    public function getMiddlewareManager(): ?MiddlewareManager
    {
        return $this->middlewareManager;
    }

    public function hasMiddleware(string $middlewareClass): bool
    {
        foreach ($this->middlewareStack as $item) {
            if ($item->middleware === $middlewareClass) {
                return true;
            }
        }
        return false;
    }

    public function clearMiddleware(): static
    {
        $this->middlewareStack = [];
        return $this;
    }

    public function getMiddlewareCount(): int
    {
        return count($this->getMiddlewareStack());
    }
}
