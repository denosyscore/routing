<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Options extends HttpMethodRoute
{
    protected static array $methods = ['OPTIONS'];
}
