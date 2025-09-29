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
     * @param bool $isDomain Whether this is a domain pattern (uses dots as separators)
     * @return string
     */
    public static function patternToRegex(string $pattern, array $constraints = [], bool $isDomain = false): string
    {
        // Determine the default constraint based on context
        $defaultConstraint = $isDomain ? '[^.]+' : '[^/]+';

        // Escape forward slashes
        $regex = preg_replace('#/#', '\\/', $pattern);

        // Escape dots if this is a domain pattern
        if ($isDomain) {
            $regex = str_replace('.', '\\.', $regex);
        }

        // Replace named parameters with regex groups, applying constraints if available
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_-]*)}/', function($matches) use ($constraints, $defaultConstraint) {
            $paramName = $matches[1];
            $constraint = $constraints[$paramName] ?? $defaultConstraint;
            
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
     * @param bool $isDomain Whether this is a domain pattern (uses dots as separators)
     * @return array
     */
    public static function extractParameters(string $pattern, string $path, array $constraints = [], bool $isDomain = false): array
    {
        $regex = self::patternToRegex($pattern, $constraints, $isDomain);

        if (preg_match($regex, $path, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        
        return [];
    }
}
