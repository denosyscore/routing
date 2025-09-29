<?php

declare(strict_types=1);

namespace Denosys\Routing\HandlerResolvers;

use Denosys\Routing\Exceptions\InvalidHandlerException;
use Psr\Container\ContainerInterface;

class HandlerResolverChain
{
    /** @var HandlerResolverInterface[] */
    private array $resolvers = [];

    public function __construct(?ContainerInterface $container = null)
    {
        $this->initializeResolvers($container);
    }

    private function initializeResolvers(?ContainerInterface $container): void
    {
        $stringResolver = new StringHandlerResolver($container);

        $this->resolvers = [
            new CallableResolver(),
            $stringResolver,
            new ArrayHandlerResolver($container),
        ];

        // Set back-reference for recursive resolution
        $stringResolver->setChain($this);

        // Sort by priority (highest first)
        usort($this->resolvers, fn($a, $b) => $b->getPriority() - $a->getPriority());
    }

    /**
     * Resolve a handler using the chain
     *
     * @param mixed $handler The handler to resolve
     * @return callable The resolved callable
     * @throws InvalidHandlerException If no resolver can handle the handler
     */
    public function resolve(mixed $handler): callable
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
        usort($this->resolvers, fn($a, $b) => $b->getPriority() - $a->getPriority());
    }
}
