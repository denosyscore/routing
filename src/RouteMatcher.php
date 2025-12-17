<?php

declare(strict_types=1);

namespace Denosys\Routing;

/**
 * Handles route matching logic.
 * Finds matching routes based on HTTP context (method, path, host, port, scheme).
 */
class RouteMatcher
{
    public function __construct(
        private RequestContextExtractor $contextExtractor
    ) {}

    /**
     * Find the first matching route for the given context.
     */
    public function findMatchingRoute(
        RouteManagerInterface $routeManager,
        array $context
    ): ?array {
        $allRoutes = $routeManager->findAllRoutes($context['method'], $context['path']);

        if (empty($allRoutes)) {
            $routeInfo = $routeManager->findRoute($context['method'], $context['path']);

            if ($routeInfo === null || !$this->isRouteMatching($routeInfo[0], $context)) {
                return null;
            }

            return $routeInfo;
        }

        foreach ($allRoutes as $routeInfo) {
            if ($this->isRouteMatching($routeInfo[0], $context)) {
                return $routeInfo;
            }
        }

        return null;
    }

    /**
     * Check if a route matches the given context (host, port, scheme).
     */
    public function isRouteMatching(RouteInterface $route, array $context): bool
    {
        $matchers = [
            'matchesHost' => fn() => $this->matchesCondition($route, 'matchesHost', $context['host']),
            'matchesPort' => fn() => $this->matchesCondition(
                $route,
                'matchesPort',
                $this->contextExtractor->buildHostWithPort($context),
                $context['scheme']
            ),
            'matchesScheme' => fn() => $this->matchesCondition($route, 'matchesScheme', $context['scheme']),
        ];

        foreach ($matchers as $matcher) {
            if (!$matcher()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find all HTTP methods allowed for the given path (excluding the current method).
     */
    public function findAllowedMethods(
        RouteManagerInterface $routeManager,
        RouteCollectionInterface $routeCollection,
        array $context
    ): array {
        $allowed = [];

        foreach ($this->getKnownHttpMethods($routeCollection) as $method) {
            if ($method === $context['method']) {
                continue;
            }

            $routeInfos = $routeManager->findAllRoutes($method, $context['path']);

            foreach ($routeInfos as $routeInfo) {
                [$route] = $routeInfo;

                if ($this->isRouteMatching($route, $context)) {
                    $allowed[$method] = true;
                    break;
                }
            }
        }

        return array_keys($allowed);
    }

    /**
     * Check if route matches a specific condition method.
     * If the route doesn't implement the condition method, it matches by default.
     */
    protected function matchesCondition(RouteInterface $route, string $method, ...$args): bool
    {
        if (!method_exists($route, $method)) {
            return true;
        }

        return $route->$method(...$args);
    }

    /**
     * Get all known HTTP methods from route collection.
     */
    protected function getKnownHttpMethods(RouteCollectionInterface $routeCollection): array
    {
        $methods = [];

        foreach ($routeCollection->all() as $route) {
            foreach ($route->getMethods() as $method) {
                $methods[$method] = true;
            }
        }

        return array_keys($methods);
    }
}
