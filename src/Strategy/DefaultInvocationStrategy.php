<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Psr\Container\ContainerInterface;
use Denosys\Routing\Exceptions\InvalidHandlerException;

class DefaultInvocationStrategy implements InvocationStrategyInterface
{
    public function __construct(protected ?ContainerInterface $container = null)
    {
    }

    public function invoke(
        callable $handler,
        ServerRequestInterface $request,
        array $routeArguments
    ): ResponseInterface {
        $parameters = [];

        $reflection = is_array($handler)
            ? new ReflectionMethod($handler[0], $handler[1])
            : new ReflectionFunction($handler);

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $resolvedParameter = $this->resolveClassParameter($type, $request, $routeArguments);
            } elseif (array_key_exists($name, $routeArguments)) {
                $resolvedParameter = $routeArguments[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $resolvedParameter = $parameter->getDefaultValue();
            } else {
                throw new InvalidHandlerException("Cannot resolve parameter {$name}");
            }

            $parameters[] = $resolvedParameter;
        }

        return $handler(...$parameters);
    }

    protected function resolveClassParameter(
        ReflectionNamedType $type,
        ServerRequestInterface $request,
        array $routeArguments
    ) {
        $typeName = $type->getName();

        if ($typeName === ServerRequestInterface::class) {
            return $request;
        }

        if ($typeName === ResponseInterface::class) {
            return $this->container->get(ResponseInterface::class);
        }

        if ($this->container && $this->container->has($typeName)) {
            return $this->container->get($typeName);
        }

        if (class_exists($typeName)) {
            return new $typeName();
        }

        throw new InvalidHandlerException("Cannot resolve parameter of type {$typeName}");
    }
}
