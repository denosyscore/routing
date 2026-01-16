<?php

declare(strict_types=1);

namespace Denosys\Routing\ParameterResolvers;

use LogicException;
use ReflectionNamedType;
use ReflectionParameter;
use Psr\Http\Message\ServerRequestInterface;

class DefaultValueResolver implements ParameterResolverInterface
{
    public function canResolve(ReflectionParameter $parameter, array $routeArguments): bool
    {
        return $parameter->isDefaultValueAvailable() || $this->allowsNull($parameter);
    }

    public function resolve(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $routeArguments
    ): mixed {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($this->allowsNull($parameter)) {
            return null;
        }

        throw new LogicException(
            sprintf('Cannot resolve parameter $%s - no default value or nullable type', $parameter->getName())
        );
    }

    public function runsBefore(): array
    {
        return [];
    }

    public function runsAfter(): array
    {
        // DefaultValueResolver should always be last - runs after all others
        return [
            TypeBasedResolver::class,
            RouteParameterResolver::class,
            UntypedRequestResolver::class,
        ];
    }

    private function allowsNull(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType ? $type->allowsNull() : $parameter->allowsNull();
    }
}
