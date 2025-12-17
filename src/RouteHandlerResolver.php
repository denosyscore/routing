<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;
use Denosys\Routing\HandlerResolvers\HandlerResolverChain;
use Denosys\Routing\Exceptions\InvalidHandlerException;

class RouteHandlerResolver implements RouteHandlerResolverInterface
{
    private HandlerResolverChain $resolverChain;

    public function __construct(
        protected ?ContainerInterface $container = null,
        ?HandlerResolverChain $resolverChain = null
    ) {
        $this->resolverChain = $resolverChain ?? new HandlerResolverChain($container);
    }

    /**
     * Resolve the given handler into a callable.
     *
     * @param Closure|array|string $handler
     *
     * @return callable
     *
     * @throws InvalidHandlerException
     */
    public function resolve(Closure|array|string $handler): callable
    {
        return $this->resolverChain->resolve($handler);
    }
}
