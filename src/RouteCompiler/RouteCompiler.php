<?php

declare(strict_types=1);

namespace Denosys\Routing\RouteCompiler;

use Denosys\Routing\RouteParser\RouteParser;

class RouteCompiler
{
    private RouteParser $parser;

    public function __construct(?RouteParser $parser = null)
    {
        $this->parser = $parser ?? new RouteParser();
    }

    /**
     * Compile a simple route pattern into a regex
     *
     * @return array{regex: string, params: array<string>}
     */
    public function compileSimpleRoute(string $pattern, array $constraints = []): array
    {
        $params = [];
        $parts = explode('/', trim($pattern, '/'));
        $regexParts = [];

        foreach ($parts as $part) {
            if (preg_match('/\{([^}]+)\}/', $part, $matches)) {
                $paramDetails = $this->parser->parseParameterDetails($part);
                $paramName = $paramDetails['name'];
                $isOptional = $paramDetails['optional'];

                $params[] = $paramName;

                $constraint = $constraints[$paramName] ?? null;
                $constraintPattern = $this->buildConstraintPattern($constraint);

                if ($isOptional) {
                    $regexParts[] = "(?:\/" . $constraintPattern . ")?";
                } else {
                    $regexParts[] = "\/" . $constraintPattern;
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

    /**
     * Build a constraint pattern for regex
     */
    private function buildConstraintPattern(?string $constraint): string
    {
        if ($constraint === null) {
            return "([^\/]+)";
        }

        // Escape forward slashes in the constraint
        $escaped = str_replace('/', '\/', $constraint);
     
        return "($escaped)";
    }

    /**
     * Compile a constraint for direct matching
     */
    public function compileConstraint(string $constraint): string
    {
        $escapedConstraint = str_replace('/', '\/', $constraint);
        
        return '/^' . $escapedConstraint . '$/';
    }

    /**
     * Extract parameters from a matched path using compiled regex
     */
    public function extractParameters(array $matches, array $paramNames): array
    {
        $params = [];

        for ($i = 1; $i < count($matches); $i++) {
            $paramIndex = $i - 1;
            
            if (isset($matches[$i]) && $matches[$i] !== '' && isset($paramNames[$paramIndex])) {
                $params[$paramNames[$paramIndex]] = $matches[$i];
            }
        }

        return $params;
    }
}
