<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Extracts HTTP request context for route matching.
 * Handles host, port, scheme, method, and path extraction.
 */
class RequestContextExtractor
{
    /**
     * Extract request context including method, path, host, port, and scheme.
     */
    public function extractContext(ServerRequestInterface $request): array
    {
        $uri = $request->getUri();
        $hostHeader = $this->extractHostFromHeader($request);

        return [
            'method' => $request->getMethod(),
            'path' => $this->normalizePath($uri->getPath()),
            'host' => $hostHeader['host'],
            'port' => $hostHeader['port'] ?? $uri->getPort(),
            'scheme' => $uri->getScheme(),
        ];
    }

    /**
     * Extract host and port from HTTP Host header.
     */
    public function extractHostFromHeader(ServerRequestInterface $request): array
    {
        $hostHeaders = $request->getHeader('Host');

        if (empty($hostHeaders)) {
            return ['host' => null, 'port' => null];
        }

        $hostHeader = $hostHeaders[0];
        $parts = explode(':', $hostHeader, 2);

        return [
            'host' => $parts[0],
            'port' => isset($parts[1]) ? (int)$parts[1] : null
        ];
    }

    /**
     * Normalize URL path (empty becomes /, remove trailing slashes except root).
     */
    public function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            return rtrim($path, '/');
        }

        return $path;
    }

    /**
     * Build host with port for matching (omit standard ports).
     */
    public function buildHostWithPort(array $context): ?string
    {
        if ($context['host'] === null) {
            return null;
        }

        if ($context['port'] === null || $this->isStandardPort($context['port'], $context['scheme'])) {
            return $context['host'];
        }

        return $context['host'] . ':' . $context['port'];
    }

    /**
     * Check if port is standard for the scheme (80 for http, 443 for https).
     */
    public function isStandardPort(?int $port, string $scheme): bool
    {
        if ($port === null) {
            return true;
        }

        return match ($scheme) {
            'https' => $port === 443,
            'http' => $port === 80,
            default => false,
        };
    }
}
