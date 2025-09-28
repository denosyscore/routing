<?php

declare(strict_types=1);

namespace Denosys\Routing;

interface RouteInterface
{
    public function getMethods(): array;
    public function getPattern(): string;
    public function getHandler(): callable;
    public function getIdentifier(): string;
    public function matches(string $method, string $path): bool;
    public function matchesPattern(string $path): bool;
    public function getParameters(string $path): array;
    
    // Middleware methods
    public function middleware(string|array $middleware): static;
    public function getMiddleware(): array;
    public function hasMiddleware(string $middlewareClass): bool;
    public function clearMiddleware(): static;
    
    // Naming
    public function name(string $name): static;
    public function getName(): ?string;
    
    // Constraints
    public function where(string $parameter, string $pattern): static;
    public function whereIn(string $parameter, array $values): static;
    public function whereNumber(string $parameter): static;
    public function whereAlpha(string $parameter): static;
    public function whereAlphaNumeric(string $parameter): static;
    public function getConstraints(): array;
}
