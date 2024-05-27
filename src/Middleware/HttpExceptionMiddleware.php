<?php

declare(strict_types=1);

namespace Denosys\Routing\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Denosys\Routing\Exceptions\HttpExceptionInterface;

class HttpExceptionMiddleware implements MiddlewareInterface
{
    public function __construct(protected ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpExceptionInterface $e) {
            return $this->createResponse($e);
        }
    }

    protected function createResponse(HttpExceptionInterface $exception): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($exception->getStatusCode());
        $response->getBody()->write($exception->getMessage());
        return $response->withHeader('Content-Type', 'text/plain');
    }
}
