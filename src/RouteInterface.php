<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Psr\Http\Server\MiddlewareInterface;

interface RouteInterface
{
    public function getMethods(): array;
    public function getPattern(): string;
    public function getHandler(): callable;
    public function getIdentifier(): string;
    public function matches(string $method, string $path): bool;
    public function matchesPattern(string $path): bool;
    public function getParameters(string $path): array;
    public function middleware(MiddlewareInterface|array|string $middleware): static;
    public function getMiddlewareStack(): iterable;
}
