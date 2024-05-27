<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;

interface RouteCollectionInterface
{
    public function add(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface;
    public function all(): array;
    public function get(string $method, string $path): ?RouteInterface;
}
