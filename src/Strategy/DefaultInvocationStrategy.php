<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategy;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Denosys\Routing\Exceptions\InvalidHandlerException;
use Denosys\Routing\ParameterResolvers\UntypedRequestResolver;
use Denosys\Routing\ParameterResolvers\DefaultValueResolver;
use Denosys\Routing\ParameterResolvers\ParameterResolverInterface;
use Denosys\Routing\ParameterResolvers\RouteParameterResolver;
use Denosys\Routing\ParameterResolvers\TypeBasedResolver;
use Denosys\Routing\ResponseConverters\JsonResponseConverter;
use Denosys\Routing\ResponseConverters\PsrResponseConverter;
use Denosys\Routing\ResponseConverters\ResponseConverterInterface;
use Denosys\Routing\ResponseConverters\StringResponseConverter;

final class DefaultInvocationStrategy implements InvocationStrategyInterface
{
    /** @var array<string, array<int, callable(ServerRequestInterface, array): mixed>> */
    private array $parameterResolversCache = [];

    /** @var ParameterResolverInterface[] */
    private array $parameterResolvers = [];

    /** @var ResponseConverterInterface[] */
    private array $responseConverters = [];

    public function __construct(
        private ?ContainerInterface $container = null,
        private ?ResponseFactoryInterface $responseFactory = null
    ) {
        $this->initializeResolvers();
    }

    private function initializeResolvers(): void
    {
        $this->parameterResolvers = [
            new TypeBasedResolver($this->container, $this->responseFactory),
            new RouteParameterResolver(),
            new UntypedRequestResolver(),
            new DefaultValueResolver(),
        ];

        // Sort by priority (highest first)
        usort($this->parameterResolvers, fn($a, $b) => $b->getPriority() - $a->getPriority());

        $this->responseConverters = [
            new PsrResponseConverter(),
            new JsonResponseConverter($this->responseFactory, $this->container),
            new StringResponseConverter($this->responseFactory, $this->container),
        ];

        // Sort by priority (highest first)
        usort($this->responseConverters, fn($a, $b) => $b->getPriority() - $a->getPriority());
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
        $reflection = is_array($handler)
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
        return function (ServerRequestInterface $request, array $routeArguments) use ($parameter): mixed {
            foreach ($this->parameterResolvers as $resolver) {
                if ($resolver->canResolve($parameter, $routeArguments)) {
                    return $resolver->resolve($parameter, $request, $routeArguments);
                }
            }

            // No resolver could handle this parameter
            $hint = "Ensure the route defines parameter '{$parameter->getName()}' or provide a default value.";
            
            throw $this->cannotResolve($parameter, $hint);
        };
    }

    private function convertToResponse(mixed $result): ResponseInterface
    {
        foreach ($this->responseConverters as $converter) {
            if ($converter->canConvert($result)) {
                return $converter->convert($result);
            }
        }

        throw new InvalidHandlerException('No response converter could handle the result');
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
