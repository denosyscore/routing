<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategy;

use Closure;
use JsonSerializable;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Denosys\Routing\Attributes\FromRoute;
use Denosys\Routing\Exceptions\InvalidHandlerException;

final class DefaultInvocationStrategy implements InvocationStrategyInterface
{
    /** @var array<string, array<int, callable(ServerRequestInterface, array): mixed>> */
    private array $parameterResolversCache = [];

    public function __construct(private ?ContainerInterface $container = null)
    {
    }

    public function invoke(
        callable $handler,
        ServerRequestInterface $request,
        array $routeArguments
    ): ResponseInterface {
        $cacheKey = $this->callableCacheKey($handler);

        if (!isset($this->parameterResolversCache[$cacheKey])) {
            $this->parameterResolversCache[$cacheKey] = $this->buildParameterResolvers($handler, $routeArguments);
        }

        $arguments = [];
        foreach ($this->parameterResolversCache[$cacheKey] as $resolver) {
            $arguments[] = $resolver($request, $routeArguments);
        }

        $result = $handler(...$arguments);
        return $this->convertToResponse($result);
    }

    /**
     * Prepares per-parameter resolver closures (no reflection at invoke-time).
     *
     * @return array<int, callable(ServerRequestInterface, array): mixed>
     */
    private function buildParameterResolvers(callable $handler, array $routeArguments): array
    {
        $reflection = \is_array($handler)
            ? new ReflectionMethod($handler[0], $handler[1])
            : new ReflectionFunction($handler);

        $resolvers = [];

        foreach ($reflection->getParameters() as $parameter) {
            $resolvers[] = $this->createResolverForParameter($parameter, array_keys($routeArguments));
        }

        return $resolvers;
    }

    private function createResolverForParameter(ReflectionParameter $parameter, array $placeholderOrder): callable
    {
        $parameterType = $parameter->getType();
        $fromRouteAttr = $this->firstAttributeInstance($parameter, FromRoute::class);
        $routeParamName = $fromRouteAttr?->name ?? $parameter->getName();

        return function (ServerRequestInterface $request, array $routeArguments) use ($parameter, $parameterType, $routeParamName): mixed {
            // 1) TYPE-BASED INJECTION
            $injected = $this->resolveByType($parameterType, $request);
            if ($injected !== null) {
                return $injected;
            }

            // 2) EXPLICIT ATTRIBUTE MAPPING
            if ($fromRoute = $this->valueFromRoute($routeArguments, $routeParamName)) {
                return $fromRoute;
            }

            // 3) NAME-BASED ROUTE VARIABLE
            $parameterName = $parameter->getName();
            if ($this->valueExistsInRoute($routeArguments, $parameterName)) {
                return $routeArguments[$parameterName];
            }

            // 4) CONVENTION: `$request` by name (untype-hinted)
            if ($parameterType === null && $parameterName === 'request') {
                return $request;
            }

            // 5) DEFAULTS / NULLABLES
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            if ($this->allowsNull($parameter)) {
                return null;
            }

            // 6) ERROR
            $hint = $routeParamName !== $parameterName
                ? "Add #[FromRoute('{$routeParamName}')] or rename parameter to \${$routeParamName}"
                : "Ensure the route defines parameter '{$parameterName}'.";
            throw $this->cannotResolve($parameter, $hint);
        };
    }

    private function resolveByType(null|\ReflectionType $parameterType, ServerRequestInterface $request): mixed
    {
        if ($parameterType === null) {
            return null;
        }

        /** @var list<ReflectionNamedType> $types */
        $types = $parameterType instanceof ReflectionUnionType ? $parameterType->getTypes() : [$parameterType];

        foreach ($types as $named) {
            if (!$named instanceof ReflectionNamedType) {
                continue;
            }

            if ($named->isBuiltin()) {
                continue; // route variables are strings; no scalar coercion by default
            }

            $typeName = $named->getName();

            if ($typeName === ServerRequestInterface::class) {
                return $request;
            }

            if ($typeName === ResponseInterface::class) {
                return $this->createResponse();
            }

            // Prefer container for services
            if ($this->container && $this->container->has($typeName)) {
                return $this->container->get($typeName);
            }

            // Instantiate zero-arg classes if available
            if (class_exists($typeName)) {
                $classInfo = new \ReflectionClass($typeName);
                $constructor = $classInfo->getConstructor();

                if (!$classInfo->isAbstract() && ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0)) {
                    return $classInfo->newInstance();
                }
            }
        }

        return null;
    }

    private function valueFromRoute(array $routeArguments, string $name): mixed
    {
        return $this->valueExistsInRoute($routeArguments, $name) ? $routeArguments[$name] : null;
    }

    private function valueExistsInRoute(array $routeArguments, string $name): bool
    {
        return array_key_exists($name, $routeArguments);
    }

    private function allowsNull(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();
        return $type instanceof ReflectionNamedType ? $type->allowsNull() : $parameter->allowsNull();
    }

    private function convertToResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $response = $this->createResponse();

        // Arrays / json-serializable / toArray() → JSON
        if (is_array($result)) {
            $response->getBody()->write(json_encode($result, JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
        }

        if ($result instanceof JsonSerializable) {
            $response->getBody()->write(json_encode($result->jsonSerialize(), JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            $response->getBody()->write(json_encode($result->toArray(), JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
        }

        // Everything else → string
        $response->getBody()->write((string) $result);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    private function createResponse(int $status = 200): ResponseInterface
    {
        if ($this->container && $this->container->has(ResponseFactoryInterface::class)) {
            /** @var ResponseFactoryInterface $factory */
            $factory = $this->container->get(ResponseFactoryInterface::class);
            return $factory->createResponse($status);
        }

        // Keep original Laminas-first fallback for test compatibility
        if (class_exists(\Laminas\Diactoros\Response::class)) {
            return new \Laminas\Diactoros\Response('php://memory', $status);
        }
        if (class_exists(\Nyholm\Psr7\Response::class)) {
            return new \Nyholm\Psr7\Response($status);
        }
        if (class_exists(\GuzzleHttp\Psr7\Response::class)) {
            return new \GuzzleHttp\Psr7\Response($status);
        }

        throw new InvalidHandlerException('No ResponseFactoryInterface bound and no known PSR-7 Response available');
    }

    private function firstAttributeInstance(ReflectionParameter $parameter, string $attributeClass): ?object
    {
        $attributes = $parameter->getAttributes($attributeClass);
        return $attributes ? $attributes[0]->newInstance() : null;
    }

    private function callableCacheKey(callable $handler): string
    {
        if ($handler instanceof Closure) {
            return 'closure:' . spl_object_hash($handler);
        }
        if (is_string($handler)) {
            return 'function:' . $handler;
        }
        if (is_array($handler)) {
            $class = is_object($handler[0]) ? get_class($handler[0]) : (string) $handler[0];
            return $class . '::' . $handler[1];
        }
        return 'callable:' . md5(serialize($handler));
    }

    private function cannotResolve(ReflectionParameter $parameter, string $hint): InvalidHandlerException
    {
        $function = $parameter->getDeclaringFunction();
        $owner = $function instanceof ReflectionMethod
            ? $function->getDeclaringClass()->getName() . '::' . $function->getName()
            : $function->getName();

        $message = sprintf(
            "Cannot resolve parameter \$%s in handler %s. %s",
            $parameter->getName(),
            $owner,
            $hint
        );

        return new InvalidHandlerException($message);
    }
}
