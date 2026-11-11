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
        $resolvers = array_values($resolvers);
        if (count($resolvers) <= 1) {
            return $resolvers;
        }

        // Nodes are positions, so several instances of one class stay distinct.
        // Dependencies name classes, so an edge applies to every instance of it.
        $positionsByClass = [];
        foreach ($resolvers as $position => $resolver) {
            $positionsByClass[$resolver::class][] = $position;
        }

        // Edge A -> B means "A must come before B".
        $adjacency = array_fill(0, count($resolvers), []);
        $inDegree = array_fill(0, count($resolvers), 0);
        $addEdge = static function (int $from, int $to) use (&$adjacency, &$inDegree): void {
            if ($from !== $to && !in_array($to, $adjacency[$from], true)) {
                $adjacency[$from][] = $to;
                $inDegree[$to]++;
            }
        };

        foreach ($resolvers as $position => $resolver) {
            foreach ($resolver->runsBefore() as $class) {
                foreach ($positionsByClass[$class] ?? [] as $other) {
                    $addEdge($position, $other);
                }
            }
            foreach ($resolver->runsAfter() as $class) {
                foreach ($positionsByClass[$class] ?? [] as $other) {
                    $addEdge($other, $position);
                }
            }
        }

        // Kahn's algorithm, always taking the earliest ready position so that
        // unconstrained resolvers keep their registration order.
        $ready = array_keys(array_filter($inDegree, static fn (int $degree): bool => $degree === 0));
        $sorted = [];
        while ($ready !== []) {
            sort($ready);
            $current = array_shift($ready);
            $sorted[] = $resolvers[$current];

            foreach ($adjacency[$current] as $next) {
                if (--$inDegree[$next] === 0) {
                    $ready[] = $next;
                }
            }
        }

        if (count($sorted) !== count($resolvers)) {
            $remaining = [];
            foreach ($inDegree as $position => $degree) {
                if ($degree > 0) {
                    $remaining[] = $resolvers[$position]::class;
                }
            }
            throw new CircularDependencyException(array_values(array_unique($remaining)));
        }

        return $sorted;
    }
}
