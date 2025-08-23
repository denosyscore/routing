<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RouteMatch extends Route
{
    public function __construct(
        public readonly array $methods,
        string $path = '',
        ?string $name = null,
        array $where = [],
        array $middleware = [],
    ) {
        parent::__construct($path, $name, $where, $middleware);
    }

    public function getMethods(): array
    {
        return $this->methods;
    }
}
