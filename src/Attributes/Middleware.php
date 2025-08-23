<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Middleware
{
    public function __construct(
        public readonly string|array $middleware,
        public readonly array $only = [],
        public readonly array $except = [],
    ) {}

    public function getMiddleware(): array
    {
        return is_array($this->middleware) ? $this->middleware : [$this->middleware];
    }

    public function getOnly(): array
    {
        return $this->only;
    }

    public function getExcept(): array
    {
        return $this->except;
    }
}
