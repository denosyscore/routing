<?php

declare(strict_types=1);

namespace Denosys\Routing;

class RegexHelper
{
    /**
     * Convert a route pattern to a regex pattern.
     *
     * @param string $pattern
     * @param array $constraints
     * @return string
     */
    public static function patternToRegex(string $pattern, array $constraints = []): string
    {
        // Escape forward slashes
        $regex = preg_replace('#/#', '\\/', $pattern);
        
        // Replace named parameters with regex groups, applying constraints if available
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_-]*)}/', function($matches) use ($constraints) {
            $paramName = $matches[1];
            $constraint = $constraints[$paramName] ?? '[^/]+';
            return '(?P<' . $paramName . '>' . $constraint . ')';
        }, $regex);
        
        return '#^' . $regex . '$#';
    }

    /**
     * Extract parameters from a path based on a regex pattern.
     *
     * @param string $pattern
     * @param string $path
     * @param array $constraints
     * @return array
     */
    public static function extractParameters(string $pattern, string $path, array $constraints = []): array
    {
        $regex = self::patternToRegex($pattern, $constraints);
        if (preg_match($regex, $path, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        return [];
    }
}
