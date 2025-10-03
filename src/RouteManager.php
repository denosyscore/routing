<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Denosys\Routing\RouteMatchers\RouteMatcherInterface;
use Denosys\Routing\RouteMatchers\StaticRouteMatcher;
use Denosys\Routing\RouteMatchers\CompiledRouteMatcher;
use Denosys\Routing\RouteMatchers\TrieRouteMatcher;

class RouteManager implements RouteManagerInterface
{
    /** @var RouteMatcherInterface[] */
    private array $matchers = [];

    public function __construct()
    {
        $this->matchers = [
            new StaticRouteMatcher(),
            new CompiledRouteMatcher(),
            new TrieRouteMatcher()
        ];
    }

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->canMatch($pattern)) {
                $matcher->addRoute($method, $pattern, $route);

                return;
            }
        }

        $this->matchers[count($this->matchers) - 1]->addRoute($method, $pattern, $route);
    }

    public function findRoute(string $method, string $path): ?array
    {
        foreach ($this->matchers as $matcher) {
            $result = $matcher->findRoute($method, $path);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    public function findAllRoutes(string $method, string $path): array
    {
        $allMatches = [];

        foreach ($this->matchers as $matcher) {
            $matches = $matcher->findAllRoutes($method, $path);
            if (!empty($matches)) {
                $allMatches = array_merge($allMatches, $matches);
            }
        }

        return $allMatches;
    }

    public function getPerformanceStats(): array
    {
        $stats = [];
        $totalHits = 0;

        foreach ($this->matchers as $matcher) {
            $matcherStats = $matcher->getStats();
            $type = $matcherStats['type'];
            $hits = $matcherStats['hits'];

            $stats[$type . '_hits'] = $hits;
            $totalHits += $hits;
        }

        foreach ($this->matchers as $matcher) {
            $matcherStats = $matcher->getStats();
            $type = $matcherStats['type'];
            $hits = $matcherStats['hits'];

            $stats[$type . '_percentage'] = $totalHits > 0
                ? round(($hits / $totalHits) * 100, 2)
                : 0;
        }

        return $stats;
    }
}
