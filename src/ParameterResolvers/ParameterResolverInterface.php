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
     * Get the priority of this resolver (higher = checked first)
     */
    public function getPriority(): int;
}
