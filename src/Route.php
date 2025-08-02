<?php

declare(strict_types=1);

namespace Denosys\Routing;

class Route implements RouteInterface
{
    use HasMiddleware;

    protected string $identifier;
    protected ?string $name = null;
    protected ?string $namePrefix = null;
    protected array $constraints = [];

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
        return $this->methods;
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
        return in_array($method, $this->methods) && preg_match($this->getPatternRegex(), $path);
    }

    public function matchesPattern(string $path): bool
    {
        return (bool) preg_match($this->getPatternRegex(), $path);
    }

    public function getParameters(string $path): array
    {
        return RegexHelper::extractParameters($this->pattern, $path, $this->constraints);
    }

    protected function getPatternRegex(): string
    {
        return RegexHelper::patternToRegex($this->pattern, $this->constraints);
    }

    public function name(string $name): static
    {
        if ($this->namePrefix) {
            $this->name = $this->namePrefix . '.' . $name;
        } else {
            $this->name = $name;
        }
        return $this;
    }

    public function setNamePrefix(?string $namePrefix): static
    {
        $this->namePrefix = $namePrefix;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function where(string $parameter, string $pattern): static
    {
        $this->constraints[$parameter] = $pattern;
        return $this;
    }

    public function whereIn(string $parameter, array $values): static
    {
        $pattern = '(' . implode('|', array_map('preg_quote', $values)) . ')';
        return $this->where($parameter, $pattern);
    }

    public function whereNumber(string $parameter): static
    {
        return $this->where($parameter, '\\d+');
    }

    public function whereAlpha(string $parameter): static
    {
        return $this->where($parameter, '[a-zA-Z]+');
    }

    public function whereAlphaNumeric(string $parameter): static
    {
        return $this->where($parameter, '[a-zA-Z0-9]+');
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }
}
