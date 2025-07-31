<?php

declare(strict_types=1);

namespace Denosys\Routing;

class RouteTrie
{
    private array $root = [];
    private Cache $cache;

    public function __construct(?string $cacheFile = null)
    {
        $this->cache = new Cache($cacheFile);
    }

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        $parts = $this->parsePath($pattern);
        $currentNode = $this->getOrCreateRootNode($method);

        foreach ($parts as $part) {
            $currentNode = $this->getOrCreateChildNode($currentNode, $part);
        }

        $currentNode->route = $route;
    }

    public function findRoute(string $method, string $path): ?array
    {
        // Check cache first
        $cacheKey = "route_{$method}_{$path}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Parse and find route
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
                    $remainingParts = array_slice($parts, $partIndex);
                    $params[$wildcardNode->paramName] = implode('/', $remainingParts);
                    $currentNode = $wildcardNode;
                    break;
                }
            }
            // Try parameter matches
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

        if ($currentNode && $currentNode->route) {
            $result = [$currentNode->route, $params];
            return $result;
        }

        return null;
    }

    public function getCacheFile(): ?string
    {
        return $this->cache->getCacheFile();
    }

    public function isCacheEnabled(): bool
    {
        return $this->cache->isEnabled();
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
            if (!isset($node->children['*'])) {
                $wildcardNode = new TrieNode();
                $wildcardNode->paramName = $paramName;
                $wildcardNode->isWildcard = true;
                $node->children['*'] = $wildcardNode;
            }
            return $node->children['*'];
        }

        $paramDetails = $this->parseParameterDetails($part);
        
        $key = $paramDetails['optional'] ? '?' : ':';
        if ($paramDetails['constraint']) {
            $key .= '_' . $paramDetails['constraint'];
        }
        
        if (!isset($node->children[$key])) {
            $paramNode = new TrieNode();
            $paramNode->paramName = $paramDetails['name'];
            $paramNode->constraint = $paramDetails['constraint'];
            $paramNode->isOptional = $paramDetails['optional'];
            $node->children[$key] = $paramNode;
        }
        
        return $node->children[$key];
    }

    private function getOrCreateStaticChildNode(TrieNode $node, string $part): TrieNode
    {
        return $node->children[$part] ??= new TrieNode();
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
        $inner = trim($part, '{}');
        
        $optional = str_ends_with($inner, '?');
        if ($optional) {
            $inner = rtrim($inner, '?');
        }
        
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
