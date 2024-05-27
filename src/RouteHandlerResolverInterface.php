<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;

interface RouteHandlerResolverInterface
{
    public function resolve(Closure|array|string $handler): callable;
}
