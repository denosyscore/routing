<?php

declare(strict_types=1);

namespace Denosys\Routing\Contracts;

use Closure;

/**
 * Defines basic route information.
 * Clients that only need route metadata can depend on this interface.
 */
interface RouteInfoInterface
{
    /**
     * Get the HTTP methods this route responds to.
     */
    public function getMethods(): array;

    /**
     * Get the route pattern (e.g., "/users/{id}").
     */
    public function getPattern(): string;

    /**
     * Get the route handler (closure, array, or string).
     */
    public function getHandler(): Closure|array|string;

    /**
     * Get the unique route identifier.
     */
    public function getIdentifier(): string;
}
