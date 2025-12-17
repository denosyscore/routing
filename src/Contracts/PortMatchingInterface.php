<?php

declare(strict_types=1);

namespace Denosys\Routing\Contracts;

/**
 * Defines port matching capabilities for routes.
 * Clients that work with port-based routing can depend on this interface.
 */
interface PortMatchingInterface
{
    /**
     * Set the port constraint for the route.
     */
    public function setPort(string|int|array|null $port): static;

    /**
     * Get the port constraint.
     */
    public function getPort(): string|int|array|null;

    /**
     * Set port parameter constraints.
     */
    public function setPortConstraints(array $constraints): static;

    /**
     * Check if route matches the given port.
     */
    public function matchesPort(?string $hostHeader, ?string $scheme = null): bool;

    /**
     * Extract port parameters from the request.
     */
    public function getPortParameters(string $hostHeader, ?string $scheme = null): array;
}
