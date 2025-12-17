<?php

declare(strict_types=1);

namespace Denosys\Routing\Contracts;

/**
 * Defines route parameter constraint capabilities.
 * Clients that apply parameter validation can depend on this interface.
 */
interface RouteConstraintInterface
{
    /**
     * Apply a regex constraint to a route parameter.
     */
    public function where(string $parameter, string $pattern): static;

    /**
     * Constrain parameter to be one of the given values.
     */
    public function whereIn(string $parameter, array $values): static;

    /**
     * Constrain parameter to be numeric.
     */
    public function whereNumber(string $parameter): static;

    /**
     * Constrain parameter to be alphabetic.
     */
    public function whereAlpha(string $parameter): static;

    /**
     * Constrain parameter to be alphanumeric.
     */
    public function whereAlphaNumeric(string $parameter): static;

    /**
     * Get all parameter constraints.
     */
    public function getConstraints(): array;
}
