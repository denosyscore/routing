<?php

declare(strict_types=1);

namespace Denosys\Routing\RouteParser;

class RouteParser
{
    /**
     * Parse a path into segments
     */
    public function parsePath(string $path): array
    {
        if ($path === '' || $path === '/') {
            return [];
        }

        $trimmed = trim($path, '/');

        return $trimmed === '' ? [] : explode('/', $trimmed);
    }

    /**
     * Parse parameter details from a route segment
     *
     * @return array{name: string, constraint: ?string, optional: bool, wildcard: bool}
     */
    public function parseParameterDetails(string $part): array
    {
        $inner = trim($part, '{}');

        $optional = str_ends_with($inner, '?');

        if ($optional) {
            $inner = rtrim($inner, '?');
        }

        $wildcard = str_ends_with($inner, '*');

        if ($wildcard) {
            $inner = rtrim($inner, '*');
        }

        $constraint = null;
        $name = $inner;

        if (str_contains($inner, ':')) {
            [$name, $constraint] = explode(':', $inner, 2);
        }

        return [
            'name' => $name,
            'constraint' => $constraint,
            'optional' => $optional,
            'wildcard' => $wildcard
        ];
    }

    /**
     * Check if a path segment is dynamic (contains parameters)
     */
    public function isDynamicPart(string $part): bool
    {
        return !empty($part) && ($part[0] === '{' || $part[strlen($part) - 1] === '*');
    }

    /**
     * Check if a path segment is a wildcard
     */
    public function isWildcardPart(string $part): bool
    {
        return $part === '*' || str_ends_with($part, '*');
    }

    /**
     * Check if a route pattern is static (no parameters)
     */
    public function isStaticRoute(string $pattern): bool
    {
        return !str_contains($pattern, '{') && !str_contains($pattern, '*');
    }

    /**
     * Check if a route pattern has simple parameters (no wildcards)
     */
    public function isSimpleParameterRoute(string $pattern): bool
    {
        return preg_match('/^[^{]*(\{[^{}*]+\??}[^{]*)+$/', $pattern) === 1
            && !str_contains($pattern, '*');
    }

    /**
     * Extract parameter names from a route pattern
     */
    public function extractParameterNames(string $pattern): array
    {
        preg_match_all('/\{([^}]+)}/', $pattern, $matches);

        return array_map(function ($param) {
            // Remove optional marker and constraint if present
            $param = rtrim($param, '?*');

            if (str_contains($param, ':')) {
                [$name] = explode(':', $param, 2);

                return $name;
            }

            return $param;
        }, $matches[1]);
    }
}
