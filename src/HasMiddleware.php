<?php

declare(strict_types=1);

namespace Denosys\Routing;

use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Container\ContainerInterface;

trait HasMiddleware
{
    protected array $middlewareStack = [];

    public function middleware(MiddlewareInterface|array|string $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middlewares as $mw) {
            $this->middlewareStack[] = $this->resolveMiddleware($mw);
        }

        return $this;
    }

    public function getMiddlewareStack(): iterable
    {
        return $this->middlewareStack;
    }

    protected function resolveMiddleware(
        MiddlewareInterface|string $middleware,
        ?ContainerInterface $container = null
    ): MiddlewareInterface {
        if ($container === null && is_string($middleware) && class_exists($middleware)) {
            $middleware = new $middleware();
        }

        if ($container !== null && is_string($middleware) && $container->has($middleware)) {
            $middleware = $container->get($middleware);
        }

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        throw new InvalidArgumentException(sprintf('Unable to resolve middleware class: %s', $middleware));
    }
}
