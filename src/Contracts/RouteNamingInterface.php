<?php

declare(strict_types=1);

namespace Denosys\Routing\Contracts;

/**
 * Defines route naming capabilities.
 * Clients that work with named routes can depend on this interface.
 */
interface RouteNamingInterface
{
    /**
     * Set the route name.
     */
    public function name(string $name): static;

    /**
     * Prefix the route's name and name prefix, for group names declared after the route.
     */
    public function prependNamePrefix(string $prefix): static;

    /**
     * Get the route name.
     */
    public function getName(): ?string;

    /**
     * Set the name prefix for the route.
     */
    public function setNamePrefix(?string $namePrefix): static;

    /**
     * Get the name prefix.
     */
    public function getNamePrefix(): ?string;
}
