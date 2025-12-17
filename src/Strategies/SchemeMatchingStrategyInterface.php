<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategies;

/**
 * Strategy for matching route scheme constraints against request schemes.
 */
interface SchemeMatchingStrategyInterface
{
    /**
     * Check if the route scheme constraint matches the request scheme.
     *
     * @param string|array|null $routeScheme The route scheme constraint (e.g., "https", ["http", "https"], "{scheme}")
     * @param string|null $requestScheme The request scheme
     */
    public function matches(string|array|null $routeScheme, ?string $requestScheme): bool;

    /**
     * Extract scheme parameters from the request.
     *
     * @param string|array|null $routeScheme The route scheme constraint
     * @param string $requestScheme The request scheme
     * @return array Extracted parameters as key-value pairs
     */
    public function extractParameters(string|array|null $routeScheme, string $requestScheme): array;
}
