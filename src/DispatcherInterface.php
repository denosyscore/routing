<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Denosys\Routing\Strategy\InvocationStrategyInterface;

interface DispatcherInterface
{
    public function dispatch(ServerRequestInterface $request): ResponseInterface;
    public function setNotFoundHandler(callable $handler): void;
    public function setMethodNotAllowedHandler(callable $handler): void;
    public function setInvocationStrategy(InvocationStrategyInterface $strategy): void;
}
