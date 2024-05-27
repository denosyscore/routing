<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;
use RuntimeException;
use Denosys\Routing\Exceptions\HttpExceptionInterface;

abstract class HttpException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        protected HttpStatus $status,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $reasonPhrase = $status->getReasonPhrase();
        parent::__construct($message ?: $reasonPhrase, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->status->value;
    }

    public function getReasonPhrase(): string
    {
        return $this->status->getReasonPhrase();
    }
}
