<?php

declare(strict_types=1);

namespace Denosys\Routing\Contracts;

/**
 * Defines scheme matching capabilities for routes.
 * Clients that work with scheme-based routing can depend on this interface.
 */
interface SchemeMatchingInterface
{
    /**
     * Set the scheme constraint for the route.
     */
    public function setScheme(string|array|null $scheme): static;

    /**
     * Get the scheme constraint.
     */
    public function getScheme(): string|array|null;

    /**
     * Check if route matches the given scheme.
     */
    public function matchesScheme(?string $scheme): bool;

    /**
     * Extract scheme parameters from the request.
     */
    public function getSchemeParameters(string $scheme): array;
}
