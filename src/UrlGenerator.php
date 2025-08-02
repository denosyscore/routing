<?php

declare(strict_types=1);

namespace Denosys\Routing;

use InvalidArgumentException;
use Denosys\Routing\Exceptions\RouteNotFoundException;

class UrlGenerator implements UrlGeneratorInterface
{
    protected string $baseUrl = '';

    public function __construct(
        protected RouteCollectionInterface $routeCollection,
        string $baseUrl = ''
    ) {
        $this->setBaseUrl($baseUrl);
    }

    public function route(string $name, array $parameters = []): string
    {
        $route = $this->routeCollection->findByName($name);
        
        if (!$route) {
            throw new RouteNotFoundException($name);
        }

        $pattern = $route->getPattern();
        
        $requiredParams = $this->extractRequiredParameters($pattern);
        
        foreach ($requiredParams as $param) {
            if (!isset($parameters[$param])) {
                throw new InvalidArgumentException(
                    "Missing required parameter [{$param}] for route [{$name}]"
                );
            }
        }

        $url = $this->replaceParameters($pattern, $parameters);
        
        $url = $this->encodeUrl($url);
        
        return $this->baseUrl . $url;
    }

    public function hasRoute(string $name): bool
    {
        return $this->routeCollection->findByName($name) !== null;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function extractRequiredParameters(string $pattern): array
    {
        preg_match_all('/\{([^}?]+)\}/', $pattern, $matches);
        return $matches[1] ?? [];
    }

    protected function replaceParameters(string $pattern, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $pattern = str_replace('{' . $key . '}', (string) $value, $pattern);
            $pattern = str_replace('{' . $key . '?}', (string) $value, $pattern);
        }
        
        // Handle optional parameters that weren't provided
        $pattern = preg_replace('/\{[^}]*\?\}/', '', $pattern);
        
        return $pattern;
    }

    protected function encodeUrl(string $url): string
    {
        $segments = explode('/', $url);

        $encodedSegments = array_map(function ($segment) {
            // Don't encode empty segments (from leading/trailing slashes)
            if ($segment === '') {
                return $segment;
            }
            return rawurlencode($segment);
        }, $segments);
        
        return implode('/', $encodedSegments);
    }
}
