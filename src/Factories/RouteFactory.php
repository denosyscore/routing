<?php

declare(strict_types=1);

namespace Denosys\Routing\Factories;

use Closure;
use Denosys\Routing\Route;
use Denosys\Routing\RouteInterface;
use Denosys\Routing\Strategies\PatternMatchingStrategyInterface;
use Denosys\Routing\Strategies\HostMatchingStrategyInterface;
use Denosys\Routing\Strategies\PortMatchingStrategyInterface;
use Denosys\Routing\Strategies\SchemeMatchingStrategyInterface;
use Denosys\Routing\Strategies\RegexPatternMatchingStrategy;
use Denosys\Routing\Strategies\DefaultHostMatchingStrategy;
use Denosys\Routing\Strategies\DefaultPortMatchingStrategy;
use Denosys\Routing\Strategies\DefaultSchemeMatchingStrategy;

/**
 * Factory for creating Route instances.
 * Encapsulates route creation logic and strategy injection.
 */
class RouteFactory
{
    private static int $routeCounter = 0;

    public function __construct(
        private ?PatternMatchingStrategyInterface $patternMatcher = null,
        private ?HostMatchingStrategyInterface $hostMatcher = null,
        private ?PortMatchingStrategyInterface $portMatcher = null,
        private ?SchemeMatchingStrategyInterface $schemeMatcher = null
    ) {
        // Initialize default strategies if not provided
        $this->patternMatcher = $patternMatcher ?? new RegexPatternMatchingStrategy();
        $this->hostMatcher = $hostMatcher ?? new DefaultHostMatchingStrategy();
        $this->portMatcher = $portMatcher ?? new DefaultPortMatchingStrategy();
        $this->schemeMatcher = $schemeMatcher ?? new DefaultSchemeMatchingStrategy();
    }

    /**
     * Create a new route with the configured strategies.
     */
    public function create(
        string|array $methods,
        string $pattern,
        Closure|array|string $handler
    ): RouteInterface {
        return new Route(
            $methods,
            $pattern,
            $handler,
            self::$routeCounter++,
            $this->patternMatcher,
            $this->hostMatcher,
            $this->portMatcher,
            $this->schemeMatcher
        );
    }

    /**
     * Reset the route counter (useful for testing).
     */
    public function resetCounter(): void
    {
        self::$routeCounter = 0;
    }

    /**
     * Get the current route counter value.
     */
    public function getCounter(): int
    {
        return self::$routeCounter;
    }
}
