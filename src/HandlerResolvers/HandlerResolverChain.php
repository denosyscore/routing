<?php

declare(strict_types=1);

namespace Denosys\Routing\HandlerResolvers;

use Closure;
use Psr\Container\ContainerInterface;
use Denosys\Routing\Exceptions\InvalidHandlerException;

class HandlerResolverChain
{
    /** @var HandlerResolverInterface[] */
    private array $resolvers = [];

    public function __construct(?ContainerInterface $container = null, ?array $resolvers = null)
    {
        $this->initializeResolvers($container, $resolvers);
    }

    private function initializeResolvers(?ContainerInterface $container, ?array $customResolvers): void
    {
        if ($customResolvers !== null) {
            $this->resolvers = $this->sortResolvers($customResolvers);
            $this->attachChainToStringResolvers();

            return;
        }

        $stringResolver = new StringHandlerResolver($container);

        $this->resolvers = [
            new CallableResolver(),
            $stringResolver,
            new ArrayHandlerResolver($container),
        ];

        $stringResolver->setChain($this);

        $this->resolvers = $this->sortResolvers($this->resolvers);
    }

    /**
     * Resolve a handler using the chain
     *
     * @param Closure|array|string $handler The handler to resolve
     * @return callable The resolved callable
     * @throws InvalidHandlerException If no resolver can handle the handler
     */
    public function resolve(Closure|array|string $handler): callable
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->canResolve($handler)) {
                return $resolver->resolve($handler);
            }
        }

        throw new InvalidHandlerException(
            "Cannot resolve handler of type: " . gettype($handler)
        );
    }

    /**
     * Add a custom resolver to the chain
     */
    public function addResolver(HandlerResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;

        // Re-sort by priority
        $this->resolvers = $this->sortResolvers($this->resolvers);
        $this->attachChainToStringResolvers();
    }

    private function sortResolvers(array $resolvers): array
    {
        usort($resolvers, fn($a, $b) => $b->getPriority() - $a->getPriority());

        return $resolvers;
    }

    private function attachChainToStringResolvers(): void
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver instanceof StringHandlerResolver) {
                $resolver->setChain($this);
            }
        }
    }
}
