<?php

declare(strict_types=1);

namespace Denosys\Routing\ResponseConverters;

use Denosys\Routing\Priority;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

readonly class StringResponseConverter implements ResponseConverterInterface
{
    public function __construct(
        private ?ResponseFactoryInterface $responseFactory = null,
        private ?ContainerInterface $container = null
    ) {
    }

    public function canConvert(mixed $value): bool
    {
        return true;
    }

    public function convert(mixed $value): ResponseInterface
    {
        $response = $this->createResponse();
        $response->getBody()->write((string) $value);

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function getPriority(): int
    {
        return Priority::FALLBACK->value;
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
        // TODO: Do we need to hardcode these?
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
