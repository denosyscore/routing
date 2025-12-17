<?php

declare(strict_types=1);

namespace Denosys\Routing;

/**
 * Registry for middleware aliases and groups.
 * 
 * Allows defining named middleware aliases and groups that can be
 * referenced by name when applying middleware to routes.
 * 
 * @example
 * ```php
 * $registry = new MiddlewareRegistry();
 * 
 * // Define aliases
 * $registry->alias('auth', AuthMiddleware::class);
 * $registry->alias('throttle', ThrottleMiddleware::class);
 * 
 * // Define groups (can reference aliases or other groups)
 * $registry->group('web', ['session', 'csrf', 'cookies']);
 * $registry->group('api', ['throttle', 'auth']);
 * 
 * // Resolve to actual class names
 * $resolved = $registry->resolve('web'); // Returns expanded array
 * ```
 */
class MiddlewareRegistry implements MiddlewareRegistryInterface
{
    /**
     * Middleware aliases mapping name to class.
     * 
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Middleware groups mapping name to array of middleware.
     * 
     * @var array<string, array<string>>
     */
    protected array $groups = [];

    /**
     * Register a middleware alias.
     * 
     * @param string $name The alias name (e.g., 'auth')
     * @param string $class The middleware class name
     */
    public function alias(string $name, string $class): static
    {
        $this->aliases[$name] = $class;

        return $this;
    }

    /**
     * Register multiple middleware aliases at once.
     * 
     * @param array<string, string> $aliases Map of name => class
     */
    public function aliases(array $aliases): static
    {
        foreach ($aliases as $name => $class) {
            $this->alias($name, $class);
        }

        return $this;
    }

    /**
     * Register a middleware group.
     * 
     * Groups can contain:
     * - Alias names (resolved recursively)
     * - Other group names (resolved recursively)
     * - Direct class names
     * 
     * @param string $name The group name (e.g., 'web')
     * @param array<string> $middleware Array of middleware names/classes
     */
    public function group(string $name, array $middleware): static
    {
        $this->groups[$name] = $middleware;

        return $this;
    }

    /**
     * Add middleware to an existing group.
     * 
     * @param string $name The group name
     * @param string|array<string> $middleware Middleware to prepend
     */
    public function prependToGroup(string $name, string|array $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];

        if (!isset($this->groups[$name])) {
            $this->groups[$name] = [];
        }

        $this->groups[$name] = array_merge($middlewares, $this->groups[$name]);

        return $this;
    }

    /**
     * Append middleware to an existing group.
     * 
     * @param string $name The group name
     * @param string|array<string> $middleware Middleware to append
     */
    public function appendToGroup(string $name, string|array $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];

        if (!isset($this->groups[$name])) {
            $this->groups[$name] = [];
        }

        $this->groups[$name] = array_merge($this->groups[$name], $middlewares);

        return $this;
    }

    /**
     * Check if an alias exists.
     */
    public function hasAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Check if a group exists.
     */
    public function hasGroup(string $name): bool
    {
        return isset($this->groups[$name]);
    }

    /**
     * Get the class for an alias.
     */
    public function getAlias(string $name): ?string
    {
        return $this->aliases[$name] ?? null;
    }

    /**
     * Get the middleware array for a group.
     * 
     * @return array<string>|null
     */
    public function getGroup(string $name): ?array
    {
        return $this->groups[$name] ?? null;
    }

    /**
     * Get all registered aliases.
     * 
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Get all registered groups.
     * 
     * @return array<string, array<string>>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Resolve middleware name(s) to an array of class names.
     * 
     * Resolution order:
     * 1. If it's a group, expand to array and resolve each item
     * 2. If it's an alias, resolve to the class
     * 3. Otherwise, treat as a direct class name
     * 
     * @param string|array<string> $middleware Middleware name(s) to resolve
     * @return array<string> Resolved class names
     */
    public function resolve(string|array $middleware): array
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        $resolved = [];

        foreach ($middlewares as $item) {
            $resolved = array_merge($resolved, $this->resolveOne($item));
        }

        return $resolved;
    }

    /**
     * Resolve a single middleware name to class name(s).
     * 
     * @param string $middleware The middleware name to resolve
     * @param array<string> $seen Track seen groups to prevent infinite recursion
     * @return array<string> Resolved class names
     */
    protected function resolveOne(string $middleware, array $seen = []): array
    {
        // Check for circular reference
        if (in_array($middleware, $seen, true)) {
            return []; // Skip circular references silently
        }

        // If it's a group, expand and resolve recursively
        if ($this->hasGroup($middleware)) {
            $seen[] = $middleware;
            $resolved = [];

            foreach ($this->groups[$middleware] as $item) {
                $resolved = array_merge($resolved, $this->resolveOne($item, $seen));
            }

            return $resolved;
        }

        // If it's an alias, resolve to the class
        if ($this->hasAlias($middleware)) {
            return [$this->aliases[$middleware]];
        }

        // Otherwise, treat as a direct class name
        return [$middleware];
    }

    /**
     * Remove an alias.
     */
    public function removeAlias(string $name): static
    {
        unset($this->aliases[$name]);

        return $this;
    }

    /**
     * Remove a group.
     */
    public function removeGroup(string $name): static
    {
        unset($this->groups[$name]);

        return $this;
    }

    /**
     * Clear all aliases and groups.
     */
    public function clear(): static
    {
        $this->aliases = [];
        $this->groups = [];

        return $this;
    }
}

