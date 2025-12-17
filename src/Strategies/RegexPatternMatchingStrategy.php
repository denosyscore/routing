<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategies;

use Denosys\Routing\RegexHelper;

/**
 * Default pattern matching strategy using regex.
 * Delegates to RegexHelper for pattern compilation and matching.
 */
class RegexPatternMatchingStrategy implements PatternMatchingStrategyInterface
{
    public function matches(string $routePattern, array $constraints, string $requestPath): bool
    {
        $regex = RegexHelper::patternToRegex($routePattern, $constraints);
        return (bool) preg_match($regex, $requestPath);
    }

    public function extractParameters(string $routePattern, array $constraints, string $requestPath): array
    {
        return RegexHelper::extractParameters($routePattern, $requestPath, $constraints);
    }
}
