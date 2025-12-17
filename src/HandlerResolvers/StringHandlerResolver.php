<?php

declare(strict_types=1);

namespace Denosys\Routing\HandlerResolvers;

use Closure;
use Denosys\Routing\Priority;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Denosys\Routing\Exceptions\InvalidHandlerException;
use Denosys\Routing\Exceptions\HandlerNotFoundException;


class StringHandlerResolver implements HandlerResolverInterface
{
    public function __construct(
        private readonly ?ContainerInterface $container = null,
        private ?HandlerResolverChain $chain = null
    ) {
    }

    public function setChain(HandlerResolverChain $chain): void
    {
        $this->chain = $chain;
    }

    public function canResolve(Closure|array|string $handler): bool
    {
        return is_string($handler);
    }

    public function resolve(Closure|array|string $handler): callable
    {
        if (!is_string($handler)) {
            throw new InvalidHandlerException("Expected string handler, got " . gettype($handler));
        }

        if (str_contains($handler, '::')) {
            $parts = explode('::', $handler, 2);

            return $this->resolveArray($parts);
        }

        if (str_contains($handler, '@')) {
            $parts = explode('@', $handler, 2);

            return $this->resolveArray($parts);
        }

        return $this->resolveInvokable($handler);
    }

    public function getPriority(): int
    {
        return Priority::NORMAL->value;
    }

    private function resolveArray(array $parts): callable
    {
        if (count($parts) !== 2) {
            throw new InvalidHandlerException("Invalid handler format");
        }

        [$class, $method] = $parts;

        $instance = $this->resolveFromContainer($class);

        if (!method_exists($instance, $method)) {
            throw new InvalidHandlerException(
                "Method does not exist: " . get_class($instance) . '::' . $method
            );
        }

        return [$instance, $method];
    }

    private function resolveInvokable(string $class): callable
    {
        $instance = $this->resolveFromContainer($class);

        if (!is_callable($instance)) {
            throw new InvalidHandlerException(
                "Class is not invokable: " . get_class($instance)
            );
        }

        return $instance;
    }

    private function resolveFromContainer(string $class): object
    {
        if ($this->container) {
            try {
                if ($this->container->has($class)) {
                    $resolved = $this->container->get($class);

                    if (is_object($resolved)) {
                        return $resolved;
                    }
                }
            } catch (NotFoundExceptionInterface) {
                // Fall through to direct instantiation
            }
        }

        if (class_exists($class)) {
            return new $class();
        }

        throw new HandlerNotFoundException($class);
    }
}
