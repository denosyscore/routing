<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategies;

/**
 * Strategy for matching route port constraints against request ports.
 */
interface PortMatchingStrategyInterface
{
    /**
     * Check if the route port constraint matches the request port.
     *
     * @param string|int|array|null $routePort The route port constraint
     * @param array $constraints Port parameter constraints
     * @param string|null $hostHeader The Host header (may include port)
     * @param string|null $scheme The request scheme (for default port detection)
     */
    public function matches(
        string|int|array|null $routePort,
        array $constraints,
        ?string $hostHeader,
        ?string $scheme
    ): bool;

    /**
     * Extract port parameters from the request.
     *
     * @param string|int|array|null $routePort The route port constraint
     * @param array $constraints Port parameter constraints
     * @param string $hostHeader The Host header
     * @param string|null $scheme The request scheme
     * @return array Extracted parameters as key-value pairs
     */
    public function extractParameters(
        string|int|array|null $routePort,
        array $constraints,
        string $hostHeader,
        ?string $scheme
    ): array;
}
