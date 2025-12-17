<?php

declare(strict_types=1);

namespace Denosys\Routing\Contracts;

/**
 * Defines host matching capabilities for routes.
 * Clients that work with host-based routing can depend on this interface.
 */
interface HostMatchingInterface
{
    /**
     * Set the host constraint for the route.
     */
    public function setHost(?string $host): static;

    /**
     * Get the host constraint.
     */
    public function getHost(): ?string;

    /**
     * Set host parameter constraints.
     */
    public function setHostConstraints(array $constraints): static;

    /**
     * Check if route matches the given host header.
     */
    public function matchesHost(?string $hostHeader): bool;

    /**
     * Extract host parameters from the request host header.
     */
    public function getHostParameters(string $hostHeader): array;
}
