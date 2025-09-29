<?php

declare(strict_types=1);

namespace Denosys\Routing\HandlerResolvers;

use Denosys\Routing\Exceptions\HandlerNotFoundException;
use Denosys\Routing\Exceptions\InvalidHandlerException;
use Denosys\Routing\Priority;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ArrayHandlerResolver implements HandlerResolverInterface
{
    public function __construct(private ?ContainerInterface $container = null)
    {
    }

    public function canResolve(mixed $handler): bool
    {
        return is_array($handler) && count($handler) === 2;
    }

    public function resolve(mixed $handler): callable
    {
        if (!is_array($handler) || count($handler) !== 2) {
            throw new InvalidHandlerException(
                "Invalid array handler format. Expected [object/class, method]."
            );
        }

        [$target, $method] = $handler;

        if (is_string($target)) {
            $target = $this->resolveTarget($target);
        }

        if (!is_object($target)) {
            throw new InvalidHandlerException(
                "First element of handler array must be object or class name"
            );
        }

        if (!is_string($method)) {
            throw new InvalidHandlerException(
                "Second element of handler array must be method name"
            );
        }

        if (!method_exists($target, $method)) {
            throw new InvalidHandlerException(
                "Method does not exist: " . get_class($target) . '::' . $method
            );
        }

        return [$target, $method];
    }

    public function getPriority(): int
    {
        return Priority::BELOW_NORMAL->value;
    }

    private function resolveTarget(string $class): object
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
