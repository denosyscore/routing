<?php

declare(strict_types=1);

namespace Denosys\Routing\Contracts;

/**
 * Defines route pattern matching capabilities.
 * Clients that need to match routes against requests can depend on this interface.
 */
interface RouteMatcherInterface
{
    /**
     * Check if route matches the given HTTP method and path pattern.
     */
    public function matches(string $method, string $pattern): bool;

    /**
     * Check if route pattern matches the given path (ignoring HTTP method).
     */
    public function matchesPattern(string $pattern): bool;

    /**
     * Extract route parameters from the given path.
     */
    public function getParameters(string $pattern): array;
}
