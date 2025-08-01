<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategy;

use JsonSerializable;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionNamedType;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
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
            } elseif ($name === 'request') {
                $resolvedParameter = $request;
            } elseif ($parameter->isDefaultValueAvailable()) {
                $resolvedParameter = $parameter->getDefaultValue();
            } else {
                throw new InvalidHandlerException("Cannot resolve parameter {$name}");
            }

            $parameters[] = $resolvedParameter;
        }

        $result = $handler(...$parameters);
        
        return $this->convertToResponse($result);
    }

    protected function convertToResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $response = $this->createResponse();

        // Handle different return types
        if (is_array($result)) {
            $response = $this->handleJsonResponse($response, $result);
        } elseif (is_object($result) && method_exists($result, 'toArray')) {
            $response = $this->handleJsonResponse($response, $result->toArray());
        } elseif ($result instanceof JsonSerializable) {
            $response = $this->handleJsonResponse($response, $result->jsonSerialize());
        } elseif (is_object($result) && method_exists($result, '__toString')) {
            $response = $this->handleStringResponse($response, (string) $result);
        } else {
            $response = $this->handleStringResponse($response, (string) $result);
        }

        return $response;
    }

    protected function handleStringResponse(ResponseInterface $response, string $content): ResponseInterface
    {
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    protected function handleJsonResponse(ResponseInterface $response, array $data): ResponseInterface
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    protected function createResponse(int $status = 200): ResponseInterface
    {
        if ($this->container && $this->container->has(ResponseFactoryInterface::class)) {
            $factory = $this->container->get(ResponseFactoryInterface::class);
            return $factory->createResponse($status);
        }

        // Fallback - assume Laminas\Diactoros is available
        if (class_exists('Laminas\Diactoros\Response')) {
            return new \Laminas\Diactoros\Response('php://memory', $status);
        }

        throw new InvalidHandlerException('No ResponseFactoryInterface available and no fallback response class found');
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
            return $this->createResponse();
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
