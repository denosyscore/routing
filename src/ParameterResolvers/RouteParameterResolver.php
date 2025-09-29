<?php

declare(strict_types=1);

namespace Denosys\Routing\ParameterResolvers;

use Denosys\Routing\Attributes\FromRoute;
use Denosys\Routing\Priority;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionParameter;

class RouteParameterResolver implements ParameterResolverInterface
{
    public function canResolve(ReflectionParameter $parameter, array $routeArguments): bool
    {
        $fromRouteAttr = $this->getFromRouteAttribute($parameter);

        if ($fromRouteAttr) {
            $routeParamName = $fromRouteAttr->name ?? $parameter->getName();
            
            return array_key_exists($routeParamName, $routeArguments);
        }

        // Check for direct name match
        return array_key_exists($parameter->getName(), $routeArguments);
    }

    public function resolve(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $routeArguments
    ): mixed {
        $fromRouteAttr = $this->getFromRouteAttribute($parameter);

        if ($fromRouteAttr) {
            $routeParamName = $fromRouteAttr->name ?? $parameter->getName();
            
            return $routeArguments[$routeParamName] ?? null;
        }

        return $routeArguments[$parameter->getName()] ?? null;
    }

    public function getPriority(): int
    {
        return Priority::NORMAL->value;
    }

    private function getFromRouteAttribute(ReflectionParameter $parameter): ?FromRoute
    {
        $attributes = $parameter->getAttributes(FromRoute::class);

        return $attributes ? $attributes[0]->newInstance() : null;
    }
}
