<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

abstract class Route
{
    public function __construct(
        public readonly string $path = '',
        public readonly ?string $name = null,
        public readonly array $where = [],
        public readonly array $middleware = [],
    ) {}

    abstract public function getMethods(): array;

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getWhere(): array
    {
        return $this->where;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
