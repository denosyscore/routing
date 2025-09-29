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
    protected ?string $host = null;
    protected array $hostConstraints = [];
    protected string|int|array|null $port = null;
    protected array $portConstraints = [];
    protected string|array|null $scheme = null;

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
        if ($this->host === null) {
            return true;
        }

        if ($hostHeader === null) {
            return false;
        }

        $hostname = $hostHeader;

        if (str_contains($hostHeader, ':')) {
            $hostname = explode(':', $hostHeader)[0];
        }

        if ($this->host === $hostname) {
            return true;
        }

        $hostRegex = RegexHelper::patternToRegex($this->host, $this->hostConstraints, true);

        return (bool) preg_match($hostRegex, $hostname);
    }

    public function getHostParameters(string $hostHeader): array
    {
        if ($this->host === null) {
            return [];
        }

        $hostname = $hostHeader;
        
        if (str_contains($hostHeader, ':')) {
            $hostname = explode(':', $hostHeader)[0];
        }

        return RegexHelper::extractParameters($this->host, $hostname, $this->hostConstraints, true);
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
        // If no port constraint is set, match any port
        if ($this->port === null) {
            return true;
        }

        $actualPort = null;

        if ($hostHeader !== null && str_contains($hostHeader, ':')) {
            $parts = explode(':', $hostHeader);
            $actualPort = (int) $parts[1];
        } elseif ($scheme !== null) {
            // Use default port based on scheme
            $actualPort = $scheme === 'https' ? 443 : 80;
        }

        if (is_array($this->port)) {
            return in_array($actualPort, array_map('intval', $this->port));
        }

        if (is_string($this->port) && str_starts_with($this->port, '{') && str_ends_with($this->port, '}')) {
            $param = trim($this->port, '{}');
            $constraint = $this->portConstraints[$param] ?? '\\d+';
            $regex = '#^' . $constraint . '$#';

            return (bool) preg_match($regex, (string) $actualPort);
        }

        return (int) $this->port === $actualPort;
    }

    public function getPortParameters(string $hostHeader, ?string $scheme = null): array
    {
        if ($this->port === null || !is_string($this->port) || !str_starts_with($this->port, '{')) {
            return [];
        }

        $actualPort = null;

        if (str_contains($hostHeader, ':')) {
            $parts = explode(':', $hostHeader);
            $actualPort = $parts[1];
        } elseif ($scheme !== null) {
            // Use default port based on scheme
            $actualPort = $scheme === 'https' ? '443' : '80';
        }

        if ($actualPort === null) {
            return [];
        }

        $param = trim($this->port, '{}');

        return [$param => $actualPort];
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
        if ($this->scheme === null) {
            return true;
        }

        if ($scheme === null) {
            return false;
        }

        if (is_array($this->scheme)) {
            return in_array($scheme, $this->scheme);
        }

        if (str_starts_with($this->scheme, '{') && str_ends_with($this->scheme, '}')) {
            return true;
        }

        return $this->scheme === $scheme;
    }

    public function getSchemeParameters(string $scheme): array
    {
        if ($this->scheme === null || !is_string($this->scheme) || !str_starts_with($this->scheme, '{')) {
            return [];
        }

        $param = trim($this->scheme, '{}');
        
        return [$param => $scheme];
    }
}
