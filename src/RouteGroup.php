<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;
use Denosys\Routing\RouterInterface;

class RouteGroup implements RouteGroupInterface
{
    use HasRouteMethods;
    use HasMiddleware;

    protected array $constraints = [];

    protected ?string $namePrefix = null;

    protected ?string $namespacePrefix = null;

    protected ?string $host = null;

    protected string|int|array|null $port = null;

    protected string|array|null $scheme = null;

    protected array $hostConstraints = [];

    protected array $portConstraints = [];

    /** @var list<RouteInterface> Routes declared directly in this group and in nested groups. */
    protected array $groupRoutes = [];

    protected ?RouteGroup $parent = null;
    
    public function __construct(
        protected string $prefix,
        protected RouterInterface $router,
        protected ?ContainerInterface $container = null
    ) {
    }

    public function __invoke(Closure $callback): self
    {
        $callback($this);
        return $this;
    }

    public function addRoute(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        $pattern = '/' . trim(rtrim($this->prefix, '/') . '/' . ltrim($pattern, '/'), '/');

        $route = $this->router->addRoute($methods, $pattern, $handler);

        $this->registerRoute($route);

        foreach ($this->getMiddleware() as $middlewareItem) {
            $route->middleware($middlewareItem);
        }

        // Apply group constraints to route parameters
        foreach ($this->constraints as $param => $constraint) {
            if (str_contains($pattern, '{' . $param . '}')) {
                $route->where($param, $constraint);
            }
        }

        if (null !== $this->namePrefix) {
            $route->setNamePrefix($this->namePrefix);
        }

        if (null !== $this->host) {
            $route->setHost($this->host);

            if (!empty($this->hostConstraints)) {
                $route->setHostConstraints($this->hostConstraints);
            }
        }

        if (null !== $this->port) {
            $route->setPort($this->port);

            if (!empty($this->portConstraints)) {
                $route->setPortConstraints($this->portConstraints);
            }
        }

        if (null !== $this->scheme) {
            $route->setScheme($this->scheme);
        }

        return $route;
    }

    /**
     * Create a nested group and return it, so options chained after the call
     * (->middleware(), ->name()) apply to the nested group.
     */
    public function group(string $prefix, Closure $callback): self
    {
        $newPrefix = rtrim($this->prefix, '/') . '/' . ltrim($prefix, '/');

        $routeGroup = new self($newPrefix, $this->router, $this->container);
        $routeGroup->parent = $this;

        $routeGroup->middleware = $this->middleware;
        $routeGroup->constraints = $this->constraints;
        $routeGroup->namePrefix = $this->namePrefix;
        $routeGroup->namespacePrefix = $this->namespacePrefix;
        $routeGroup->host = $this->host;
        $routeGroup->port = $this->port;
        $routeGroup->scheme = $this->scheme;
        $routeGroup->hostConstraints = $this->hostConstraints;
        $routeGroup->portConstraints = $this->portConstraints;

        $callback($routeGroup);

        return $routeGroup;
    }

    public function name(string $name): static
    {
        $this->namePrefix = self::joinNames($this->namePrefix, $name);

        foreach ($this->groupRoutes as $route) {
            $route->prependNamePrefix($name);
        }

        return $this;
    }

    /**
     * Join name segments with a single dot, whether or not segments already end in one.
     */
    public static function joinNames(?string $prefix, string $name): string
    {
        if ($prefix === null || $prefix === '') {
            return $name;
        }

        return str_ends_with($prefix, '.') ? $prefix . $name : $prefix . '.' . $name;
    }

    public function namespace(string $namespace): static
    {
        $this->namespacePrefix = $this->namespacePrefix ? $this->namespacePrefix . '\\' . $namespace : $namespace;

        return $this;
    }

    public function host(string $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function port(string|int|array $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function scheme(string|array $scheme): static
    {
        $this->scheme = $scheme;

        return $this;
    }

    public function secure(): static
    {
        return $this->scheme('https');
    }

    public function whereHost(string $parameter, string $pattern): static
    {
        $this->hostConstraints[$parameter] = $pattern;

        return $this;
    }

    public function wherePort(string $parameter, string $pattern): static
    {
        $this->portConstraints[$parameter] = $pattern;

        return $this;
    }

    public function portIn(array $ports): static
    {
        $this->port = $ports;

        return $this;
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

    public function when(bool|Closure $condition, Closure $callback): static
    {
        $shouldExecute = is_callable($condition) ? $condition() : $condition;
        
        if ($shouldExecute) {
            $callback($this);
        }
        
        return $this;
    }

    public function unless(bool|Closure $condition, Closure $callback): static
    {
        $shouldSkip = is_callable($condition) ? $condition() : $condition;
        
        if (!$shouldSkip) {
            $callback($this);
        }
        
        return $this;
    }

    /**
     * Add middleware to every route in the group, including nested groups,
     * whether the routes were declared before or after this call.
     */
    public function middleware(string|array $middleware): static
    {
        return $this->addGroupMiddleware($middleware);
    }

    /**
     * Kept for callers that signal the end of the group callback. Group options
     * no longer depend on when they are declared, so there is nothing to do.
     */
    public function markCallbackFinished(): void
    {
    }

    /**
     * Record a route declared in this group or a nested group.
     */
    protected function registerRoute(RouteInterface $route): void
    {
        $this->groupRoutes[] = $route;
        $this->parent?->registerRoute($route);
    }

    public function addGroupMiddleware(string|array $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        foreach ($middlewares as $mw) {
            $this->middleware[] = $mw;
        }

        foreach ($this->groupRoutes as $route) {
            $route->middleware($middleware);
        }

        return $this;
    }

}
