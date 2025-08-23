<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Put extends Route
{
    public function getMethods(): array
    {
        return ['PUT'];
    }
}
