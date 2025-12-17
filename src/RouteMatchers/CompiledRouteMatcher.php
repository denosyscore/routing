<?php

declare(strict_types=1);

namespace Denosys\Routing\RouteMatchers;

use Denosys\Routing\RouteInterface;
use Denosys\Routing\RouteParser\RouteParser;
use Denosys\Routing\RouteCompiler\RouteCompiler;

class CompiledRouteMatcher implements RouteMatcherInterface
{
    private array $compiledRoutes = [];
    private int $hits = 0;

    public function __construct(
        private ?RouteParser $parser = null,
        private ?RouteCompiler $compiler = null
    ) {
        $this->parser = $parser ?? new RouteParser();
        $this->compiler = $compiler ?? new RouteCompiler($this->parser);
    }

    public function canMatch(string $pattern): bool
    {
        return $this->parser->isSimpleParameterRoute($pattern);
    }

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        $compiled = $this->compiler->compileSimpleRoute($pattern, $route->getConstraints());

        $this->compiledRoutes[$method][] = [
            'pattern' => $compiled['regex'],
            'params' => $compiled['params'],
            'route' => $route
        ];
    }

    public function findRoute(string $method, string $path): ?array
    {
        if (!isset($this->compiledRoutes[$method])) {
            return null;
        }

        foreach ($this->compiledRoutes[$method] as $compiledRoute) {
            if (preg_match($compiledRoute['pattern'], $path, $matches)) {
                $this->hits++;
                $params = $this->compiler->extractParameters($matches, $compiledRoute['params']);
                return [$compiledRoute['route'], $params];
            }
        }

        return null;
    }

    public function getType(): string
    {
        return 'compiled';
    }

    public function getStats(): array
    {
        $totalRoutes = array_sum(array_map('count', $this->compiledRoutes));

        return [
            'type' => $this->getType(),
            'hits' => $this->hits,
            'routes_count' => $totalRoutes
        ];
    }

    public function resetStats(): void
    {
        $this->hits = 0;
    }

    public function findAllRoutes(string $method, string $path): array
    {
        if (!isset($this->compiledRoutes[$method])) {
            return [];
        }

        $matches = [];
        foreach ($this->compiledRoutes[$method] as $compiledRoute) {
            if (preg_match($compiledRoute['pattern'], $path, $matchResult)) {
                $this->hits++;
                $params = $this->compiler->extractParameters($matchResult, $compiledRoute['params']);
                $matches[] = [$compiledRoute['route'], $params];
            }
        }

        return $matches;
    }
}
