<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Denosys\Routing\RouteParser\RouteParser;

class RegexHelper
{
    /**
     * Convert a route pattern to a regex pattern.
     *
     * @param string $pattern
     * @param array $constraints
     * @param bool $isDomain Whether this is a domain pattern (uses dots as separators)
     * 
     * @return string
     */
    public static function patternToRegex(string $pattern, array $constraints = [], bool $isDomain = false): string
    {
        $separator = $isDomain ? '.' : '/';
        $separatorPattern = $isDomain ? '\.' : '\/';
        $defaultConstraint = $isDomain ? '[^.]+' : '[^/]+';
        $parser = new RouteParser();

        if ($pattern === '') {
            return '#^$#';
        }

        if ($pattern === $separator) {
            return '#^' . ($isDomain ? '' : '\/') . '$#';
        }

        $hasLeadingSeparator = str_starts_with($pattern, $separator);
        $stripped = ltrim($pattern, $separator);
        $segments = explode($separator, $stripped);
        $regex = '#^';

        foreach ($segments as $index => $segment) {
            $prefix = $index === 0
                ? ($hasLeadingSeparator && !$isDomain ? $separatorPattern : '')
                : $separatorPattern;

            // Dynamic parameter segment
            if ($parser->isDynamicPart($segment)) {
                $details = $parser->parseParameterDetails($segment);
                $name = $details['name'] ?: 'param' . $index;
                $constraint = $constraints[$name] ?? $details['constraint'] ?? $defaultConstraint;

                if ($details['wildcard']) {
                    $capture = '(?P<' . $name . '>.+)';
                } else {
                    $capture = '(?P<' . $name . '>' . $constraint . ')';
                }

                $partRegex = $prefix . $capture;

                if ($details['optional']) {
                    $partRegex = '(?:' . $partRegex . ')?';
                }

                $regex .= $partRegex;
                continue;
            }

            // Static segment; escape to avoid regex meta leaking
            $regex .= $prefix . preg_quote($segment, '#');
        }

        $regex .= '$#';

        return $regex;
    }

    /**
     * Extract parameters from a path based on a regex pattern.
     *
     * @param string $pattern
     * @param string $path
     * @param array $constraints
     * @param bool $isDomain Whether this is a domain pattern (uses dots as separators)
     * 
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
