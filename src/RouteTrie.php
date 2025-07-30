<?php

declare(strict_types=1);

namespace Denosys\Routing;

class RouteTrie
{
    private array $root = [];
    private array $routeCache = [];

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        $parts = $this->parsePath($pattern);
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

        $parts = $this->parsePath($path);
        $currentNode = $this->root[$method] ?? null;
        $params = [];
        $partIndex = 0;

        while ($partIndex < count($parts) && $currentNode !== null) {
            $part = $parts[$partIndex];
            $matched = false;

            // Try exact match first
            if (isset($currentNode->children[$part])) {
                $currentNode = $currentNode->children[$part];
                $matched = true;
            }
            // Try wildcard match
            elseif (isset($currentNode->children['*'])) {
                $wildcardNode = $currentNode->children['*'];
                if ($wildcardNode->isWildcard) {
                    // Wildcard matches remaining path segments
                    $remainingParts = array_slice($parts, $partIndex);
                    $params[$wildcardNode->paramName] = implode('/', $remainingParts);
                    $currentNode = $wildcardNode;
                    break;
                }
            }
            // Try parameter matches (prioritize constrained routes)
            else {
                $constrainedNodes = [];
                $unconstrainedNodes = [];
                
                foreach ($currentNode->children as $key => $childNode) {
                    if (str_starts_with($key, ':') || str_starts_with($key, '?')) {
                        if ($childNode->constraint !== null) {
                            $constrainedNodes[] = $childNode;
                        } else {
                            $unconstrainedNodes[] = $childNode;
                        }
                    }
                }
                
                // Try constrained nodes first
                foreach ($constrainedNodes as $childNode) {
                    if ($childNode->matchesConstraint($part)) {
                        $params[$childNode->paramName] = $part;
                        $currentNode = $childNode;
                        $matched = true;
                        break;
                    }
                }
                
                // Fall back to unconstrained nodes
                if (!$matched) {
                    foreach ($unconstrainedNodes as $childNode) {
                        $params[$childNode->paramName] = $part;
                        $currentNode = $childNode;
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                return null;
            }

            $partIndex++;
        }

        // Handle optional parameters at the end
        while ($currentNode && !$currentNode->route && $this->hasOptionalChild($currentNode)) {
            $currentNode = $this->getOptionalChild($currentNode);
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
        return $this->isWildcardPart($part) || preg_match('/^{[^}]+}$/', $part) === 1;
    }

    private function isWildcardPart(string $part): bool
    {
        return $part === '*' || str_ends_with($part, '*');
    }

    private function getOrCreateDynamicChildNode(TrieNode $node, string $part): TrieNode
    {
        if ($this->isWildcardPart($part)) {
            $paramName = $part === '*' ? 'wildcard' : rtrim($part, '*');
            return $node->children['*'] ??= new TrieNode($paramName, null, false, true);
        }

        $paramDetails = $this->parseParameterDetails($part);
        
        // Create unique key for different constraints/optional combinations
        $key = $paramDetails['optional'] ? '?' : ':';
        if ($paramDetails['constraint']) {
            $key .= '_' . $paramDetails['constraint'];
        }
        
        return $node->children[$key] ??= new TrieNode(
            $paramDetails['name'],
            $paramDetails['constraint'],
            $paramDetails['optional']
        );
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
        $pathLength = strlen($path);
        
        // Use hash for long paths to save memory
        if ($pathLength > 50) {
            return $method . ':' . hash('xxh3', $path);
        }
        
        return $method . ':' . $path;
    }

    private function parsePath(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return [];
        }
        
        return preg_split('/\//', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
    }

    private function parseParameterDetails(string $part): array
    {
        // Remove braces
        $inner = trim($part, '{}');
        
        // Check for optional parameter
        $optional = str_ends_with($inner, '?');
        if ($optional) {
            $inner = rtrim($inner, '?');
        }
        
        // Parse constraint
        $constraint = null;
        $name = $inner;
        
        if (str_contains($inner, ':')) {
            [$name, $constraint] = explode(':', $inner, 2);
        }
        
        return [
            'name' => $name,
            'constraint' => $constraint,
            'optional' => $optional
        ];
    }


    private function hasOptionalChild(TrieNode $node): bool
    {
        return isset($node->children['?']) && $node->children['?']->isOptional;
    }

    private function getOptionalChild(TrieNode $node): ?TrieNode
    {
        return $node->children['?'] ?? null;
    }
}
