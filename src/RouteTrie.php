<?php

declare(strict_types=1);

namespace Denosys\Routing;

class RouteTrie
{
    private array $staticRoutes = [];
    private array $compiledRoutes = [];
    private array $root = [];
    private Cache $cache;
    
    private int $staticHits = 0;
    private int $compiledHits = 0;
    private int $trieHits = 0;

    public function __construct(?string $cacheFile = null)
    {
        $this->cache = new Cache($cacheFile);
    }

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        if ($this->isStaticRoute($pattern)) {
            $this->staticRoutes[$method][$pattern] = $route;
            return;
        }
        
        if ($this->isSimpleParameterRoute($pattern)) {
            $compiled = $this->compileSimpleRoute($pattern, $route->getConstraints());
            $this->compiledRoutes[$method][] = [
                'pattern' => $compiled['regex'],
                'params' => $compiled['params'],
                'route' => $route
            ];
            return;
        }
        
        $this->addToTrie($method, $pattern, $route);
    }

    public function findRoute(string $method, string $path): ?array
    {
        // Check cache first
        $cacheKey = "route_{$method}_{$path}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $result = null;
        
        if (isset($this->staticRoutes[$method][$path])) {
            $result = [$this->staticRoutes[$method][$path], []];
            $this->staticHits++;
        }
        
        elseif (isset($this->compiledRoutes[$method])) {
            $result = $this->matchCompiledRoutes($method, $path);
            if ($result) $this->compiledHits++;
        }
        
        if ($result === null) {
            $result = $this->matchTrie($method, $path);
            if ($result) $this->trieHits++;
        }
        
        if ($result !== null) {
            $this->cache->set($cacheKey, $result);
        }
        
        return $result;
    }

    public function getCacheFile(): ?string
    {
        return $this->cache->getCacheFile();
    }

    public function isCacheEnabled(): bool
    {
        return $this->cache->isEnabled();
    }
    
    public function getPerformanceStats(): array
    {
        $total = $this->staticHits + $this->compiledHits + $this->trieHits;
        
        return [
            'static_hits' => $this->staticHits,
            'compiled_hits' => $this->compiledHits,
            'trie_hits' => $this->trieHits,
            'static_percentage' => $total > 0 ? round(($this->staticHits / $total) * 100, 2) : 0,
            'compiled_percentage' => $total > 0 ? round(($this->compiledHits / $total) * 100, 2) : 0,
            'trie_percentage' => $total > 0 ? round(($this->trieHits / $total) * 100, 2) : 0,
        ];
    }

    private function isStaticRoute(string $pattern): bool
    {
        return strpos($pattern, '{') === false && strpos($pattern, '*') === false;
    }
    
    private function isSimpleParameterRoute(string $pattern): bool
    {
        // Routes with simple {param} or {param?} patterns (no wildcards)
        return preg_match('/^[^{]*(\{[^{}*]+\??}[^{]*)+$/', $pattern) === 1 && strpos($pattern, '*') === false;
    }
    
    private function compileSimpleRoute(string $pattern, array $constraints): array
    {
        $params = [];
        $parts = explode('/', trim($pattern, '/'));
        $regexParts = [];
        
        foreach ($parts as $part) {
            if (preg_match('/\{([^}]+)\}/', $part, $matches)) {
                $param = $matches[1];
                $isOptional = str_ends_with($param, '?');
                if ($isOptional) {
                    $param = rtrim($param, '?');
                }
                $params[] = $param;
                
                if (isset($constraints[$param])) {
                    $constraint = str_replace('/', '\/', $constraints[$param]);
                    $regexParts[] = $isOptional ? "(?:\/($constraint))?" : "\/($constraint)";
                } else {
                    $regexParts[] = $isOptional ? "(?:\/([^\/]+))?" : "\/([^\/]+)";
                }
            } else {
                $regexParts[] = '\/' . preg_quote($part, '/');
            }
        }
        
        $regex = '/^' . implode('', $regexParts) . '$/';
        
        return [
            'regex' => $regex,
            'params' => $params
        ];
    }
    
    private function matchCompiledRoutes(string $method, string $path): ?array
    {
        foreach ($this->compiledRoutes[$method] as $compiledRoute) {
            if (preg_match($compiledRoute['pattern'], $path, $matches)) {
                $params = [];
                for ($i = 1; $i < count($matches); $i++) {
                    $paramIndex = $i - 1;
                    if (isset($matches[$i]) && $matches[$i] !== '' && isset($compiledRoute['params'][$paramIndex])) {
                        $params[$compiledRoute['params'][$paramIndex]] = $matches[$i];
                    }
                }
                
                return [$compiledRoute['route'], $params];
            }
        }
        
        return null;
    }

    private function addToTrie(string $method, string $pattern, RouteInterface $route): void
    {
        $parts = $this->parsePath($pattern);
        $currentNode = $this->getOrCreateRootNode($method);

        foreach ($parts as $part) {
            $currentNode = $this->getOrCreateChildNode($currentNode, $part, $route->getConstraints());
        }

        $currentNode->route = $route;
    }
    
    private function matchTrie(string $method, string $path): ?array
    {
        $parts = $this->parsePath($path);
        $currentNode = $this->root[$method] ?? null;
        $params = [];

        foreach ($parts as $index => $part) {
            if ($currentNode === null) {
                return null;
            }
            
            $nextNode = $currentNode->findChild($part);
            if ($nextNode === null) {
                return null;
            }
            
            if ($nextNode->isWildcard) {
                $remaining = array_slice($parts, $index);
                $params[$nextNode->paramName ?? 'wildcard'] = implode('/', $remaining);
                $currentNode = $nextNode;
                break;
            } elseif ($nextNode->paramName !== null) {
                $params[$nextNode->paramName] = $part;
            }
            
            $currentNode = $nextNode;
        }

        return ($currentNode && $currentNode->route) ? [$currentNode->route, $params] : null;
    }

    private function getOrCreateRootNode(string $method): TrieNode
    {
        return $this->root[$method] ??= new TrieNode();
    }

    private function getOrCreateChildNode(TrieNode $node, string $part, array $routeConstraints = []): TrieNode
    {
        if (!$this->isDynamicPart($part)) {
            return $node->staticChildren[$part] ??= new TrieNode();
        }
        
        if ($this->isWildcardPart($part)) {
            if ($node->wildcardNode === null) {
                $paramName = $part === '*' ? 'wildcard' : rtrim($part, '*');
                $node->wildcardNode = new TrieNode($paramName, null, false, true);
            }
            return $node->wildcardNode;
        }
        
        $paramDetails = $this->parseParameterDetails($part);
        $constraint = $routeConstraints[$paramDetails['name']] ?? $paramDetails['constraint'];
        
        if ($paramDetails['wildcard']) {
            if ($node->wildcardNode === null) {
                $node->wildcardNode = new TrieNode(
                    $paramDetails['name'],
                    $constraint,
                    $paramDetails['optional'],
                    true
                );
            }
            return $node->wildcardNode;
        } else {
            if ($node->parameterNode === null) {
                $node->parameterNode = new TrieNode(
                    $paramDetails['name'],
                    $constraint,
                    $paramDetails['optional'],
                    false
                );
            }
            return $node->parameterNode;
        }
    }

    private function isDynamicPart(string $part): bool
    {
        return $part[0] === '{' || $part[-1] === '*';
    }

    private function isWildcardPart(string $part): bool
    {
        return $part === '*' || str_ends_with($part, '*');
    }

    private function parsePath(string $path): array
    {
        if ($path === '' || $path === '/') {
            return [];
        }
        
        $trimmed = trim($path, '/');
        return $trimmed === '' ? [] : explode('/', $trimmed);
    }

    private function parseParameterDetails(string $part): array
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
}
