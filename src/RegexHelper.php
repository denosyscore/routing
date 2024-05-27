<?php

declare(strict_types=1);

namespace Denosys\Routing;

class RegexHelper
{
    /**
     * Convert a route pattern to a regex pattern.
     *
     * @param string $pattern
     * @return string
     */
    public static function patternToRegex(string $pattern): string
    {
        // Escape forward slashes and replace named parameters with regex groups
        $regex = preg_replace('#/#', '\\/', $pattern);
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_-]*)}/', '(?P<$1>[^/]+)', $regex);
        return '#^' . $regex . '$#';
    }

    /**
     * Extract parameters from a path based on a regex pattern.
     *
     * @param string $pattern
     * @param string $path
     * @return array
     */
    public static function extractParameters(string $pattern, string $path): array
    {
        $regex = self::patternToRegex($pattern);
        if (preg_match($regex, $path, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        return [];
    }
}
