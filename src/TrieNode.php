<?php

declare(strict_types=1);

namespace Denosys\Routing;

class TrieNode
{
    public ?RouteInterface $route = null;

    public array $children = [];

    public function __construct(
        public ?string $paramName = null,
        public ?string $constraint = null,
        public bool $isOptional = false,
        public bool $isWildcard = false
    ) {
    }

    public function matchesConstraint(string $value): bool
    {
        if ($this->constraint === null) {
            return true;
        }

        return preg_match('/^' . $this->constraint . '$/', $value) === 1;
    }
}
