<?php

declare(strict_types=1);

namespace Denosys\Routing\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewarePipeline implements RequestHandlerInterface
{
    protected array $middlewares;
    protected int $index = 0;
    protected ?RequestHandlerInterface $finalHandler = null;

    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function then(RequestHandlerInterface $handler): self
    {
        $this->finalHandler = $handler;
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middlewares[$this->index])) {
            if ($this->finalHandler) {
                return $this->finalHandler->handle($request);
            }
            
            throw new \RuntimeException('No final handler provided for middleware pipeline');
        }

        $middleware = $this->middlewares[$this->index];
        $this->index++;

        return $middleware->process($request, $this);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $pipeline = clone $this;
        $pipeline->then($handler);
        $pipeline->index = 0;
        
        return $pipeline->handle($request);
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function count(): int
    {
        return count($this->middlewares);
    }

    public function isEmpty(): bool
    {
        return empty($this->middlewares);
    }
}
