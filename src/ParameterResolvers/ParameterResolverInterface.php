<?php

declare(strict_types=1);

namespace Denosys\Routing\ParameterResolvers;

use Psr\Http\Message\ServerRequestInterface;
use ReflectionParameter;

interface ParameterResolverInterface
{
    /**
     * Check if this resolver can handle the given parameter
     */
    public function canResolve(ReflectionParameter $parameter, array $routeArguments): bool;

    /**
     * Resolve the parameter value
     *
     * @param ReflectionParameter $parameter The parameter to resolve
     * @param ServerRequestInterface $request The current request
     * @param array<string, mixed> $routeArguments Route parameters
     * @return mixed The resolved value
     */
    public function resolve(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $routeArguments
    ): mixed;

    /**
     * Get the list of resolver classes this resolver should run BEFORE.
     * This resolver will be placed earlier in the chain than the specified resolvers.
     * 
     * @return class-string<ParameterResolverInterface>[]
     */
    public function runsBefore(): array;

    /**
     * Get the list of resolver classes this resolver should run AFTER.
     * This resolver will be placed later in the chain than the specified resolvers.
     * 
     * @return class-string<ParameterResolverInterface>[]
     */
    public function runsAfter(): array;
}
