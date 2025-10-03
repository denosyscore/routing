<?php

declare(strict_types=1);

namespace Denosys\Routing;

interface RouteManagerInterface
{
    public function addRoute(string $method, string $pattern, RouteInterface $route): void;

    public function findRoute(string $method, string $path): ?array;

    public function findAllRoutes(string $method, string $path): array;
}
