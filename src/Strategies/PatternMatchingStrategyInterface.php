<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategies;

/**
 * Strategy for matching route patterns against request paths.
 */
interface PatternMatchingStrategyInterface
{
    /**
     * Check if the route pattern matches the request path.
     *
     * @param string $routePattern The route pattern (e.g., "/users/{id}")
     * @param array $constraints Parameter constraints
     * @param string $requestPath The request path to match
     */
    public function matches(string $routePattern, array $constraints, string $requestPath): bool;

    /**
     * Extract parameters from the request path based on the route pattern.
     *
     * @param string $routePattern The route pattern
     * @param array $constraints Parameter constraints
     * @param string $requestPath The request path
     * @return array Extracted parameters as key-value pairs
     */
    public function extractParameters(string $routePattern, array $constraints, string $requestPath): array;
}
