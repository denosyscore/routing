<?php

declare(strict_types=1);

namespace Denosys\Routing\ParameterResolvers;

use Denosys\Routing\Exceptions\CircularDependencyException;

/**
 * Sorts parameter resolvers using topological sort based on their dependencies.
 * 
 * This implements Kahn's algorithm for topological sorting to resolve the
 * order of parameter resolvers based on their runsBefore() and runsAfter()
 * declarations.
 */
final class ResolverDependencySorter
{
    /**
     * Sort resolvers based on their dependency declarations.
     * 
     * @param ParameterResolverInterface[] $resolvers
     * @return ParameterResolverInterface[]
     * @throws CircularDependencyException If circular dependencies are detected
     */
    public function sort(array $resolvers): array
    {
        if (count($resolvers) <= 1) {
            return $resolvers;
        }

        // Build class name to resolver mapping
        $classToResolver = [];
        foreach ($resolvers as $resolver) {
            $classToResolver[get_class($resolver)] = $resolver;
        }

        // Build adjacency list and in-degree count
        // Edge A -> B means "A must come before B"
        $adjacency = [];   // class => [classes that must come after]
        $inDegree = [];    // class => number of dependencies
        
        foreach ($resolvers as $resolver) {
            $class = get_class($resolver);
            if (!isset($adjacency[$class])) {
                $adjacency[$class] = [];
            }
            if (!isset($inDegree[$class])) {
                $inDegree[$class] = 0;
            }
        }

        // Process runsBefore: "this resolver runs before X" means edge: this -> X
        foreach ($resolvers as $resolver) {
            $class = get_class($resolver);
            foreach ($resolver->runsBefore() as $beforeClass) {
                if (isset($classToResolver[$beforeClass])) {
                    $adjacency[$class][] = $beforeClass;
                    $inDegree[$beforeClass]++;
                }
            }
        }

        // Process runsAfter: "this resolver runs after X" means edge: X -> this
        foreach ($resolvers as $resolver) {
            $class = get_class($resolver);
            foreach ($resolver->runsAfter() as $afterClass) {
                if (isset($classToResolver[$afterClass])) {
                    $adjacency[$afterClass][] = $class;
                    $inDegree[$class]++;
                }
            }
        }

        // Kahn's algorithm: start with nodes with no incoming edges
        $queue = [];
        foreach ($inDegree as $class => $degree) {
            if ($degree === 0) {
                $queue[] = $class;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $classToResolver[$current];

            foreach ($adjacency[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // If we haven't sorted all nodes, there's a cycle
        if (count($sorted) !== count($resolvers)) {
            // Find the cycle for error reporting
            $remaining = array_filter($inDegree, fn($d) => $d > 0);
            throw new CircularDependencyException(array_keys($remaining));
        }

        return $sorted;
    }
}
