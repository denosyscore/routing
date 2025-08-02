<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use RuntimeException;

class RouteNotFoundException extends RuntimeException
{
    public function __construct(string $routeName)
    {
        parent::__construct("Route [{$routeName}] not found");
    }
}