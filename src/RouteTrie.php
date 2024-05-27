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
    }

    public function findRoute(string $method, string $path): ?array
    {
        $cacheKey = $method . $path;
        if (isset($this->routeCache[$cacheKey])) {
            return $this->routeCache[$cacheKey];
        }

        $parts = explode('/', trim($path, '/'));
        $currentNode = $this->root[$method] ?? null;
        $params = [];

        foreach ($parts as $part) {
            $currentNode = $this->getNextNode($currentNode, $part, $params);
            if ($currentNode === null) {
                return null;
            }
        }

        return $this->cacheRoute($method, $path, $currentNode, $params);
    }

    private function getOrCreateRootNode(string $method): TrieNode
    {
        if (!isset($this->root[$method])) {
            $this->root[$method] = new TrieNode();
        }
        return $this->root[$method];
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
        return $part[0] === '{' && $part[-1] === '}';
    }

    private function getOrCreateDynamicChildNode(TrieNode $node, string $part): TrieNode
    {
        $paramName = trim($part, '{}');
        if (!isset($node->children[':'])) {
            $node->children[':'] = new TrieNode($paramName);
        }
        return $node->children[':'];
    }

    private function getOrCreateStaticChildNode(TrieNode $node, string $part): TrieNode
    {
        if (!isset($node->children[$part])) {
            $node->children[$part] = new TrieNode();
        }
        return $node->children[$part];
    }

    private function getNextNode(?TrieNode $node, string $part, array &$params): ?TrieNode
    {
        if ($node === null) {
            return null;
        }

        if (isset($node->children[$part])) {
            return $node->children[$part];
        }

        if (isset($node->children[':'])) {
            $childNode = $node->children[':'];
            $params[$childNode->paramName] = $part;
            return $childNode;
        }

        return null;
    }

    private function cacheRoute(string $method, string $path, TrieNode $node, array $params): ?array
    {
        if ($node->route) {
            $result = [$node->route, $params];
            $this->routeCache[$method . $path] = $result;
            return $result;
        }

        return null;
    }
}
