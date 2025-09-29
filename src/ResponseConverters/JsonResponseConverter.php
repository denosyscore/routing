<?php

declare(strict_types=1);

namespace Denosys\Routing\ResponseConverters;

use Denosys\Routing\Priority;
use JsonSerializable;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class JsonResponseConverter implements ResponseConverterInterface
{
    public function __construct(
        private ?ResponseFactoryInterface $responseFactory = null,
        private ?ContainerInterface $container = null
    ) {
    }

    public function canConvert(mixed $value): bool
    {
        return is_array($value)
            || $value instanceof JsonSerializable
            || (is_object($value) && method_exists($value, 'toArray'));
    }

    public function convert(mixed $value): ResponseInterface
    {
        $response = $this->createResponse();

        $data = match (true) {
            is_array($value) => $value,
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            is_object($value) && method_exists($value, 'toArray') => $value->toArray(),
            default => $value
        };

        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    public function getPriority(): int
    {
        return Priority::NORMAL->value;
    }

    private function createResponse(int $status = 200): ResponseInterface
    {
        if ($this->responseFactory) {
            return $this->responseFactory->createResponse($status);
        }

        if ($this->container && $this->container->has(ResponseFactoryInterface::class)) {
            /** @var ResponseFactoryInterface $factory */
            $factory = $this->container->get(ResponseFactoryInterface::class);
            return $factory->createResponse($status);
        }

        // Fallback to known PSR-7 implementations
        // TODO: Do we want to hardcode these?
        if (class_exists(\Laminas\Diactoros\Response::class)) {
            return new \Laminas\Diactoros\Response('php://memory', $status);
        }
        if (class_exists(\Nyholm\Psr7\Response::class)) {
            return new \Nyholm\Psr7\Response($status);
        }
        if (class_exists(\GuzzleHttp\Psr7\Response::class)) {
            return new \GuzzleHttp\Psr7\Response($status);
        }

        throw new \RuntimeException('No ResponseFactoryInterface bound and no known PSR-7 Response available');
    }
}
