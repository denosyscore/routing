<?php

declare(strict_types=1);

namespace Tests\Unit\ParameterResolvers;

use PHPUnit\Framework\TestCase;
use Denosys\Routing\ParameterResolvers\ResolverDependencySorter;
use Denosys\Routing\ParameterResolvers\ParameterResolverInterface;
use Denosys\Routing\Exceptions\CircularDependencyException;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionParameter;

/**
 * TDD tests for ResolverDependencySorter - topological sort for parameter resolvers.
 */
class ResolverDependencySorterTest extends TestCase
{
    /**
     * Test: Resolvers with no dependencies maintain stable order
     */
    public function testResolversWithNoDependenciesMaintainOrder(): void
    {
        $resolverA = $this->createMockResolver('A');
        $resolverB = $this->createMockResolver('B');
        $resolverC = $this->createMockResolver('C');

        $sorter = new ResolverDependencySorter();
        $sorted = $sorter->sort([$resolverA, $resolverB, $resolverC]);

        $this->assertCount(3, $sorted);
        // Order should be stable when no dependencies
        $this->assertSame($resolverA, $sorted[0]);
        $this->assertSame($resolverB, $sorted[1]);
        $this->assertSame($resolverC, $sorted[2]);
    }

    /**
     * Test: runsBefore places resolver before specified resolver
     */
    public function testRunsBeforePlacesResolverCorrectly(): void
    {
        $resolverA = $this->createMockResolver('A');
        $resolverB = $this->createMockResolver('B', runsBefore: [get_class($resolverA)]);

        $sorter = new ResolverDependencySorter();
        $sorted = $sorter->sort([$resolverA, $resolverB]);

        // B should run BEFORE A, so B comes first
        $this->assertSame($resolverB, $sorted[0]);
        $this->assertSame($resolverA, $sorted[1]);
    }

    /**
     * Test: runsAfter places resolver after specified resolver
     */
    public function testRunsAfterPlacesResolverCorrectly(): void
    {
        $resolverA = $this->createMockResolver('A');
        $resolverB = $this->createMockResolver('B', runsAfter: [get_class($resolverA)]);

        $sorter = new ResolverDependencySorter();
        $sorted = $sorter->sort([$resolverA, $resolverB]);

        // B should run AFTER A, so A comes first
        $this->assertSame($resolverA, $sorted[0]);
        $this->assertSame($resolverB, $sorted[1]);
    }

    /**
     * Test: Complex dependency chain is resolved correctly
     */
    public function testComplexDependencyChain(): void
    {
        // C runs after B, B runs after A
        // Expected order: A, B, C
        $resolverA = $this->createMockResolver('A');
        $resolverB = $this->createMockResolver('B', runsAfter: [get_class($resolverA)]);
        $resolverC = $this->createMockResolver('C', runsAfter: [get_class($resolverB)]);

        $sorter = new ResolverDependencySorter();
        // Pass in reverse order to test sorting
        $sorted = $sorter->sort([$resolverC, $resolverB, $resolverA]);

        $this->assertSame($resolverA, $sorted[0]);
        $this->assertSame($resolverB, $sorted[1]);
        $this->assertSame($resolverC, $sorted[2]);
    }

    /**
     * Test: Circular dependency throws exception
     */
    public function testCircularDependencyThrowsException(): void
    {
        $this->expectException(CircularDependencyException::class);

        // A depends on B, B depends on A = circular
        $resolverA = $this->createMockResolver('A', runsAfter: [FakeResolverB::class]);
        $resolverB = $this->createMockResolver('B', runsAfter: [get_class($resolverA)]);

        $sorter = new ResolverDependencySorter();
        $sorter->sort([$resolverA, $resolverB]);
    }

    /**
     * Test: Multiple runsBefore dependencies work correctly
     */
    public function testMultipleRunsBeforeDependencies(): void
    {
        $resolverA = $this->createMockResolver('A');
        $resolverB = $this->createMockResolver('B');
        $resolverC = $this->createMockResolver('C', runsBefore: [
            get_class($resolverA),
            get_class($resolverB),
        ]);

        $sorter = new ResolverDependencySorter();
        $sorted = $sorter->sort([$resolverA, $resolverB, $resolverC]);

        // C must come before both A and B
        $cIndex = array_search($resolverC, $sorted, true);
        $aIndex = array_search($resolverA, $sorted, true);
        $bIndex = array_search($resolverB, $sorted, true);

        $this->assertLessThan($aIndex, $cIndex, 'C should come before A');
        $this->assertLessThan($bIndex, $cIndex, 'C should come before B');
    }

    /**
     * Test: Dependency on non-existent resolver is ignored
     */
    public function testDependencyOnMissingResolverIsIgnored(): void
    {
        $resolverA = $this->createMockResolver('A', runsAfter: ['NonExistent\\Resolver']);
        $resolverB = $this->createMockResolver('B');

        $sorter = new ResolverDependencySorter();
        $sorted = $sorter->sort([$resolverA, $resolverB]);

        // Should not throw, just ignore missing dependency
        $this->assertCount(2, $sorted);
    }

    /**
     * Test: Empty resolver array returns empty
     */
    public function testEmptyResolverArrayReturnsEmpty(): void
    {
        $sorter = new ResolverDependencySorter();
        $sorted = $sorter->sort([]);

        $this->assertSame([], $sorted);
    }

    /**
     * Test: Single resolver returns single resolver
     */
    public function testSingleResolverReturnsSingleResolver(): void
    {
        $resolver = $this->createMockResolver('A');

        $sorter = new ResolverDependencySorter();
        $sorted = $sorter->sort([$resolver]);

        $this->assertCount(1, $sorted);
        $this->assertSame($resolver, $sorted[0]);
    }

    /**
     * Test: Two instances of one resolver class are both kept, in order
     */
    public function testInstancesOfTheSameClassAreNotTreatedAsACycle(): void
    {
        $first = new FakeResolverA();
        $second = new FakeResolverA();
        $before = new FakeResolverB(runsBefore: [FakeResolverA::class]);

        $sorted = (new ResolverDependencySorter())->sort([$first, $second, $before]);

        $this->assertSame([$before, $first, $second], $sorted);
    }

    /**
     * Helper: Create a resolver whose class is unique to its name
     *
     * @param list<string> $runsBefore
     * @param list<string> $runsAfter
     */
    private function createMockResolver(
        string $name,
        array $runsBefore = [],
        array $runsAfter = []
    ): ParameterResolverInterface {
        return match ($name) {
            'A' => new FakeResolverA($runsBefore, $runsAfter),
            'B' => new FakeResolverB($runsBefore, $runsAfter),
            'C' => new FakeResolverC($runsBefore, $runsAfter),
        };
    }
}

trait FakeResolverBehaviour
{
    /**
     * @param list<string> $runsBefore
     * @param list<string> $runsAfter
     */
    public function __construct(
        private array $runsBefore = [],
        private array $runsAfter = []
    ) {}

    public function canResolve(ReflectionParameter $parameter, array $routeArguments): bool
    {
        return false;
    }

    public function resolve(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $routeArguments
    ): mixed {
        return null;
    }

    public function runsBefore(): array
    {
        return $this->runsBefore;
    }

    public function runsAfter(): array
    {
        return $this->runsAfter;
    }
}

final class FakeResolverA implements ParameterResolverInterface
{
    use FakeResolverBehaviour;
}

final class FakeResolverB implements ParameterResolverInterface
{
    use FakeResolverBehaviour;
}

final class FakeResolverC implements ParameterResolverInterface
{
    use FakeResolverBehaviour;
}
