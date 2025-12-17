<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategies;

/**
 * Default port matching strategy.
 * Handles specific ports, port arrays, parameterized ports, and default ports.
 */
class DefaultPortMatchingStrategy implements PortMatchingStrategyInterface
{
    public function matches(
        string|int|array|null $routePort,
        array $constraints,
        ?string $hostHeader,
        ?string $scheme
    ): bool {
        // If no port constraint is set, match any port
        if ($routePort === null) {
            return true;
        }

        if ($hostHeader === null && $scheme === null) {
            return false;
        }

        $actualPort = $this->extractActualPort($hostHeader, $scheme);

        // Array of allowed ports
        if (is_array($routePort)) {
            return in_array($actualPort, array_map('intval', $routePort));
        }

        // Parameterized port (e.g., "{port}")
        if (is_string($routePort) && str_starts_with($routePort, '{') && str_ends_with($routePort, '}')) {
            $param = trim($routePort, '{}');
            $constraint = $constraints[$param] ?? '\\d+';
            $regex = '#^' . $constraint . '$#';

            return (bool) preg_match($regex, (string) $actualPort);
        }

        // Exact port match
        return (int) $routePort === $actualPort;
    }

    public function extractParameters(
        string|int|array|null $routePort,
        array $constraints,
        string $hostHeader,
        ?string $scheme
    ): array {
        // Only parameterized ports can extract parameters
        if ($routePort === null || !is_string($routePort) || !str_starts_with($routePort, '{')) {
            return [];
        }

        $actualPort = $this->extractActualPort($hostHeader, $scheme);

        if ($actualPort === null) {
            return [];
        }

        $param = trim($routePort, '{}');

        return [$param => (string) $actualPort];
    }

    /**
     * Extract the actual port from the host header or use default port based on scheme.
     */
    protected function extractActualPort(?string $hostHeader, ?string $scheme): ?int
    {
        if ($hostHeader !== null && str_contains($hostHeader, ':')) {
            $parts = explode(':', $hostHeader);
            return (int) $parts[1];
        }

        if ($scheme !== null) {
            // Use default port based on scheme
            return $scheme === 'https' ? 443 : 80;
        }

        return null;
    }
}
