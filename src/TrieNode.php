<?php

declare(strict_types=1);

namespace Denosys\Routing;

class TrieNode
{
    /** @var RouteInterface[] */
    public array $routes = [];
    
    public array $staticChildren = [];
    public ?TrieNode $parameterNode = null;
    public ?TrieNode $wildcardNode = null;

    public ?string $paramName = null;
    public ?string $compiledConstraint = null;
    public bool $isOptional = false;
    public bool $isWildcard = false;
    
    private static array $constraintCache = [];

    public function __construct(
        ?string $paramName = null,
        public ?string $constraint = null,
        bool $isOptional = false,
        bool $isWildcard = false
    ) {
        $this->paramName = $paramName;
        $this->isOptional = $isOptional;
        $this->isWildcard = $isWildcard;
        
        if ($constraint !== null) {
            $this->compiledConstraint = $this->compileConstraint($constraint);
        }
    }

    public function findChild(string $segment): ?TrieNode
    {
        if (isset($this->staticChildren[$segment])) {
            return $this->staticChildren[$segment];
        }
        
        if ($this->parameterNode !== null && $this->parameterNode->matchesConstraint($segment)) {
            return $this->parameterNode;
        }
        
        return $this->wildcardNode;
    }

    public function matchesConstraint(string $value): bool
    {
        if ($this->compiledConstraint === null) {
            return true;
        }
        
        return preg_match($this->compiledConstraint, $value) === 1;
    }

    private function compileConstraint(string $constraint): string
    {
        if (!isset(self::$constraintCache[$constraint])) {
            $escapedConstraint = str_replace('/', '\/', $constraint);
            self::$constraintCache[$constraint] = '/^' . $escapedConstraint . '$/';
        }
        
        return self::$constraintCache[$constraint];
    }
}
