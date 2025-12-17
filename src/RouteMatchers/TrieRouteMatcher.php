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

    public function __construct(private ?RouteParser $parser = null)
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
        $matches = $this->doMatch($method, $path);

        return $matches[0] ?? null;
    }

    public function findAllRoutes(string $method, string $path): array
    {
        return $this->doMatch($method, $path, true);
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

    private function doMatch(string $method, string $path, bool $findAll = false): array
    {
        if (!isset($this->root[$method])) {
            return [];
        }

        $parts = $this->parser->parsePath($path);
        $result = $this->traversePath($this->root[$method], $parts);

        if ($result === null) {
            return [];
        }

        [$currentNode, $params] = $result;

        if (!$currentNode || empty($currentNode->routes)) {
            return [];
        }

        $this->hits++;

        if ($findAll) {
            $routes = array_reverse($currentNode->routes);

            return array_map(fn($route) => [$route, $params], $routes);
        }

        $lastRoute = $currentNode->routes[count($currentNode->routes) - 1];

        return [$lastRoute, $params];
    }

    private function traversePath(TrieNode $node, array $parts): ?array
    {
        $currentNode = $node;
        $params = [];

        foreach ($parts as $index => $part) {
            $nextNode = $currentNode->findChild($part);

            if ($nextNode === null) {
                return null;
            }

            if ($nextNode->isWildcard) {
                $params = $this->extractWildcardParams(
                    $nextNode,
                    $parts,
                    $index,
                    $params
                );
                $currentNode = $nextNode;
                break;
            }

            if ($nextNode->paramName !== null) {
                $params[$nextNode->paramName] = $part;
            }

            $currentNode = $nextNode;
        }

        return [$currentNode, $params];
    }

    private function extractWildcardParams(
        TrieNode $node,
        array $parts,
        int $index,
        array $params
    ): array {
        $remaining = array_slice($parts, $index);
        $params[$node->paramName ?? 'wildcard'] = implode('/', $remaining);

        return $params;
    }

    private function getOrCreateRootNode(string $method): TrieNode
    {
        return $this->root[$method] ??= new TrieNode();
    }

    private function getOrCreateChildNode(
        TrieNode $node,
        string $part,
        array $routeConstraints = []
    ): TrieNode {
        if (!$this->parser->isDynamicPart($part)) {
            return $node->staticChildren[$part] ??= new TrieNode();
        }

        if ($this->parser->isWildcardPart($part)) {
            return $this->getOrCreateWildcardNode($node, $part);
        }

        $paramDetails = $this->parser->parseParameterDetails($part);
        $constraint = $routeConstraints[$paramDetails['name']] ?? $paramDetails['constraint'];

        return $this->getOrCreateParameterNode($node, $paramDetails, $constraint);
    }

    private function getOrCreateWildcardNode(TrieNode $node, string $part): TrieNode
    {
        if ($node->wildcardNode === null) {
            $paramName = $part === '*' ? 'wildcard' : rtrim($part, '*');

            $node->wildcardNode = new TrieNode(
                $paramName,
                constraint: null,
                isOptional: false,
                isWildcard: true
            );
        }

        return $node->wildcardNode;
    }

    private function getOrCreateParameterNode(
        TrieNode $node,
        array $paramDetails,
        string $constraint
    ): TrieNode {
        if ($paramDetails['wildcard']) {
            return $node->wildcardNode ??= $this->createNode(
                $paramDetails['name'],
                $constraint,
                $paramDetails['optional'],
                isWildcard: true
            );
        }

        return $node->parameterNode ??= $this->createNode(
            $paramDetails['name'],
            $constraint,
            $paramDetails['optional'],
            isWildcard: false
        );
    }

    private function createNode(
        string $name,
        string $constraint,
        bool $optional,
        bool $isWildcard
    ): TrieNode {
        return new TrieNode($name, $constraint, $optional, $isWildcard);
    }
}
