<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RouteGroup
{
    public function __construct(
        public readonly string $prefix,
        public readonly ?string $name = null,
        public readonly array $middleware = [],
    ) {}

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
