<?php

declare(strict_types=1);

namespace Denosys\Routing\Strategy;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface InvocationStrategyInterface
{
    public function invoke(
        callable $handler,
        ServerRequestInterface $request,
        array $routeArguments,
    ): ResponseInterface;
}
