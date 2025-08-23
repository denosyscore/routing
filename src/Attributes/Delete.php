<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Delete extends HttpMethodRoute
{
    protected static array $methods = ['DELETE'];
}
