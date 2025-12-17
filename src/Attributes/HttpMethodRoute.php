<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

abstract class HttpMethodRoute extends Route
{
    protected static array $methods = [];

    public function getMethods(): array
    {
        return static::$methods;
    }
}
