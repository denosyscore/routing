<?php

declare(strict_types=1);

namespace Denosys\Routing\ParameterResolvers;

use Psr\Http\Message\ServerRequestInterface;
use ReflectionParameter;

class UntypedRequestResolver implements ParameterResolverInterface
{
    public function canResolve(ReflectionParameter $parameter, array $routeArguments): bool
    {
        return $parameter->getType() === null && $parameter->getName() === 'request';
    }

    public function resolve(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $routeArguments
    ): mixed {
        if ($parameter->getName() === 'request') {
            return $request;
        }

        return null;
    }

    public function runsBefore(): array
    {
        return [];
    }

    public function runsAfter(): array
    {
        return [RouteParameterResolver::class];
    }
}
