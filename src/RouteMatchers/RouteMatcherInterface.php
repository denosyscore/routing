<?php

declare(strict_types=1);

namespace Denosys\Routing\RouteMatchers;

use Denosys\Routing\RouteInterface;

interface RouteMatcherInterface
{
    /**
     * Check if this matcher can handle the given pattern
     */
    public function canMatch(string $pattern): bool;

    /**
     * Add a route to this matcher
     */
    public function addRoute(string $method, string $pattern, RouteInterface $route): void;

    /**
     * Find a matching route for the given method and path
     *
     * @return array{0: RouteInterface, 1: array<string, string>}|null Returns [route, params] or null
     */
    public function findRoute(string $method, string $path): ?array;

    /**
     * Find all matching routes for the given method and path
     *
     * @return array<array{0: RouteInterface, 1: array<string, string>}> Returns array of [route, params]
     */
    public function findAllRoutes(string $method, string $path): array;

    /**
     * Get the type identifier for this matcher
     */
    public function getType(): string;

    /**
     * Get performance statistics for this matcher
     */
    public function getStats(): array;

    /**
     * Reset performance statistics
     */
    public function resetStats(): void;
}
