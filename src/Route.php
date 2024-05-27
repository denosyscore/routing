<?php

declare(strict_types=1);

namespace Denosys\Routing;

class Route implements RouteInterface
{
    protected string $identifier;

    public function __construct(
        protected string|array $methods,
        protected string $pattern,
        protected $handler,
        protected RouteHandlerResolverInterface $handlerResolver,
        int $identifier = 0
    ) {
        $this->identifier = '_route_' . $identifier;

        $this->methods = (array) $methods;
        if (in_array('GET', $this->methods) && !in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }

        $this->handler = $this->handlerResolver->resolve($handler);
    }

    public function getMethods(): array
    {
        return (array) $this->methods;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getHandler(): callable
    {
        return $this->handler;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function matches(string $method, string $path): bool
    {
        return in_array($method, (array) $this->methods) && preg_match($this->getPatternRegex(), $path);
    }

    public function matchesPattern(string $path): bool
    {
        return (bool) preg_match($this->getPatternRegex(), $path);
    }

    public function getParameters(string $path): array
    {
        return RegexHelper::extractParameters($this->pattern, $path);
    }

    protected function getPatternRegex(): string
    {
        return RegexHelper::patternToRegex($this->pattern);
    }
}
