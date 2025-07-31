<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Denosys\Routing\Middleware\MiddlewareManager;

class RouteGroup implements RouteGroupInterface
{
    use HasRouteMethods;
    use HasMiddleware;

    protected array $constraints = [];
    protected ?string $namePrefix = null;
    protected ?string $namespacePrefix = null;
    protected ?string $domain = null;
    
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
        $pattern = $this->prefix . $pattern;
        $route = $this->router->addRoute($methods, $pattern, $handler);

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

    // Constraints
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

    // Conditional registration
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