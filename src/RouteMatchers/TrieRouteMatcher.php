<?php

declare(strict_types=1);

namespace Denosys\Routing\RouteMatchers;

use Denosys\Routing\RouteInterface;
use Denosys\Routing\TrieNode;
use Denosys\Routing\RouteParser\RouteParser;

class TrieRouteMatcher implements RouteMatcherInterface
{
    private array $root = [];
    private int $hits = 0;
    private int $routeCount = 0;
    private RouteParser $parser;

    public function __construct(?RouteParser $parser = null)
    {
        $this->parser = $parser ?? new RouteParser();
    }

    public function canMatch(string $pattern): bool
    {
        return !$this->parser->isStaticRoute($pattern)
            && !$this->parser->isSimpleParameterRoute($pattern);
    }

    public function addRoute(string $method, string $pattern, RouteInterface $route): void
    {
        $parts = $this->parser->parsePath($pattern);
        $currentNode = $this->getOrCreateRootNode($method);

        foreach ($parts as $part) {
            $currentNode = $this->getOrCreateChildNode($currentNode, $part, $route->getConstraints());
        }

        $currentNode->routes[] = $route;

        $this->routeCount++;
    }

    public function findRoute(string $method, string $path): ?array
    {
        if (!isset($this->root[$method])) {
            return null;
        }

        $parts = $this->parser->parsePath($path);
        $result = $this->matchPath($this->root[$method], $parts);

        if ($result !== null) {
            $this->hits++;
        }

        return $result;
    }

    private function matchPath(TrieNode $node, array $parts): ?array
    {
        $currentNode = $node;
        $params = [];

        foreach ($parts as $index => $part) {
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

        if ($currentNode && !empty($currentNode->routes)) {
            $lastRoute = $currentNode->routes[count($currentNode->routes) - 1];

            return [$lastRoute, $params];
        }

        return null;
    }

    private function getOrCreateRootNode(string $method): TrieNode
    {
        return $this->root[$method] ??= new TrieNode();
    }

    private function getOrCreateChildNode(TrieNode $node, string $part, array $routeConstraints = []): TrieNode
    {
        if (!$this->parser->isDynamicPart($part)) {
            return $node->staticChildren[$part] ??= new TrieNode();
        }

        if ($this->parser->isWildcardPart($part)) {
            if ($node->wildcardNode === null) {
                $paramName = $part === '*' ? 'wildcard' : rtrim($part, '*');
                $node->wildcardNode = new TrieNode($paramName, null, false, true);
            }

            return $node->wildcardNode;
        }

        $paramDetails = $this->parser->parseParameterDetails($part);
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

    public function getType(): string
    {
        return 'trie';
    }

    public function getStats(): array
    {
        return [
            'type' => $this->getType(),
            'hits' => $this->hits,
            'routes_count' => $this->routeCount
        ];
    }

    public function resetStats(): void
    {
        $this->hits = 0;
    }

    public function findAllRoutes(string $method, string $path): array
    {
        if (!isset($this->root[$method])) {
            return [];
        }

        $parts = $this->parser->parsePath($path);
        $results = $this->matchAllPaths($this->root[$method], $parts);

        if (!empty($results)) {
            $this->hits++;
        }

        return $results;
    }

    private function matchAllPaths(TrieNode $node, array $parts): array
    {
        $currentNode = $node;
        $params = [];

        foreach ($parts as $index => $part) {
            $nextNode = $currentNode->findChild($part);

            if ($nextNode === null) {
                return [];
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

        if ($currentNode && !empty($currentNode->routes)) {
            $routes = array_reverse($currentNode->routes);
            
            return array_map(fn($route) => [$route, $params], $routes);
        }

        return [];
    }
}
