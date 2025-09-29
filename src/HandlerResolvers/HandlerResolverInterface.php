<?php

declare(strict_types=1);

namespace Denosys\Routing\HandlerResolvers;

interface HandlerResolverInterface
{
    /**
     * Check if this resolver can handle the given handler
     *
     * @param mixed $handler The handler to check
     */
    public function canResolve(mixed $handler): bool;

    /**
     * Resolve the handler into a callable
     *
     * @param mixed $handler The handler to resolve
     * @return callable The resolved callable
     * @throws \Denosys\Routing\Exceptions\InvalidHandlerException
     */
    public function resolve(mixed $handler): callable;

    /**
     * Get the priority of this resolver (higher = checked first)
     */
    public function getPriority(): int;
}
