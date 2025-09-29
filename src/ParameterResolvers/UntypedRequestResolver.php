<?php

declare(strict_types=1);

namespace Denosys\Routing\ParameterResolvers;

use Denosys\Routing\Priority;
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

    public function getPriority(): int
    {
        return Priority::LOW->value;
    }
}
