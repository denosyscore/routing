<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
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
    
    // Middleware methods
    public function middleware(MiddlewareInterface|array|string $middleware, int $priority = 0): static;
    public function middlewareWhen(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static;
    public function middlewareUnless(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static;
    public function prependMiddleware(MiddlewareInterface|array|string $middleware, int $priority = 1000): static;
    public function skipMiddleware(string $middlewareClass): static;
    public function getMiddlewareStack(): iterable;
    
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
