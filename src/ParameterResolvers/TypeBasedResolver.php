<?php

declare(strict_types=1);

namespace Denosys\Routing\ParameterResolvers;

use Denosys\Routing\Priority;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

class TypeBasedResolver implements ParameterResolverInterface
{
    public function __construct(
        private ?ContainerInterface $container = null,
        private ?ResponseFactoryInterface $responseFactory = null
    ) {
    }

    public function canResolve(ReflectionParameter $parameter, array $routeArguments): bool
    {
        $type = $parameter->getType();
     
        if ($type === null) {
            return false;
        }

        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        foreach ($types as $namedType) {
            if (!$namedType instanceof ReflectionNamedType || $namedType->isBuiltin()) {
                continue;
            }

            $typeName = $namedType->getName();

            if ($this->canResolveType($typeName)) {
                return true;
            }
        }

        return false;
    }

    public function resolve(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $routeArguments
    ): mixed {
        $type = $parameter->getType();
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        foreach ($types as $namedType) {
            if (!$namedType instanceof ReflectionNamedType || $namedType->isBuiltin()) {
                continue;
            }

            $typeName = $namedType->getName();

            if ($typeName === ServerRequestInterface::class) {
                return $request;
            }

            if ($typeName === ResponseInterface::class) {
                return $this->createResponse();
            }

            if ($this->container && $this->container->has($typeName)) {
                return $this->container->get($typeName);
            }

            if (class_exists($typeName)) {
                $classInfo = new ReflectionClass($typeName);
                $constructor = $classInfo->getConstructor();

                if (!$classInfo->isAbstract() &&
                    ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0)) {
                    return $classInfo->newInstance();
                }
            }
        }

        return null;
    }

    public function getPriority(): int
    {
        return Priority::HIGHEST->value;
    }

    private function canResolveType(string $typeName): bool
    {
        return $typeName === ServerRequestInterface::class
            || $typeName === ResponseInterface::class
            || ($this->container && $this->container->has($typeName))
            || $this->canInstantiate($typeName);
    }

    private function canInstantiate(string $typeName): bool
    {
        if (!class_exists($typeName)) {
            return false;
        }

        $classInfo = new ReflectionClass($typeName);
        $constructor = $classInfo->getConstructor();

        return !$classInfo->isAbstract() &&
               ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0);
    }

    private function createResponse(int $status = 200): ResponseInterface
    {
        if ($this->responseFactory) {
            return $this->responseFactory->createResponse($status);
        }

        if ($this->container && $this->container->has(ResponseFactoryInterface::class)) {
            /** @var ResponseFactoryInterface $factory */
            $factory = $this->container->get(ResponseFactoryInterface::class);
            return $factory->createResponse($status);
        }

        // Fallback to known PSR-7 implementations
        // TODO: Do we want to hardcode these?
        if (class_exists(\Laminas\Diactoros\Response::class)) {
            return new \Laminas\Diactoros\Response('php://memory', $status);
        }
        if (class_exists(\Nyholm\Psr7\Response::class)) {
            return new \Nyholm\Psr7\Response($status);
        }
        if (class_exists(\GuzzleHttp\Psr7\Response::class)) {
            return new \GuzzleHttp\Psr7\Response($status);
        }

        throw new \RuntimeException('No ResponseFactoryInterface bound and no known PSR-7 Response available');
    }
}
