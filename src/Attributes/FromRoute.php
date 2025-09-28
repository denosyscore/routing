<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class FromRoute
{
    public function __construct(public string $name) {}
}
