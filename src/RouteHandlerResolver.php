<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Denosys\Routing\Exceptions\InvalidHandlerException;
use Denosys\Routing\Exceptions\HandlerNotFoundException;

class RouteHandlerResolver implements RouteHandlerResolverInterface
{
    public function __construct(protected ?ContainerInterface $container = null)
    {
    }

    /**
     * Resolve the given handler into a callable.
     *
     * @param Closure|array|string $handler
     *
     * @return callable
     *
     * @throws InvalidHandlerException
     */
    public function resolve(Closure|array|string $handler): callable
    {
        if ($handler instanceof Closure || is_callable($handler)) {
            return $handler;
        }

        if (is_string($handler)) {
            return $this->resolveStringHandler($handler);
        }

        if (is_array($handler)) {
            return $this->resolveArrayHandler($handler);
        }

        throw new InvalidHandlerException(
            "Invalid route handler type: " . gettype($handler)
        );
    }

    /**
     * Resolve a string handler.
     *
     * @param string $handler
     *
     * @return callable
     *
     * @throws HandlerNotFoundException
     * @throws InvalidHandlerException
     */
    protected function resolveStringHandler(string $handler): callable
    {
        if (str_contains($handler, '::')) {
            $handler = explode('::', $handler, 2);
            return $this->resolve($handler);
        }

        if (str_contains($handler, '@')) {
            $handler = explode('@', $handler, 2);
            return $this->resolve($handler);
        }

        return $this->resolveInvokable($handler);
    }

    /**
     * Resolve an array handler.
     *
     * @param array $handler
     *
     * @return callable
     *
     * @throws InvalidHandlerException
     */
    protected function resolveArrayHandler(array $handler): callable
    {
        if (isset($handler[0]) && is_string($handler[0])) {
            $handler[0] = $this->resolveFromContainer($handler[0]);
        }

        if (isset($handler[0]) && is_object($handler[0]) && isset($handler[1])) {
            if (is_string($handler[1]) && method_exists($handler[0], $handler[1])) {
                return [$handler[0], $handler[1]];
            }

            throw new InvalidHandlerException(
                "Undefined route handler method: " . get_class($handler[0]) . '::' . (is_string($handler[1]) ? $handler[1] : 'unknown') . '()'
            );
        }

        throw new InvalidHandlerException(
            "Invalid route handler array format. Expected [object, method] or [class, method]."
        );
    }

    /**
     * Resolve an invokable class from the container or instantiate it directly.
     *
     * @param string $class
     *
     * @return callable
     *
     * @throws HandlerNotFoundException
     * @throws InvalidHandlerException
     */
    protected function resolveInvokable(string $class): callable
    {
        $instance = $this->resolveFromContainer($class);

        if (is_callable($instance)) {
            return $instance;
        }

        throw new InvalidHandlerException(
            "Resolved handler is not callable: " . get_class($instance)
        );
    }

    /**
     * Resolve a class name from the container or create a new instance.
     *
     * @param string $class
     *
     * @return mixed
     *
     * @throws HandlerNotFoundException
     */
    protected function resolveFromContainer(string $class): mixed
    {
        try {
            if ($this->container instanceof ContainerInterface && $this->container->has($class)) {
                return $this->container->get($class);
            }
        } catch (NotFoundExceptionInterface $e) {
            throw new HandlerNotFoundException($class, 0, $e);
        }

        if (class_exists($class)) {
            return new $class();
        }

        throw new HandlerNotFoundException($class);
    }
}
