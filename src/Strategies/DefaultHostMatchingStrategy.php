<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategies;

use Denosys\Routing\RegexHelper;

/**
 * Default host matching strategy.
 * Handles exact matches, wildcard patterns, and parameterized hosts.
 */
class DefaultHostMatchingStrategy implements HostMatchingStrategyInterface
{
    public function matches(?string $routeHost, array $constraints, ?string $requestHost): bool
    {
        // If no host constraint is set, match any host
        if ($routeHost === null) {
            return true;
        }

        // If route requires a host but request has none, no match
        if ($requestHost === null) {
            return false;
        }

        // Extract hostname from request (remove port if present)
        $hostname = $requestHost;
        if (str_contains($requestHost, ':')) {
            $hostname = explode(':', $requestHost)[0];
        }

        // Exact match
        if ($routeHost === $hostname) {
            return true;
        }

        // Pattern match using regex
        $hostRegex = RegexHelper::patternToRegex($routeHost, $constraints, true);
        return (bool) preg_match($hostRegex, $hostname);
    }

    public function extractParameters(?string $routeHost, array $constraints, string $requestHost): array
    {
        if ($routeHost === null) {
            return [];
        }

        // Extract hostname from request (remove port if present)
        $hostname = $requestHost;
        if (str_contains($requestHost, ':')) {
            $hostname = explode(':', $requestHost)[0];
        }

        return RegexHelper::extractParameters($routeHost, $hostname, $constraints, true);
    }
}
