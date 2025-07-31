<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

class RouteGroup implements RouteGroupInterface
{
    use HasRouteMethods;
    use HasMiddleware;

    protected array $constraints = [];
    protected ?string $namePrefix = null;
    protected ?string $namespacePrefix = null;
    protected ?string $domain = null;
    protected array $groupRoutes = [];
    
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
        $pattern = rtrim($this->prefix, '/') . '/' . ltrim($pattern, '/');

        $route = $this->router->addRoute($methods, $pattern, $handler);

        $this->groupRoutes[] = $route;

        // Apply group middleware
        foreach ($this->getMiddlewareStack() as $middlewareItem) {
            $route->middleware($middlewareItem->middleware, $middlewareItem->priority);
        }

        // Apply group constraints to route parameters
        foreach ($this->constraints as $param => $constraint) {
            if (str_contains($pattern, '{' . $param . '}')) {
                $route->where($param, $constraint);
            }
        }

        // Apply naming if set
        if ($this->namePrefix) {
            // Generate a default name based on the pattern
            $routeName = $this->generateRouteName($pattern);
            $route->name($this->namePrefix . '.' . $routeName);
        }

        return $route;
    }

    public function group(string $prefix, Closure $callback): self
    {
        $newPrefix = $this->prefix . $prefix;
        
        $routeGroup = new self($newPrefix, $this->router, $this->container);
        
        // Inherit group properties
        $routeGroup->middlewareStack = $this->middlewareStack;
        $routeGroup->constraints = $this->constraints;
        $routeGroup->namePrefix = $this->namePrefix;
        $routeGroup->namespacePrefix = $this->namespacePrefix;
        $routeGroup->domain = $this->domain;

        $callback($routeGroup);

        return $this;
    }


    // Naming and namespacing
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

    public function domain(string $domain): static
    {
        $this->domain = $domain;
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

    public function middleware(MiddlewareInterface|array|string $middleware, int $priority = 0): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        foreach ($middlewares as $mw) {
            $this->middlewareStack[] = \Denosys\Routing\Middleware\MiddlewareItem::create($mw, $priority);
        }
        
        foreach ($this->groupRoutes as $route) {
            $route->middleware($middleware, $priority);
        }
        
        return $this;
    }

    public function middlewareWhen(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static
    {
        $shouldExecute = is_callable($condition) ? $condition : fn() => $condition;
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        foreach ($middlewares as $mw) {
            $this->middlewareStack[] = \Denosys\Routing\Middleware\MiddlewareItem::create($mw, $priority, $shouldExecute);
        }
        
        foreach ($this->groupRoutes as $route) {
            $route->middlewareWhen($condition, $middleware, $priority);
        }
        
        return $this;
    }

    public function middlewareUnless(bool|Closure $condition, MiddlewareInterface|array|string $middleware, int $priority = 0): static
    {
        $shouldNotExecute = is_callable($condition) ? $condition : fn() => $condition;
        $shouldExecute = fn() => !$shouldNotExecute();
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        foreach ($middlewares as $mw) {
            $this->middlewareStack[] = \Denosys\Routing\Middleware\MiddlewareItem::create($mw, $priority, $shouldExecute);
        }
        
        foreach ($this->groupRoutes as $route) {
            $route->middlewareUnless($condition, $middleware, $priority);
        }
        
        return $this;
    }

    public function prependMiddleware(MiddlewareInterface|array|string $middleware, int $priority = 1000): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        foreach ($middlewares as $mw) {
            $this->middlewareStack[] = \Denosys\Routing\Middleware\MiddlewareItem::create($mw, $priority);
        }
        
        foreach ($this->groupRoutes as $route) {
            $route->prependMiddleware($middleware, $priority);
        }
        
        return $this;
    }

    protected function generateRouteName(string $pattern): string
    {
        // Remove leading slash and parameters, convert to dot notation
        $name = trim($pattern, '/');
        $name = preg_replace('/\{[^}]+\}/', '', $name); // Remove parameters
        $name = preg_replace('/\/+/', '.', $name); // Convert slashes to dots
        $name = trim($name, '.');
        
        return $name ?: 'index';
    }
}
