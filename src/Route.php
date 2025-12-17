<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Denosys\Routing\Strategies\PatternMatchingStrategyInterface;
use Denosys\Routing\Strategies\HostMatchingStrategyInterface;
use Denosys\Routing\Strategies\PortMatchingStrategyInterface;
use Denosys\Routing\Strategies\SchemeMatchingStrategyInterface;
use Denosys\Routing\Strategies\RegexPatternMatchingStrategy;
use Denosys\Routing\Strategies\DefaultHostMatchingStrategy;
use Denosys\Routing\Strategies\DefaultPortMatchingStrategy;
use Denosys\Routing\Strategies\DefaultSchemeMatchingStrategy;

class Route implements RouteInterface
{
    use HasMiddleware;

    protected string $identifier;
    protected ?string $name = null;
    protected ?string $namePrefix = null;
    protected array $constraints = [];
    protected ?string $host = null;
    protected array $hostConstraints = [];
    protected string|int|array|null $port = null;
    protected array $portConstraints = [];
    protected string|array|null $scheme = null;

    public function __construct(
        protected string|array $methods,
        protected string $pattern,
        protected Closure|array|string $handler,
        int $identifier = 0,
        protected ?PatternMatchingStrategyInterface $patternMatcher = null,
        protected ?HostMatchingStrategyInterface $hostMatcher = null,
        protected ?PortMatchingStrategyInterface $portMatcher = null,
        protected ?SchemeMatchingStrategyInterface $schemeMatcher = null
    ) {
        $this->identifier = '_route_' . $identifier;
        $this->methods = (array) $methods;

        if (in_array('GET', $this->methods) && !in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }

        // Initialize default strategies if not provided
        $this->patternMatcher = $patternMatcher ?? new RegexPatternMatchingStrategy();
        $this->hostMatcher = $hostMatcher ?? new DefaultHostMatchingStrategy();
        $this->portMatcher = $portMatcher ?? new DefaultPortMatchingStrategy();
        $this->schemeMatcher = $schemeMatcher ?? new DefaultSchemeMatchingStrategy();
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getHandler(): Closure|array|string
    {
        return $this->handler;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function matches(string $method, string $pattern): bool
    {
        return in_array($method, $this->methods)
            && $this->patternMatcher->matches($this->pattern, $this->constraints, $pattern);
    }

    public function matchesPattern(string $pattern): bool
    {
        return $this->patternMatcher->matches($this->pattern, $this->constraints, $pattern);
    }

    public function getParameters(string $pattern): array
    {
        return $this->patternMatcher->extractParameters($this->pattern, $this->constraints, $pattern);
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

    public function getNamePrefix(): ?string
    {
        return $this->namePrefix;
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

    public function setHost(?string $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHostConstraints(array $constraints): static
    {
        $this->hostConstraints = $constraints;

        return $this;
    }

    public function matchesHost(?string $hostHeader): bool
    {
        return $this->hostMatcher->matches($this->host, $this->hostConstraints, $hostHeader);
    }

    public function getHostParameters(string $hostHeader): array
    {
        return $this->hostMatcher->extractParameters($this->host, $this->hostConstraints, $hostHeader);
    }

    public function setPort(string|int|array|null $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getPort(): string|int|array|null
    {
        return $this->port;
    }

    public function setPortConstraints(array $constraints): static
    {
        $this->portConstraints = $constraints;

        return $this;
    }

    public function matchesPort(?string $hostHeader, ?string $scheme = null): bool
    {
        return $this->portMatcher->matches($this->port, $this->portConstraints, $hostHeader, $scheme);
    }

    public function getPortParameters(string $hostHeader, ?string $scheme = null): array
    {
        return $this->portMatcher->extractParameters($this->port, $this->portConstraints, $hostHeader, $scheme);
    }

    public function setScheme(string|array|null $scheme): static
    {
        $this->scheme = $scheme;

        return $this;
    }

    public function getScheme(): string|array|null
    {
        return $this->scheme;
    }

    public function matchesScheme(?string $scheme): bool
    {
        return $this->schemeMatcher->matches($this->scheme, $scheme);
    }

    public function getSchemeParameters(string $scheme): array
    {
        return $this->schemeMatcher->extractParameters($this->scheme, $scheme);
    }
}
