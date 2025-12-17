<?php

declare(strict_types=1);

namespace Denosys\Routing;

/**
 * Interface for middleware registry implementations.
 */
interface MiddlewareRegistryInterface
{
    /**
     * Register a middleware alias.
     */
    public function alias(string $name, string $class): static;

    /**
     * Register multiple middleware aliases at once.
     * 
     * @param array<string, string> $aliases
     */
    public function aliases(array $aliases): static;

    /**
     * Register a middleware group.
     * 
     * @param array<string> $middleware
     */
    public function group(string $name, array $middleware): static;

    /**
     * Add middleware to the beginning of an existing group.
     * 
     * @param string|array<string> $middleware
     */
    public function prependToGroup(string $name, string|array $middleware): static;

    /**
     * Add middleware to the end of an existing group.
     * 
     * @param string|array<string> $middleware
     */
    public function appendToGroup(string $name, string|array $middleware): static;

    /**
     * Check if an alias exists.
     */
    public function hasAlias(string $name): bool;

    /**
     * Check if a group exists.
     */
    public function hasGroup(string $name): bool;

    /**
     * Get the class for an alias.
     */
    public function getAlias(string $name): ?string;

    /**
     * Get the middleware array for a group.
     * 
     * @return array<string>|null
     */
    public function getGroup(string $name): ?array;

    /**
     * Resolve middleware name(s) to an array of class names.
     * 
     * @param string|array<string> $middleware
     * @return array<string>
     */
    public function resolve(string|array $middleware): array;
}

