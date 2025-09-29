<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;

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

    protected array $groupRoutes = [];

    protected array $pendingMiddleware = [];

    protected bool $callbackFinished = false;

    protected ?RouteGroup $lastNestedGroup = null;
    
    public function __construct(
        protected string $prefix,
        protected Router $router,
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

        $this->groupRoutes[] = $route;

        foreach ($this->getMiddleware() as $middlewareItem) {
            $route->middleware($middlewareItem);
        }

        foreach ($this->pendingMiddleware as $middlewareItem) {
            $route->middleware($middlewareItem);
        }

        $this->pendingMiddleware = [];

        // Apply group constraints to route parameters
        foreach ($this->constraints as $param => $constraint) {
            if (str_contains($pattern, '{' . $param . '}')) {
                $route->where($param, $constraint);
            }
        }

        if ($this->namePrefix && method_exists($route, 'setNamePrefix')) {
            $route->setNamePrefix($this->namePrefix);
        }

        if ($this->host !== null && method_exists($route, 'setHost')) {
            $route->setHost($this->host);

            if (method_exists($route, 'setHostConstraints') && !empty($this->hostConstraints)) {
                $route->setHostConstraints($this->hostConstraints);
            }
        }

        if ($this->port !== null && method_exists($route, 'setPort')) {
            $route->setPort($this->port);

            if (method_exists($route, 'setPortConstraints') && !empty($this->portConstraints)) {
                $route->setPortConstraints($this->portConstraints);
            }
        }

        if ($this->scheme !== null && method_exists($route, 'setScheme')) {
            $route->setScheme($this->scheme);
        }

        return $route;
    }

    public function group(string $prefix, Closure $callback): self
    {
        $newPrefix = rtrim($this->prefix, '/') . '/' . ltrim($prefix, '/');

        $routeGroup = new self($newPrefix, $this->router, $this->container);

        $routeGroup->middleware = $this->middleware;
        $routeGroup->constraints = $this->constraints;
        $routeGroup->namePrefix = $this->namePrefix;
        $routeGroup->namespacePrefix = $this->namespacePrefix;
        $routeGroup->host = $this->host;
        $routeGroup->port = $this->port;
        $routeGroup->scheme = $this->scheme;
        $routeGroup->hostConstraints = $this->hostConstraints;
        $routeGroup->portConstraints = $this->portConstraints;

        foreach ($this->pendingMiddleware as $middlewareItem) {
            $routeGroup->middleware($middlewareItem);
        }

        $this->pendingMiddleware = [];

        $callback($routeGroup);

        $routeGroup->markCallbackFinished();

        $this->lastNestedGroup = $routeGroup;

        return $this;
    }

    public function name(string $name): static
    {
        $this->namePrefix = $this->namePrefix ? $this->namePrefix . '.' . $name : $name;

        return $this;
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

    public function middleware(string|array $middleware): static
    {
        if ($this->callbackFinished) {
            return $this->addGroupMiddleware($middleware);
        }

        if ($this->lastNestedGroup !== null) {
            $this->lastNestedGroup->addGroupMiddleware($middleware);
            $this->lastNestedGroup = null;
            
            return $this;
        }

        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        foreach ($middlewares as $mw) {
            $this->pendingMiddleware[] = $mw;
        }

        return $this;
    }

    public function markCallbackFinished(): void
    {
        $this->callbackFinished = true;
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
