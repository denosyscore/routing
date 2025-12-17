<?php

declare(strict_types=1);

namespace Denosys\Routing\HandlerResolvers;

use Closure;

interface HandlerResolverInterface
{
    /**
     * Check if this resolver can handle the given handler
     *
     * @param Closure|array|string $handler The handler to check
     */
    public function canResolve(Closure|array|string $handler): bool;

    /**
     * Resolve the handler into a callable
     *
     * @param Closure|array|string $handler The handler to resolve
     * @return callable The resolved callable
     * @throws \Denosys\Routing\Exceptions\InvalidHandlerException
     */
    public function resolve(Closure|array|string $handler): callable;

    /**
     * Get the priority of this resolver (higher = checked first)
     */
    public function getPriority(): int;
}
