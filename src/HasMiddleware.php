<?php

declare(strict_types=1);

namespace Denosys\Routing;

trait HasMiddleware
{
    protected array $middleware = [];

    public function middleware(string|array $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middlewares as $middlewareItem) {
            $this->middleware[] = $middlewareItem;
        }

        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function hasMiddleware(string $middlewareClass): bool
    {
        return in_array($middlewareClass, $this->middleware, true);
    }

    public function clearMiddleware(): static
    {
        $this->middleware = [];
        
        return $this;
    }
}
