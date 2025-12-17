<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategies;

/**
 * Default scheme matching strategy.
 * Handles single schemes, scheme arrays, and parameterized schemes.
 */
class DefaultSchemeMatchingStrategy implements SchemeMatchingStrategyInterface
{
    public function matches(string|array|null $routeScheme, ?string $requestScheme): bool
    {
        // If no scheme constraint is set, match any scheme
        if ($routeScheme === null) {
            return true;
        }

        // If route requires a scheme but request has none, no match
        if ($requestScheme === null) {
            return false;
        }

        // Array of allowed schemes
        if (is_array($routeScheme)) {
            return in_array($requestScheme, $routeScheme);
        }

        // Parameterized scheme (e.g., "{scheme}")
        if (str_starts_with($routeScheme, '{') && str_ends_with($routeScheme, '}')) {
            return true;
        }

        // Exact scheme match
        return $routeScheme === $requestScheme;
    }

    public function extractParameters(string|array|null $routeScheme, string $requestScheme): array
    {
        // Only parameterized schemes can extract parameters
        if ($routeScheme === null || !is_string($routeScheme) || !str_starts_with($routeScheme, '{')) {
            return [];
        }

        $param = trim($routeScheme, '{}');

        return [$param => $requestScheme];
    }
}
