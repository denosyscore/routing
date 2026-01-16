<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use RuntimeException;

/**
 * Thrown when circular dependencies are detected in resolver ordering.
 */
class CircularDependencyException extends RuntimeException
{
    /**
     * @param string[] $cycle The resolvers involved in the cycle
     */
    public function __construct(array $cycle = [])
    {
        $message = 'Circular dependency detected in parameter resolvers';
        
        if (!empty($cycle)) {
            $message .= ': ' . implode(' -> ', $cycle);
        }
        
        parent::__construct($message);
    }
}
