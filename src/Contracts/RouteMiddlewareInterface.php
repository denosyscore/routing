<?php

declare(strict_types=1);

namespace Denosys\Routing\Contracts;

/**
 * Defines route middleware management.
 * Clients that handle middleware can depend on this interface.
 */
interface RouteMiddlewareInterface
{
    /**
     * Add middleware to the route.
     */
    public function middleware(string|array|object $middleware): static;

    /**
     * Get all middleware attached to this route.
     */
    public function getMiddleware(): array;

    /**
     * Check if route has specific middleware.
     */
    public function hasMiddleware(string $middlewareClass): bool;

    /**
     * Clear all middleware from the route.
     */
    public function clearMiddleware(): static;
}
