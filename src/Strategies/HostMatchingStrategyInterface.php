<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategies;

/**
 * Strategy for matching route host patterns against request hosts.
 */
interface HostMatchingStrategyInterface
{
    /**
     * Check if the route host pattern matches the request host.
     *
     * @param string|null $routeHost The route host pattern (e.g., "{subdomain}.example.com")
     * @param array $constraints Host parameter constraints
     * @param string|null $requestHost The request host to match (without port)
     */
    public function matches(?string $routeHost, array $constraints, ?string $requestHost): bool;

    /**
     * Extract parameters from the request host based on the route host pattern.
     *
     * @param string|null $routeHost The route host pattern
     * @param array $constraints Host parameter constraints
     * @param string $requestHost The request host (without port)
     * @return array Extracted parameters as key-value pairs
     */
    public function extractParameters(?string $routeHost, array $constraints, string $requestHost): array;
}
