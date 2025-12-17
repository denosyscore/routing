<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class RouteGroup
{
    public function __construct(
        public string $prefix,
        public ?string $name = null,
        public array $middleware = [],
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
