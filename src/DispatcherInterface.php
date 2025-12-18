<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Denosys\Routing\Strategy\InvocationStrategyInterface;
use Denosys\Routing\RouteManagerInterface;

interface DispatcherInterface
{
    public function dispatch(ServerRequestInterface $request): ResponseInterface;
    public function setNotFoundHandler(callable $handler): void;
    public function setMethodNotAllowedHandler(callable $handler): void;
    public function setInvocationStrategy(InvocationStrategyInterface $strategy): void;
    public function setRouteManager(RouteManagerInterface $routeManager): void;
    public function setExceptionHandler(callable $handler): void;
    public function markRoutesDirty(): void;
    public function setGlobalMiddleware(array $middleware): void;
}
