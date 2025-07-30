<?php

declare(strict_types=1);

namespace Denosys\Routing\Middleware;

use Closure;
use Psr\Http\Server\MiddlewareInterface;

class MiddlewareItem
{
    public function __construct(
        public readonly MiddlewareInterface|string $middleware,
        public readonly int $priority = 0,
        public readonly ?Closure $condition = null,
        public readonly array $parameters = []
    ) {
    }

    public function shouldExecute(): bool
    {
        if ($this->condition === null) {
            return true;
        }

        return ($this->condition)();
    }

    public static function create(
        MiddlewareInterface|string $middleware, 
        int $priority = 0,
        ?Closure $condition = null,
        array $parameters = []
    ): self {
        return new self($middleware, $priority, $condition, $parameters);
    }

    public function withPriority(int $priority): self
    {
        return new self($this->middleware, $priority, $this->condition, $this->parameters);
    }

    public function withCondition(Closure $condition): self
    {
        return new self($this->middleware, $this->priority, $condition, $this->parameters);
    }

    public function withParameters(array $parameters): self
    {
        return new self($this->middleware, $this->priority, $this->condition, $parameters);
    }
}