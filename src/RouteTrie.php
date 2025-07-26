<?php

declare(strict_types=1);

namespace Denosys\Routing;

class RouteTrie
{
    private array $root = [];
    private array $routeCache = [];

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        $parts = explode('/', trim($pattern, '/'));
        $currentNode = $this->getOrCreateRootNode($method);

        foreach ($parts as $part) {
            $currentNode = $this->getOrCreateChildNode($currentNode, $part);
        }

        $currentNode->route = $route;
        $this->cacheRoutePattern($method, $pattern, $route);
    }

    public function findRoute(string $method, string $path): ?array
    {
        $cacheKey = $this->getCacheKey($method, $path);
        if (isset($this->routeCache[$cacheKey])) {
            return $this->routeCache[$cacheKey];
        }

        $parts = explode('/', trim($path, '/'));
        $currentNode = $this->root[$method] ?? null;
        $params = [];

        foreach ($parts as $part) {
            if ($currentNode === null) {
                return null;
            }

            if (isset($currentNode->children[$part])) {
                $currentNode = $currentNode->children[$part];
            } elseif (isset($currentNode->children[':'])) {
                $childNode = $currentNode->children[':'];
                $params[$childNode->paramName] = $part;
                $currentNode = $childNode;
            } else {
                return null;
            }
        }

        return $this->cacheRoute($method, $path, $currentNode, $params);
    }

    private function getOrCreateRootNode(string $method): TrieNode
    {
        return $this->root[$method] ??= new TrieNode();
    }

    private function getOrCreateChildNode(TrieNode $node, string $part): TrieNode
    {
        if ($this->isDynamicPart($part)) {
            return $this->getOrCreateDynamicChildNode($node, $part);
        }
        return $this->getOrCreateStaticChildNode($node, $part);
    }

    private function isDynamicPart(string $part): bool
    {
        return strpos($part, '{') === 0 && strrpos($part, '}') === strlen($part) - 1;
    }

    private function getOrCreateDynamicChildNode(TrieNode $node, string $part): TrieNode
    {
        $paramName = trim($part, '{}');

        return $node->children[':'] ??= new TrieNode($paramName);
    }

    private function getOrCreateStaticChildNode(TrieNode $node, string $part): TrieNode
    {
        return $node->children[$part] ??= new TrieNode();
    }

    private function cacheRoute(string $method, string $path, TrieNode $node, array $params): ?array
    {
        if ($node->route) {
            $result = [$node->route, $params];
            $this->routeCache[$this->getCacheKey($method, $path)] = $result;
            return $result;
        }

        return null;
    }

    private function cacheRoutePattern(string $method, string $pattern, RouteInterface $route): void
    {
        $this->routeCache[$this->getCacheKey($method, $pattern)] = [$route, []];
    }

    private function getCacheKey(string $method, string $path): string
    {
        return $method . $path;
    }
}
