<?php

declare(strict_types=1);

namespace Denosys\Routing\HandlerResolvers;

use Closure;
use Denosys\Routing\Priority;

class CallableResolver implements HandlerResolverInterface
{
    public function canResolve(mixed $handler): bool
    {
        return $handler instanceof Closure || is_callable($handler);
    }

    public function resolve(mixed $handler): callable
    {
        return $handler;
    }

    public function getPriority(): int
    {
        return Priority::HIGHEST->value;
    }
}
