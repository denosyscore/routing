<?php

declare(strict_types=1);

namespace Denosys\Routing;

class TrieNode
{
    public ?RouteInterface $route = null;

    public array $children = [];

    public function __construct(public ?string $paramName = null)
    {
    }
}
